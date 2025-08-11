<?php

namespace Jeidison\JSignPDF\Sign;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 */
class JSignParam
{
    private ?string $pdf = null;
    private ?string $certificate = null;
    private ?string $password = null;
    private string $pathPdfSigned = '';
    private string $JSignParameters = "-a -kst PKCS12";
    private bool $isUseJavaInstalled = false;
    private string $javaPath = '';
    private ?string $tempPath = null;
    private string $tempName = '';
    private bool $isOutputTypeBase64 = false;
    private string $jSignPdfJarPath;
    private string $javaDownloadUrl = 'https://github.com/adoptium/temurin21-binaries/releases/download/jdk-21.0.8%2B9/OpenJDK21U-jre_x64_linux_hotspot_21.0.8_9.tar.gz';
    private string $jSignPdfDownloadUrl = 'https://github.com/intoolswetrust/jsignpdf/releases/download/JSignPdf_2_3_0/jsignpdf-2.3.0.zip';

    public function __construct()
    {
        $this->tempName = md5(time() . uniqid() . mt_rand());
        $this->tempPath = __DIR__ . DIRECTORY_SEPARATOR . '..'  . DIRECTORY_SEPARATOR . '..'  . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
        $this->javaPath = $this->tempPath . 'java'  . DIRECTORY_SEPARATOR . 'bin'  . DIRECTORY_SEPARATOR . 'java';
        $this->jSignPdfJarPath = $this->tempPath . 'jsignpdf'  . DIRECTORY_SEPARATOR . 'JSignPdf.jar';
    }

    public static function instance(): self
    {
        return new self();
    }

    public function getPdf(): ?string
    {
        return $this->pdf;
    }

    public function setPdf(?string $pdf): self
    {
        $this->pdf = $pdf;
        return $this;
    }

    public function getCertificate(): ?string
    {
        return $this->certificate;
    }

    public function setCertificate(?string $certificate): self
    {
        $this->certificate = $certificate;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getPathPdfSigned(): string
    {
        return $this->pathPdfSigned != null ? $this->pathPdfSigned : $this->getTempPath();
    }

    public function setPathPdfSigned($pathPdfSigned): self
    {
        $this->pathPdfSigned = $pathPdfSigned;
        return $this;
    }

    public function getJSignParameters(): string
    {
        return $this->JSignParameters;
    }

    public function setJSignParameters(string $JSignParameters): self
    {
        $this->JSignParameters = $JSignParameters;
        return $this;
    }

    public function getTempPath(): ?string
    {
        return $this->tempPath;
    }

    public function setTempPath(?string $tempPath): self
    {
        $this->tempPath = $tempPath;
        return $this;
    }

    public function getTempName(string|null $extension = null): string
    {
        return $this->tempName.$extension;
    }

    public function isUseJavaInstalled(): bool
    {
        return $this->isUseJavaInstalled;
    }

    public function setIsUseJavaInstalled(bool $isUseJavaInstalled): self
    {
        $this->isUseJavaInstalled = $isUseJavaInstalled;
        return $this;
    }

    public function setJavaPath(string $javaPath): self {
        $this->javaPath = $javaPath;
        return $this;
    }

    public function getJavaPath(): string {
        return $this->javaPath;
    }

    public function setjSignPdfJarPath($jSignPdfJarPath): self
    {
        $this->jSignPdfJarPath = $jSignPdfJarPath;
        return $this;
    }

    public function getjSignPdfJarPath(): string
    {
        return $this->jSignPdfJarPath;
    }

    public function isOutputTypeBase64(): bool
    {
        return $this->isOutputTypeBase64;
    }

    public function setIsOutputTypeBase64(bool $isOutputTypeBase64): self
    {
        $this->isOutputTypeBase64 = $isOutputTypeBase64;
        return $this;
    }

    public function getTempPdfPath(): string
    {
        return $this->getTempPath() . $this->getTempName('.pdf');
    }

    public function getTempPdfSignedPath(): string
    {
        return $this->getPathPdfSigned() . $this->getTempName('_signed.pdf');
    }

    public function getTempCertificatePath(): string
    {
        return $this->getTempPath() . $this->getTempName('.pfx');
    }

    public function setJavaDownloadUrl(string $url): self
    {
        $this->javaDownloadUrl = $url;
        return $this;
    }

    public function getJavaDownloadUrl(): string
    {
        return $this->javaDownloadUrl;
    }

    public function setJSignPdfDownloadUrl(string $url): self
    {
        $this->jSignPdfDownloadUrl = $url;
        return $this;
    }

    public function getJSignPdfDownloadUrl(): string
    {
        return $this->jSignPdfDownloadUrl;
    }
}
