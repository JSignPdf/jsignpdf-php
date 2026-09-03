<?php

namespace Jeidison\JSignPDF\Sign;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 */
class JSignParam
{
    /** @var array<string, string> */
    private const PASSWORD_OPTIONS = [
        '-kp'   => '--key-password',
        '-opwd' => '--owner-password',
        '-upwd' => '--user-password',
        '-tscp' => '--tsa-cert-password',
        '-tsp'  => '--tsa-password',
    ];

    private const JSIGNPDF_VERSION = '3.1.0';

    /** @var list<string> */
    private const DEFAULT_JSIGN_PARAMETERS = ['-a', '-kst', 'PKCS12'];

    private string $pdf = '';
    private string $certificate = '';
    private string $password = '';
    private string $pathPdfSigned = '';
    /** @var list<string> */
    private array $jSignParameters = self::DEFAULT_JSIGN_PARAMETERS;
    private bool $isUseJavaInstalled = false;
    private string $javaPath = '';
    /** @var list<string> */
    private array $javaOptions = [];
    /** @var array<string, string> */
    private array $environmentVariables = [];
    private string $tempPath = '';
    private string $tempName = '';
    private bool $isOutputTypeBase64 = false;
    private string $jSignPdfPath = '';
    private string $javaDownloadUrl = 'https://github.com/adoptium/temurin21-binaries/releases/download/jdk-21.0.8%2B9/OpenJDK21U-jre_x64_linux_hotspot_21.0.8_9.tar.gz';
    private string $jSignPdfDownloadUrl = '';
    /** @var array<string, string> */
    private array $passwords = [];
    /** @var array<string, string> */
    private array $parameterPasswords = [];

    public function __construct()
    {
        $this->tempName = md5(time() . uniqid() . mt_rand());
        $this->tempPath = __DIR__ . DIRECTORY_SEPARATOR . '..'  . DIRECTORY_SEPARATOR . '..'  . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
        $this->javaPath = $this->tempPath . 'java'  . DIRECTORY_SEPARATOR . 'bin'  . DIRECTORY_SEPARATOR . 'java';
        $this->jSignPdfPath = $this->tempPath . 'jsignpdf';
        $this->jSignPdfDownloadUrl = self::buildJSignPdfDownloadUrl();
    }

    private static function buildJSignPdfDownloadUrl(): string
    {
        $tag = 'JSignPdf_' . str_replace('.', '_', self::JSIGNPDF_VERSION);
        return "https://github.com/intoolswetrust/jsignpdf/releases/download/$tag/jsignpdf-" . self::JSIGNPDF_VERSION . '-minimal.zip';
    }

    public static function instance(): self
    {
        return new self();
    }

    public function getPdf(): string
    {
        return $this->pdf;
    }

    public function setPdf(string $pdf): self
    {
        $this->pdf = $pdf;
        return $this;
    }

    public function getCertificate(): string
    {
        return $this->certificate;
    }

    public function setCertificate(string $certificate): self
    {
        $this->certificate = $certificate;
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getPathPdfSigned(): string
    {
        return $this->pathPdfSigned != null ? $this->pathPdfSigned : $this->getTempPath();
    }

    public function setPathPdfSigned(string $pathPdfSigned): self
    {
        $this->pathPdfSigned = $pathPdfSigned;
        return $this;
    }

    public function getJSignParameters(): string
    {
        return implode(' ', array_map('escapeshellarg', $this->jSignParameters));
    }

    /**
     * @param list<string> $parameters
     */
    public function setJSignParameters(array $parameters): self
    {
        $this->parameterPasswords = [];
        $this->jSignParameters = $this->takePasswords($parameters);
        return $this;
    }

    /**
     * Adds to the current parameters instead of replacing them.
     *
     * @param list<string> $parameters
     */
    public function addJSignParameters(array $parameters): self
    {
        $this->jSignParameters = array_merge($this->jSignParameters, $this->takePasswords($parameters));
        return $this;
    }

    public function setKeyPassword(string $password): self
    {
        return $this->setPasswordOption('-kp', $password);
    }

    public function setOwnerPassword(string $password): self
    {
        return $this->setPasswordOption('-opwd', $password);
    }

    public function setUserPassword(string $password): self
    {
        return $this->setPasswordOption('-upwd', $password);
    }

    public function setTsaCertPassword(string $password): self
    {
        return $this->setPasswordOption('-tscp', $password);
    }

    public function setTsaPassword(string $password): self
    {
        return $this->setPasswordOption('-tsp', $password);
    }

    /** @return array<string, string> */
    public function getPasswords(): array
    {
        $passwords = [];
        foreach (array_keys(self::PASSWORD_OPTIONS) as $option) {
            $password = $this->passwords[$option] ?? $this->parameterPasswords[$option] ?? null;
            if ($password !== null) {
                $passwords[$option] = $password;
            }
        }
        return $passwords;
    }

    private function setPasswordOption(string $option, string $password): self
    {
        $this->passwords[$option] = $password;
        return $this;
    }

    /**
     * @param list<string> $parameters
     * @return list<string>
     */
    private function takePasswords(array $parameters): array
    {
        $remaining = [];
        for ($i = 0; $i < count($parameters); $i++) {
            $option = $this->passwordOption($parameters[$i]);
            if ($option !== null && isset($parameters[$i + 1]) && $parameters[$i + 1] !== '-') {
                $this->parameterPasswords[$option] = $parameters[++$i];
                continue;
            }
            $assignment = explode('=', $parameters[$i], 2);
            $option = count($assignment) === 2 ? $this->passwordOption($assignment[0]) : null;
            if ($option !== null && $assignment[1] !== '-') {
                $this->parameterPasswords[$option] = $assignment[1];
                continue;
            }
            $remaining[] = $parameters[$i];
        }
        return $remaining;
    }

    private function passwordOption(string $parameter): ?string
    {
        if (isset(self::PASSWORD_OPTIONS[$parameter])) {
            return $parameter;
        }
        $option = array_search($parameter, self::PASSWORD_OPTIONS, true);
        return $option === false ? null : $option;
    }

    public function getTempPath(): string
    {
        return $this->tempPath;
    }

    public function setTempPath(string $tempPath): self
    {
        $this->tempPath = $tempPath;
        return $this;
    }

    public function getTempName(string $extension = ''): string
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

    public function setJavaPath(string $javaPath): self
    {
        $this->javaPath = $javaPath;
        return $this;
    }

    public function getJavaPath(): string
    {
        return $this->javaPath;
    }

    /**
     * JVM options for the java command, kept out of javaPath so it stays a
     * plain executable path (e.g. `-Duser.home=/tmp/jsignpdf-home`).
     *
     * @param list<string> $javaOptions
     */
    public function setJavaOptions(array $javaOptions): self
    {
        $this->javaOptions = $javaOptions;
        return $this;
    }

    /** @return list<string> */
    public function getJavaOptions(): array
    {
        return $this->javaOptions;
    }

    /**
     * Environment variables for the process that runs JSignPdf (e.g.
     * `JSIGNPDF_HOME`).
     *
     * @param array<string, string> $environmentVariables
     */
    public function setEnvironmentVariables(array $environmentVariables): self
    {
        $this->environmentVariables = $environmentVariables;
        return $this;
    }

    /** @return array<string, string> */
    public function getEnvironmentVariables(): array
    {
        return $this->environmentVariables;
    }

    public function setJSignPdfPath(string $jSignPdfPath): self
    {
        $this->jSignPdfPath = $jSignPdfPath;
        return $this;
    }

    public function getJSignPdfPath(): string
    {
        return $this->jSignPdfPath;
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
