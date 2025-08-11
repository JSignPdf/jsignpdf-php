<?php

namespace Jeidison\JSignPDF\Sign;

use DateTime;
use Exception;
use Jeidison\JSignPDF\JSignFileService;
use Jeidison\JSignPDF\Runtime\JavaRuntimeService;
use Jeidison\JSignPDF\Runtime\JSignPdfRuntimeService;
use Throwable;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 */
class JSignService
{
    private $fileService;

    public function __construct()
    {
        $this->fileService = JSignFileService::instance();
    }

    public function sign(JSignParam $params)
    {
        try {
            $this->validation($params);

            $commandSign = $this->commandSign($params);
            exec($commandSign, $output);

            $out            = json_encode($output);
            $messageSuccess = "Finished: Signature succesfully created.";
            $isSigned       = strpos($out, $messageSuccess) !== false;

            $this->throwIf(!$isSigned, "Error to sign PDF. $out");

            $fileSigned = $this->fileService->contentFile(
                $params->getTempPdfSignedPath(),
                $params->isOutputTypeBase64()
            );

            $this->fileService->deleteTempFiles(
                $params->getTempPath(),
                $params->getTempName()
            );

            return $fileSigned;
        } catch (Throwable $e) {
            if ($params->getTempPath())
                $this->fileService->deleteTempFiles($params->getTempPath(), $params->getTempName());

            throw new Exception($e->getMessage());
        }
    }

    /**
     * JSignPdf don't works as well at CLI interfaceif the password have
     * unicode chars. As workaround, I changed the password certificate in
     * memory.
     */
    private function repackCertificateIfPasswordIsUnicode(JSignParam $params, $cert, $pkey): void
    {
        if (!mb_detect_encoding($params->getPassword(), 'ASCII', true)) {
            $password = md5(microtime());
            $newCert = $this->exportToPkcs12($cert, $pkey, $password);
            $params->setPassword($password);
            $params->setCertificate($newCert);
        }
    }

    public function getVersion(JSignParam $params): string
    {
        $java     = $this->javaCommand($params);
        $jSignPdf = $this->getjSignPdfJarPath($params);
        $jSignPdf = $params->getjSignPdfJarPath();

        $command = "$java -jar $jSignPdf --version 2>&1";
        exec($command, $output);
        $lastRow = end($output);
        if (empty($output) || strpos($lastRow, 'version') === false) {
            return '';
        }
        return explode('version ', $lastRow)[1];
    }

    private function validation(JSignParam $params): void
    {
        $this->throwIf(empty($params->getTempPath()) || !is_writable($params->getTempPath()), 'Temp Path is invalid or has not permission to writable.');
        $this->throwIf(empty($params->getPdf()), 'PDF is Empty or Invalid.');
        $this->throwIf(empty($params->getCertificate()), 'Certificate is Empty or Invalid.');
        $this->throwIf(empty($params->getPassword()), 'Certificate Password is Empty.');
        $this->throwIf(!$this->isPasswordCertificateValid($params), 'Certificate Password Invalid.');
        $this->throwIf($this->isExpiredCertificate($params), 'Certificate expired.');
        if ($params->isUseJavaInstalled()) {
            $javaVersion    = exec("java -version 2>&1");
            $hasJavaVersion = strpos($javaVersion, 'not found') === false;
            $this->throwIf(!$hasJavaVersion, 'Java not installed, set the flag "isUseJavaInstalled" as false or install java.');
        }
    }

    /**
     * @psalm-return list{mixed, mixed}
     */
    private function storeTempFiles(JSignParam $params): array
    {
        $pdf = $this->fileService->storeFile(
            $params->getTempPath(),
            $params->getTempName('.pdf'),
            $params->getPdf()
        );

        $certificate = $this->fileService->storeFile(
            $params->getTempPath(),
            $params->getTempName('.pfx'),
            $params->getCertificate()
        );

        return [$pdf, $certificate];
    }

