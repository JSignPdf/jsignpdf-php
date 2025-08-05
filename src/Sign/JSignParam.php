<?php

namespace Jeidison\JSignPDF\Sign;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 */
class JSignParam
{
    private $pdf;
    private $certificate;
    private $password;
    private $pathPdfSigned;
    private $JSignParameters = "-a -kst PKCS12";
    private $isUseJavaInstalled = false;
    private $javaPath = '';
    private $tempPath;
    private $tempName;
    private $isOutputTypeBase64 = false;
    private $jSignPdfJarPath;
    private ?string $javaVersion = null;
    private ?string $javaDownloadUrl = null;
    private ?string $jSignPdfDownloadUrl = null;
    private ?string $jsignPdfVersion = null;

    public function __construct()
    {
        $this->tempName = md5(time() . uniqid() . mt_rand());
        $this->tempPath = __DIR__ . DIRECTORY_SEPARATOR . '..'  . DIRECTORY_SEPARATOR . '..'  . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
    }

    public static function instance()
    {
        return new self();
    }

    public function getPdf()
    {
        return $this->pdf;
    }

    public function setPdf($pdf)
    {
        $this->pdf = $pdf;
        return $this;
    }

    public function getCertificate()
    {
        return $this->certificate;
    }

    public function setCertificate($certificate)
    {
        $this->certificate = $certificate;
        return $this;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    public function getPathPdfSigned()
    {
        return $this->pathPdfSigned != null ? $this->pathPdfSigned : $this->getTempPath();
    }

    public function setPathPdfSigned($pathPdfSigned)
    {
        $this->pathPdfSigned = $pathPdfSigned;
        return $this;
    }

    public function getJSignParameters(): string
    {
        return $this->JSignParameters;
    }

    public function setJSignParameters(string $JSignParameters)
    {
        $this->JSignParameters = $JSignParameters;
        return $this;
    }

    public function getTempPath()
    {
        return $this->tempPath;
    }

    public function setTempPath($tempPath)
    {
        $this->tempPath = $tempPath;
        return $this;
    }

    public function getTempName($extension = null)
    {
        return $this->tempName.$extension;
    }

    public function isUseJavaInstalled(): bool
    {
        return $this->isUseJavaInstalled;
    }

    public function setIsUseJavaInstalled(bool $isUseJavaInstalled)
    {
        $this->isUseJavaInstalled = $isUseJavaInstalled;
        return $this;
    }

    public function setJavaPath($javaPath): self {
        $this->javaPath = $javaPath;
        return $this;
    }

    public function getJavaPath(): string {
        return $this->javaPath;
    }

    public function setjSignPdfJarPath($jSignPdfJarPath)
    {
        $this->jSignPdfJarPath = $jSignPdfJarPath;
        return $this;
    }

    public function getjSignPdfJarPath()
    {
        return $this->jSignPdfJarPath;
    }

    public function setJsignPdfVersion(string $version): self
    {
        $this->jsignPdfVersion = $version;
        return $this;
    }

    public function getJsignPdfVersion(): ?string
    {
        return $this->jsignPdfVersion;
    }

    public function isOutputTypeBase64(): bool
    {
        return $this->isOutputTypeBase64;
    }

    public function setIsOutputTypeBase64(bool $isOutputTypeBase64)
    {
        $this->isOutputTypeBase64 = $isOutputTypeBase64;
        return $this;
    }

    public function getTempPdfPath()
    {
        return $this->getTempPath() . $this->getTempName('.pdf');
    }

    public function getTempPdfSignedPath()
    {
        return $this->getPathPdfSigned() . $this->getTempName('_signed.pdf');
    }

    public function getTempCertificatePath()
    {
        return $this->getTempPath() . $this->getTempName('.pfx');
    }

    public function setJavaDownloadUrl(string $url): self
    {
        $this->javaDownloadUrl = $url;
        return $this;
    }

    public function getJavaDownloadUrl(): ?string
    {
        return $this->javaDownloadUrl;
    }

    public function setJSignPdfDownloadUrl(string $url): self
    {
        $this->jSignPdfDownloadUrl = $url;
        return $this;
    }

    public function getJSignPdfDownloadUrl(): ?string
    {
        return $this->jSignPdfDownloadUrl;
    }

    public function setJavaVersion(string $javaVersion): self
    {
        $this->javaVersion = $javaVersion;
        return $this;
    }

    public function getJavaVersion(): ?string {
        return $this->javaVersion;
    }
}
