<?php

namespace Jeidison\JSignPDF\Sign;

use InvalidArgumentException;

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

    /**
     * The certificate password has its own setter, and JSignPdf always reads
     * it from stdin, so it never comes from the parameters.
     *
     * @var array<string, string>
     */
    private const CERTIFICATE_PASSWORD_OPTIONS = ['-ksp' => '--keystore-password'];

    /** @var array<string, string> */
    private const LONG_OPTIONS = [
        '-c'    => '--contact',
        '-cl'   => '--certification-level',
        '-d'    => '--out-directory',
        '-ec'   => '--encryption-certificate',
        '-eng'  => '--engine',
        '-fs'   => '--font-size',
        '-ha'   => '--hash-algorithm',
        '-ka'   => '--key-alias',
        '-ki'   => '--key-index',
        '-ksf'  => '--keystore-file',
        '-kst'  => '--keystore-type',
        '-l'    => '--location',
        '-lpf'  => '--load-properties-file',
        '-o'    => '--option',
        '-op'   => '--out-prefix',
        '-os'   => '--out-suffix',
        '-pe'   => '--encryption',
        '-pg'   => '--page',
        '-pl'   => '--pades-level',
        '-pr'   => '--print-right',
        '-r'    => '--reason',
        '-sf'   => '--sig-field',
        '-sn'   => '--signer-name',
        '-ta'   => '--tsa-authentication',
        '-ts'   => '--tsa-server-url',
        '-tscf' => '--tsa-cert-file',
        '-tsct' => '--tsa-cert-file-type',
        '-tsh'  => '--tsa-hash-algorithm',
        '-tsu'  => '--tsa-user',
    ];

    private const JSIGNPDF_VERSION = '3.2.0';

    private const JSIGNPDF_SHA256 = '960c1a90222682eef293f44421097b5c1f2cb00e9a9415cc608334616fbda00c';

    private const JAVA_DOWNLOAD_URL = 'https://github.com/adoptium/temurin21-binaries/releases/download/jdk-21.0.13%2B9/OpenJDK21U-jre_x64_linux_hotspot_21.0.13_9.tar.gz';

    private const JAVA_SHA256 = 'a535a1df9c6284d9618872984e026ae0352d1f7e9e0605425c0e6114f47351dd';

    /** @var array<array-key, string> */
    private const DEFAULT_JSIGN_PARAMETERS = ['-a', '-kst' => 'PKCS12'];

    private string $pdf = '';
    private string $certificate = '';
    private string $password = '';
    private string $pathPdfSigned = '';
    /** @var array<array-key, string> */
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
    private string $javaDownloadUrl = self::JAVA_DOWNLOAD_URL;
    private string $javaDownloadSha256 = self::JAVA_SHA256;
    private string $jSignPdfDownloadUrl = '';
    private string $jSignPdfDownloadSha256 = self::JSIGNPDF_SHA256;
    /** @var array<string, string> */
    private array $passwords = [];
    /** @var array<string, string> */
    private array $parameterPasswords = [];

    private ?string $signatureField = null;

    public function __construct()
    {
        $this->tempName = bin2hex(random_bytes(16));
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
        $this->password = $this->singleLinePassword('-ksp', $password);
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
        $parameters = [];
        foreach ($this->jSignParameters as $option => $value) {
            $parameters[] = is_string($option)
                ? $this->optionWithValue($option, $value)
                : escapeshellarg($value);
        }
        return implode(' ', $parameters);
    }

    private function optionWithValue(string $option, string $value): string
    {
        $longOption = self::LONG_OPTIONS[$option] ?? $option;
        if (str_starts_with($longOption, '--')) {
            return escapeshellarg("$longOption=$value");
        }
        return escapeshellarg($option) . ' ' . escapeshellarg($value);
    }

    /**
     * Options that take a value are keyed by the option; flags are items
     * without a key.
     *
     * @param array<array-key, string> $parameters
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
     * @param array<array-key, string> $parameters
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
        $this->passwords[$option] = $this->singleLinePassword($option, $password);
        return $this;
    }

    /**
     * @param array<array-key, string> $parameters
     * @return array<array-key, string>
     */
    private function takePasswords(array $parameters): array
    {
        $remaining = [];
        foreach ($parameters as $key => $value) {
            if (is_string($key)) {
                $this->rejectTheCertificatePassword($key);
                $option = $this->passwordOption($key);
                if ($option !== null) {
                    $this->parameterPasswords[$option] = $this->singleLinePassword($option, $value);
                    continue;
                }
                $remaining[$key] = $value;
                continue;
            }
            $assignment = explode('=', $value, 2);
            if (count($assignment) === 2) {
                $this->rejectTheCertificatePassword($assignment[0]);
                $option = $this->passwordOption($assignment[0]);
                if ($option !== null) {
                    $this->parameterPasswords[$option] = $this->singleLinePassword($option, $assignment[1]);
                    continue;
                }
                $remaining[] = $value;
                continue;
            }
            $this->rejectTheCertificatePassword($value);
            if ($this->passwordOption($value) !== null) {
                throw new InvalidArgumentException("The option $value takes a password: pass it as \"'$value' => 'password'\".");
            }
            $remaining[] = $value;
        }
        return $remaining;
    }

    private function rejectTheCertificatePassword(string $parameter): void
    {
        if (isset(self::CERTIFICATE_PASSWORD_OPTIONS[$parameter])
            || in_array($parameter, self::CERTIFICATE_PASSWORD_OPTIONS, true)
        ) {
            throw new InvalidArgumentException("The password of $parameter is set with setPassword().");
        }
    }

    /**
     * JSignPdf reads one password per line from stdin, so a password with a
     * line break would be read as the password of the next option.
     */
    private function singleLinePassword(string $option, string $password): string
    {
        if (preg_match('/[\r\n]/', $password) === 1) {
            throw new InvalidArgumentException("The password of $option cannot contain a line break.");
        }
        return $password;
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

    public function setJavaDownloadUrl(string $url, string $sha256 = ''): self
    {
        $this->javaDownloadUrl = $url;
        $this->javaDownloadSha256 = $sha256 === '' && $url === self::JAVA_DOWNLOAD_URL
            ? self::JAVA_SHA256
            : $this->validSha256($sha256);
        return $this;
    }

    public function getJavaDownloadSha256(): string
    {
        return $this->javaDownloadSha256;
    }

    public function getJavaDownloadUrl(): string
    {
        return $this->javaDownloadUrl;
    }

    public function setJSignPdfDownloadUrl(string $url, string $sha256 = ''): self
    {
        $this->jSignPdfDownloadUrl = $url;
        $this->jSignPdfDownloadSha256 = $sha256 === '' && $url === self::buildJSignPdfDownloadUrl()
            ? self::JSIGNPDF_SHA256
            : $this->validSha256($sha256);
        return $this;
    }

    public function getJSignPdfDownloadSha256(): string
    {
        return $this->jSignPdfDownloadSha256;
    }

    private function validSha256(string $sha256): string
    {
        if ($sha256 !== '' && preg_match('/^[0-9a-f]{64}$/i', $sha256) !== 1) {
            throw new InvalidArgumentException('The SHA-256 must have 64 hexadecimal characters.');
        }
        return strtolower($sha256);
    }

    public function getJSignPdfDownloadUrl(): string
    {
        return $this->jSignPdfDownloadUrl;
    }

    public function setSignatureField(?string $fieldName): self
    {
        $this->signatureField = $fieldName;
        return $this;
    }

    public function getSignatureField(): ?string
    {
        return $this->signatureField;
    }
}
