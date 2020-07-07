<?php

namespace Jeidison\JSignPDF\Sign;

use Exception;
use Jeidison\JSignPDF\JavaCommandService;
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
            exec($commandSign, $output);

            $out            = json_encode($output);
            $messageSuccess = "INFO  Finished: Signature succesfully created.";
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

    private function validation(JSignParam $params)
    {
        $this->throwIf(empty($params->getTempPath()) || !is_writable($params->getTempPath()), 'Temp Path is invalid or has not permission to writable.');
        $this->throwIf(empty($params->getPdf()), 'PDF is Empty or Invalid.');
        $this->throwIf(empty($params->getCertificate()), 'Certificate is Empty or Invalid.');
        $this->throwIf(empty($params->getPassword()), 'Certificate Password is Empty.');
        $this->throwIf(!$this->isPasswordCertificateValid($params->getCertificate(), $params->getPassword()), 'Certificate Password Invalid.');
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
        $jSignPdf = $this->jSignPdfJarPath();

        return "$java -jar $jSignPdf $pdf -ksf $certificate -ksp {$params->getPassword()} {$params->getJSignParameters()} -d {$params->getPathPdfSigned()}";
    }

    private function javaCommand(JSignParam $params)
    {
        return JavaCommandService::instance()->command($params->isUseJavaInstalled());
    }

    private function jSignPdfJarPath()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'jsignpdf-1.6.4' . DIRECTORY_SEPARATOR . 'JSignPdf.jar';
    }

    private function throwIf($condition, $message)
    {
        if ($condition)
            throw new Exception($message);
    }

    private function isPasswordCertificateValid($certificate, $password)
    {
        return openssl_pkcs12_read($certificate, $certInfo, $password);
    }
}