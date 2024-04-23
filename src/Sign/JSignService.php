<?php

namespace Jeidison\JSignPDF\Sign;

use Exception;
use Jeidison\JSignPDF\JSignFileService;
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
            \exec($commandSign, $output);

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

    public function getVersion(JSignParam $params)
    {
        $java     = $this->javaCommand($params);
        $jSignPdf = $params->getjSignPdfJarPath();
        if (!$jSignPdf && class_exists('JSignPDF\JSignPDFBin\JSignPdfPathService')) {
            $jSignPdf = \JSignPDF\JSignPDFBin\JSignPdfPathService::jSignPdfJarPath();
        }
        $this->throwIf(!file_exists($jSignPdf), 'Jar of JSignPDF not found on path: '. $jSignPdf);

        $command = "$java -jar $jSignPdf --version 2>&1";
        \exec($command, $output);
        $lastRow = end($output);
        if (empty($output) || strpos($lastRow, 'version') === false) {
            return '';
        }
        return explode('version ', $lastRow)[1];
    }

    private function validation(JSignParam $params)
    {
        $this->throwIf(empty($params->getTempPath()) || !is_writable($params->getTempPath()), 'Temp Path is invalid or has not permission to writable.');
        $this->throwIf(empty($params->getPdf()), 'PDF is Empty or Invalid.');
        $this->throwIf(empty($params->getCertificate()), 'Certificate is Empty or Invalid.');
        $this->throwIf(empty($params->getPassword()), 'Certificate Password is Empty.');
        $this->throwIf(!$this->isPasswordCertificateValid($params->getCertificate(), $params->getPassword()), 'Certificate Password Invalid.');
        $this->throwIf($this->isExpiredCertificate($params->getCertificate(), $params->getPassword()), 'Certificate expired.');
        if ($params->isUseJavaInstalled()) {
            $javaVersion    = exec("java -version 2>&1");
            $hasJavaVersion = strpos($javaVersion, 'not found') === false;
            $this->throwIf(!$hasJavaVersion, 'Java not installed, set the flag "isUseJavaInstalled" as false or install java.');
        }
    }

    private function storeTempFiles(JSignParam $params)
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

    private function commandSign(JSignParam $params)
    {
        list ($pdf, $certificate) = $this->storeTempFiles($params);
        $java     = $this->javaCommand($params);
        $jSignPdf = $params->getjSignPdfJarPath();
        if (!$jSignPdf && class_exists('JSignPDF\JSignPDFBin\JSignPdfPathService')) {
            $jSignPdf = \JSignPDF\JSignPDFBin\JSignPdfPathService::jSignPdfJarPath();
        }
        $this->throwIf(!file_exists($jSignPdf), 'Jar of JSignPDF not found on path: '. $jSignPdf);

        return "$java -jar $jSignPdf $pdf -ksf $certificate -ksp '{$params->getPassword()}' {$params->getJSignParameters()} -d {$params->getPathPdfSigned()} 2>&1";
    }

    private function javaCommand(JSignParam $params)
    {
        if ($params->isUseJavaInstalled()) {
            return 'java';
        }
        if ($params->getJavaPath()) {
            return $params->getJavaPath();
        }
        if (!class_exists('JSignPDF\JSignPDFBin\JavaCommandService')) {
            throw new Exception("JSignPDF not found, install manually or run composer require jsignpdf/jsignpdf-bin", 1);
        }
        return \JSignPDF\JSignPDFBin\JavaCommandService::instance()->command($params->isUseJavaInstalled());
    }

    private function throwIf($condition, $message)
    {
        if ($condition)
            throw new Exception($message);
    }

    private function isPasswordCertificateValid($certificate, $password)
    {
        return $this->pkcs12Read($certificate, $password);
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
    private function pkcs12Read($certificate, $password)
    {
        if (openssl_pkcs12_read($certificate, $certInfo, $password)) {
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
            unlink($tempPassword);
            unlink($tempEncriptedOriginal);
            unlink($tempEncriptedRepacked);
            unlink($tempDecrypted);
            openssl_pkcs12_read($certificateRepacked, $certInfo, $password);
            return $certInfo;
        }
        return [];
    }

    private function isExpiredCertificate($certificate, $password)
    {
        $certInfo = $this->pkcs12Read($certificate, $password);
        $certificate = openssl_x509_parse($certInfo['cert']);
        $dateCert    = date_create()->setTimestamp($certificate['validTo_time_t']);
        return $dateCert <= date_create();
    }
}
