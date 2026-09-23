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
    private const MAIN_CLASS = 'com.intoolswetrust.jsignpdf.Bootstrap';

    private JSignFileService $fileService;

    public function __construct()
    {
        $this->fileService = JSignFileService::instance();
    }

    public function sign(JSignParam $params): string
    {
        try {
            $this->validation($params);

            $commandSign = $this->commandSign($params);
            $passwords = array_merge([$params->getPassword()], array_values($params->getPasswords()));
            [$output, $exitCode] = $this->run($commandSign, $params, $passwords);

            $out = json_encode($output);
            if ($out === false) {
                throw new Exception('Error to sign PDF.');
            }
            $this->throwIf($exitCode !== 0, "Error to sign PDF. $out");

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
            if ($params->getTempPath()) {
                $this->fileService->deleteTempFiles($params->getTempPath(), $params->getTempName());
            }

            throw new Exception($e->getMessage());
        }
    }

    /**
     * JSignPdf don't works as well at CLI interfaceif the password have
     * unicode chars. As workaround, I changed the password certificate in
     * memory.
     */
    private function repackCertificateIfPasswordIsUnicode(
        JSignParam $params,
        \OpenSSLCertificate|string $cert,
        \OpenSSLAsymmetricKey|\OpenSSLCertificate|string $pkey,
    ): void {
        $detectedEncodingString = mb_detect_encoding($params->getPassword(), 'ASCII', true);
        if ($detectedEncodingString === false) {
            $password = md5(microtime());
            $newCert = $this->exportToPkcs12($cert, $pkey, $password);
            $params->setPassword($password);
            $params->setCertificate($newCert);
        }
    }

    public function getVersion(JSignParam $params): string
    {
        $java     = escapeshellarg($this->javaCommand($params));
        $jSignPdf = $this->jSignPdfInvocation($params);

        $command = implode(' ', array_merge([$java], $this->javaOptions($params), [$jSignPdf, '--version'])) . ' 2>&1';
        [$output] = $this->run($command, $params);
        $lastRow = (string) end($output);
        if (strpos($lastRow, 'version') === false) {
            return '';
        }
        return explode('version ', $lastRow)[1];
    }

    /**
     * @return list<SignatureField>
     */
    public function getSignatureFields(JSignParam $params): array
    {
        $this->validateSignatureFieldInspection($params);

        $pdf = $this->fileService->storeFile(
            $params->getTempPath(),
            $params->getTempName('.pdf'),
            $params->getPdf()
        );

        try {
            $command = $this->commandListSignatureFields($params, $pdf);
            [$output, $exitCode] = $this->run($command, $params);

            if ($exitCode !== 0) {
                $diagnostic = trim(implode(PHP_EOL, $output));

                if ($diagnostic === '') {
                    $diagnostic = 'Can not read the signature fields.';
                }

                throw new Exception($diagnostic);
            }

            return $this->parseSignatureFields($output);
        } finally {
            $this->fileService->deleteFile($pdf);
        }
    }

    private function validateSignatureFieldInspection(JSignParam $params): void
    {
        $this->throwIf(
            empty($params->getTempPath()) || !is_writable($params->getTempPath()),
            'Temp Path is invalid or has not permission to writable.'
        );

        $this->throwIf(
            empty($params->getPdf()),
            'PDF is Empty or Invalid.'
        );
    }

    private function commandListSignatureFields(JSignParam $params, string $pdf): string
    {
        $java = escapeshellarg($this->javaCommand($params));
        $jSignPdf = $this->jSignPdfInvocation($params);
        $pdf = escapeshellarg($pdf);

        $javaOptions = implode(
            ' ',
            array_merge(['-Duser.language=en'], $this->javaOptions($params))
        );

        return "$java $javaOptions $jSignPdf --quiet --list-sig-fields $pdf 2>&1";
    }

    /**
     * @param list<string> $output
     * @return list<SignatureField>
     */
    private function parseSignatureFields(array $output): array
    {
        $fields = [];
        $sawHeader = false;
        $sawNoFields = false;

        foreach ($output as $line) {
            if (preg_match('/^Signature fields of .+:$/u', $line) === 1) {
                if ($sawHeader || $sawNoFields || $fields !== []) {
                    throw new Exception(
                        "Unexpected signature field output: $line"
                    );
                }

                $sawHeader = true;
                continue;
            }

            if (preg_match('/:\s*no signature fields\s*$/', $line) === 1) {
                if ($sawHeader || $sawNoFields || $fields !== []) {
                    throw new Exception(
                        "Unexpected signature field output: $line"
                    );
                }

                $sawNoFields = true;
                continue;
            }

            if ($sawNoFields) {
                throw new Exception(
                    "Unexpected signature field output: $line"
                );
            }

            $line = preg_replace(
                '/\s+- this field name shadows the selector of the same name, the field name wins\s*$/',
                '',
                $line
            );

            if ($line === null) {
                throw new Exception(
                    'Unexpected signature field output.'
                );
            }

            $matches = [];

            $matched = preg_match(
                '/^#\d+\s+(.+?)\s+page\s+(\d+)\s+\[(-?(?:\d+(?:\.\d*)?|\.\d+))\s+(-?(?:\d+(?:\.\d*)?|\.\d+))\s+(-?(?:\d+(?:\.\d*)?|\.\d+))\s+(-?(?:\d+(?:\.\d*)?|\.\d+))\]\s+(blank|signed)(?:,\s*hidden|\s+hidden)?\s*$/u',
                $line,
                $matches
            );

            if ($matched !== 1) {
                throw new Exception(
                    "Unexpected signature field output: $line"
                );
            }

            $fields[] = new SignatureField(
                rtrim($matches[1]),
                (int) $matches[2],
                (float) $matches[3],
                (float) $matches[4],
                (float) $matches[5],
                (float) $matches[6],
                $matches[7] === 'signed',
                preg_match('/(?:,\s*|\s+)hidden\s*$/', $line) === 1,
            );
        }

        if ($sawNoFields) {
            return [];
        }

        if ($fields === []) {
            throw new Exception(
                'Unexpected signature field output: empty output'
            );
        }

        return $fields;
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
            if ($javaVersion === false) {
                throw new Exception('Java not installed, set the flag "isUseJavaInstalled" as false or install java.');
            }
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
        list($pdf, $certificate) = $this->storeTempFiles($params);
        $java          = escapeshellarg($this->javaCommand($params));
        $jSignPdf      = $this->jSignPdfInvocation($params);
        $pdf           = escapeshellarg($pdf);
        $certificate   = escapeshellarg($certificate);
        $pathPdfSigned = escapeshellarg($params->getPathPdfSigned());
        $javaOptions   = implode(' ', array_merge(['-Duser.language=en'], $this->javaOptions($params)));

        $passwords = '';
        $signatureField = '';
        if ($params->getSignatureField() !== null) {
            $signatureField = '--sig-field ' . escapeshellarg($params->getSignatureField()) . ' ';
        }

        foreach (array_keys($params->getPasswords()) as $option) {
            $passwords .= "$option - ";
        }

        return "$java $javaOptions $jSignPdf $pdf -ksf $certificate --enable-stdin-passwords -ksp - {$passwords}{$signatureField}{$params->getJSignParameters()} -d $pathPdfSigned 2>&1";
    }

    /**
     * @return list<string>
     */
    private function javaOptions(JSignParam $params): array
    {
        return array_map('escapeshellarg', $params->getJavaOptions());
    }

    private function jSignPdfInvocation(JSignParam $params): string
    {
        $jSignPdfPath = $this->getJSignPdfPath($params);
        $libDir = $jSignPdfPath . '/lib';
        if (is_dir($libDir)) {
            return '-classpath ' . escapeshellarg($libDir . '/*') . ' ' . self::MAIN_CLASS;
        }
        return '-jar ' . escapeshellarg($jSignPdfPath . '/JSignPdf.jar');
    }

    /**
     * @param list<string> $passwords
     * @psalm-return list{list<string>, int}
     */
    private function run(string $command, JSignParam $params, array $passwords = []): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
        ];
        $pipes = [];
        $environmentVariables = $params->getEnvironmentVariables();
        $env = $environmentVariables === [] ? null : array_merge(getenv() ?: [], $environmentVariables);
        $process = proc_open($command, $descriptors, $pipes, null, $env);
        if (!is_resource($process)) {
            throw new Exception('Error to run JSignPdf.');
        }
        $written = $passwords === [] ? 0 : fwrite($pipes[0], implode(PHP_EOL, $passwords) . PHP_EOL);
        fclose($pipes[0]);
        if ($written === false) {
            fclose($pipes[1]);
            proc_close($process);
            throw new Exception('Error to run JSignPdf.');
        }
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exitCode = proc_close($process);

        return [explode(PHP_EOL, rtrim((string) $output, PHP_EOL)), $exitCode];
    }

    private function javaCommand(JSignParam $params): string
    {
        $javaRuntimeService = new JavaRuntimeService();
        return $javaRuntimeService->getPath($params);
    }

    private function getJSignPdfPath(JSignParam $params): string
    {
        $JsignPdfRuntimeService = new JSignPdfRuntimeService();
        return $JsignPdfRuntimeService->getPath($params);
    }

    private function throwIf(bool $condition, string $message): void
    {
        if ($condition) {
            throw new Exception($message);
        }
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
        $password = $params->getPassword();

        $this->clearOpenSslErrors();

        if (openssl_pkcs12_read($certificate, $certInfo, $password)) {
            $this->repackCertificateIfPasswordIsUnicode($params, $certInfo['cert'], $certInfo['pkey']);
            return $certInfo;
        }

        $errors = $this->getOpenSslErrors();
        if ($this->hasUnsupportedLegacyAlgorithmError($errors)) {
            $opensslVersion = exec('openssl version');
            if ($opensslVersion === false) {
                return [];
            }

            $tempPassword = tempnam(sys_get_temp_dir(), 'pfx');
            $tempEncriptedOriginal = tempnam(sys_get_temp_dir(), 'original');
            $tempEncriptedRepacked = tempnam(sys_get_temp_dir(), 'repacked');
            $tempDecrypted = tempnam(sys_get_temp_dir(), 'decripted');

            if ($tempDecrypted === false || $tempPassword === false || $tempEncriptedOriginal === false || $tempEncriptedRepacked === false) {
                return [];
            }

            file_put_contents($tempPassword, $password);
            file_put_contents($tempEncriptedOriginal, $certificate);

            $this->safeExec(
                $tempPassword,
                $tempEncriptedOriginal,
                $tempDecrypted,
                $tempEncriptedRepacked
            );

            $certificateRepacked = file_get_contents($tempEncriptedRepacked);

            unlink($tempPassword);
            unlink($tempEncriptedOriginal);
            unlink($tempEncriptedRepacked);
            unlink($tempDecrypted);

            if ($certificateRepacked === false) {
                return [];
            }

            $this->clearOpenSslErrors();

            if (!openssl_pkcs12_read($certificateRepacked, $certInfo, $password)) {
                $this->getOpenSslErrors();
                return [];
            }

            $params->setCertificate($certificateRepacked);

            $this->repackCertificateIfPasswordIsUnicode(
                $params,
                $certInfo['cert'],
                $certInfo['pkey']
            );

            return $certInfo;
        }

        return [];
    }

    private function clearOpenSslErrors(): void
    {
        while (openssl_error_string() !== false) {
        }
    }

    /**
     * @return list<string>
     */
    private function getOpenSslErrors(): array
    {
        $errors = [];

        while (($error = openssl_error_string()) !== false) {
            $errors[] = $error;
        }

        return $errors;
    }

    /**
     * @param list<string> $errors
     */
    private function hasUnsupportedLegacyAlgorithmError(array $errors): bool
    {
        foreach ($errors as $error) {
            if (str_contains($error, 'digital envelope routines::unsupported')) {
                return true;
            }
        }

        return false;
    }

    private function safeExec(
        string $tempPassword,
        string $tempEncriptedOriginal,
        string $tempDecrypted,
        string $tempEncriptedRepacked,
    ): void {
        $tempPassword = escapeshellarg($tempPassword);
        $tempEncriptedOriginal = escapeshellarg($tempEncriptedOriginal);
        $tempDecrypted = escapeshellarg($tempDecrypted);
        $tempEncriptedRepacked = escapeshellarg($tempEncriptedRepacked);

        exec(
            <<<REPACK_COMMAND
            cat $tempPassword | openssl pkcs12 -legacy -in $tempEncriptedOriginal -nodes -out $tempDecrypted -passin stdin &&
            cat $tempPassword | openssl pkcs12 -in $tempDecrypted -export -out $tempEncriptedRepacked -passout stdin
            REPACK_COMMAND
        );
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