    private function commandSign(JSignParam $params): string
    {
        list ($pdf, $certificate) = $this->storeTempFiles($params);
        $java     = $this->javaCommand($params);
        $jSignPdf = $this->getjSignPdfJarPath($params);

        $password = escapeshellarg($params->getPassword());
        return "$java -Duser.language=en -jar $jSignPdf $pdf -ksf $certificate -ksp {$password} {$params->getJSignParameters()} -d {$params->getPathPdfSigned()} 2>&1";
    }

    private function javaCommand(JSignParam $params): string
    {
        $javaRuntimeService = new JavaRuntimeService();
        return $javaRuntimeService->getPath($params);
    }

    private function getjSignPdfJarPath(JSignParam $params): string
    {
        $JsignPdfRuntimeService = new JSignPdfRuntimeService();
        return $JsignPdfRuntimeService->getPath($params);
    }

    private function throwIf(bool $condition, string $message): void
    {
        if ($condition)
            throw new Exception($message);
    }

    private function isPasswordCertificateValid(JSignParam $params): bool
    {
        return $this->pkcs12Read($params) ? true : false;
    }

    /**
     * Prevent error to read certificate generated with old version of
     * openssl and using a newest version of openssl.
     *
     * To check the password is necessary to repack the certificate using
     * openssl command. If the command don't exists, will consider that
     * the password is invalid.
     *
     * Reference:
     *
     * https://github.com/php/php-src/issues/12128
     * https://www.php.net/manual/en/function.openssl-pkcs12-read.php#128992
     */
    private function pkcs12Read(JSignParam $params): array
    {
        $certificate = $params->getCertificate();
        $password = (string) $params->getPassword();
        if (openssl_pkcs12_read($certificate, $certInfo, $password)) {
            $this->repackCertificateIfPasswordIsUnicode($params, $certInfo['cert'], $certInfo['pkey']);
            return $certInfo;
        }
        $msg = openssl_error_string();
        if ($msg === 'error:0308010C:digital envelope routines::unsupported') {
            if (!shell_exec('openssl version')) {
                return [];
            }
            $tempPassword = tempnam(sys_get_temp_dir(), 'pfx');
            $tempEncriptedOriginal = tempnam(sys_get_temp_dir(), 'original');
            $tempEncriptedRepacked = tempnam(sys_get_temp_dir(), 'repacked');
            $tempDecrypted = tempnam(sys_get_temp_dir(), 'decripted');
            file_put_contents($tempPassword, $password);
            file_put_contents($tempEncriptedOriginal, $certificate);
            shell_exec(<<<REPACK_COMMAND
                cat $tempPassword | openssl pkcs12 -legacy -in $tempEncriptedOriginal -nodes -out $tempDecrypted -passin stdin &&
                cat $tempPassword | openssl pkcs12 -in $tempDecrypted -export -out $tempEncriptedRepacked -passout stdin
                REPACK_COMMAND
            );
            $certificateRepacked = file_get_contents($tempEncriptedRepacked);
            if ($certificateRepacked === false) {
                return [];
            }
            $params->setCertificate($certificateRepacked);
            unlink($tempPassword);
            unlink($tempEncriptedOriginal);
            unlink($tempEncriptedRepacked);
            unlink($tempDecrypted);
            openssl_pkcs12_read($certificateRepacked, $certInfo, $password);
            $this->repackCertificateIfPasswordIsUnicode($params, $certInfo['cert'], $certInfo['pkey']);
            return $certInfo;
        }
        return [];
    }

    private function exportToPkcs12(\OpenSSLCertificate|string $certificate, \OpenSSLAsymmetricKey|\OpenSSLCertificate|string $privateKey, string $password): string
    {
        $certContent = null;
        openssl_pkcs12_export(
            $certificate,
            $certContent,
            $privateKey,
            $password,
        );
        return $certContent;
    }

    private function isExpiredCertificate(JSignParam $params): bool
    {
        $certInfo = $this->pkcs12Read($params);
        $certificate = openssl_x509_parse($certInfo['cert']);
        if (!is_array($certificate)) {
            throw new Exception('Invalid certificate');
        }
        $currentDate = new DateTime();
        $dateCert    = (clone $currentDate)->setTimestamp($certificate['validTo_time_t']);
        return $dateCert <= $currentDate;
    }
}
