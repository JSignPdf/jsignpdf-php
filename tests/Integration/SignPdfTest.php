<?php

namespace Jeidison\JSignPDF\Tests\Integration;

use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
class SignPdfTest extends TestCase
{
    private const PASSWORD = '123';

    private function params(): JSignParam
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $csr = openssl_csr_new(['commonName' => 'Jhon Doe'], $privateKey, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $privateKey, 365);
        openssl_pkcs12_export($x509, $certificate, $privateKey, self::PASSWORD);

        $params = JSignParam::instance();
        $params->setCertificate($certificate);
        $params->setPdf(file_get_contents(__DIR__ . '/../resources/pdf-test.pdf'));
        $params->setPassword(self::PASSWORD);
        return $params;
    }

    public function testGetVersionReturnsTheInstalledJSignPdf(): void
    {
        $version = JSignPDF::instance($this->params())->getVersion();
        $this->assertMatchesRegularExpression('/^3\.\d+\.\d+/', $version);
    }

    public function testSignProducesASignedPdf(): void
    {
        $params = $this->params();
        $params->setJSignParameters(['-kst', 'PKCS12', '--overwrite']);

        $signed = JSignPDF::instance($params)->sign();

        $this->assertStringStartsWith('%PDF-', $signed);
        $this->assertStringContainsString('/ByteRange', $signed);
        $this->assertStringContainsString('adbe.pkcs7', $signed);
    }

    public function testSignWithAVisibleSignature(): void
    {
        $params = $this->params();
        $params->setJSignParameters([
            '-kst', 'PKCS12',
            '--overwrite',
            '-V',
            '-pg', '1',
            '-llx', '50', '-lly', '50', '-urx', '300', '-ury', '150',
        ]);

        $signed = JSignPDF::instance($params)->sign();

        $this->assertStringContainsString('/ByteRange', $signed);
    }

    public function testSignAPdfOlderThan16WithTheDefaultParameters(): void
    {
        $this->expectExceptionMessageMatches('/Creating of signature failed/');
        JSignPDF::instance($this->params())->sign();
    }
}
