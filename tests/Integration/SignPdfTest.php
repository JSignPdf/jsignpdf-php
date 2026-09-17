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
        $params->setJSignParameters(['-kst' => 'PKCS12', '--overwrite']);

        $signed = JSignPDF::instance($params)->sign();

        $this->assertStringStartsWith('%PDF-', $signed);
        $this->assertStringContainsString('/ByteRange', $signed);
        $this->assertStringContainsString('adbe.pkcs7', $signed);
    }

    public function testSignSendingMoreThanOnePasswordThroughStdin(): void
    {
        $params = $this->params();
        $params->setJSignParameters(['-kst' => 'PKCS12', '--overwrite']);
        $params->setKeyPassword(self::PASSWORD);

        $signed = JSignPDF::instance($params)->sign();

        $this->assertStringContainsString('/ByteRange', $signed);
    }

    public function testSignWithAVisibleSignature(): void
    {
        $params = $this->params();
        $params->setJSignParameters([
            '-kst' => 'PKCS12',
            '--overwrite',
            '-V',
            '-pg' => '1',
            '-llx' => '50',
            '-lly' => '50',
            '-urx' => '300',
            '-ury' => '150',
        ]);

        $signed = JSignPDF::instance($params)->sign();

        $this->assertStringContainsString('/ByteRange', $signed);
    }

    public function testSignWithAnExplicitHashAlgorithm(): void
    {
        $params = $this->params();
        $params->setJSignParameters(['-kst' => 'PKCS12', '--overwrite', '-ha' => 'SHA512']);

        $signed = JSignPDF::instance($params)->sign();

        $this->assertStringContainsString('/ByteRange', $signed);
    }

    public function testSignAPdfOlderThan16WithTheDefaultParameters(): void
    {
        $signed = JSignPDF::instance($this->params())->sign();

        $this->assertStringStartsWith('%PDF-', $signed);
        $this->assertStringContainsString('/ByteRange', $signed);
    }

    public function testGetSignatureFieldsReturnsEmptyListWhenPdfHasNoSignatureFields(): void
    {
        $fields = JSignPDF::instance(
            $this->inspectionParams()
        )->getSignatureFields();

        $this->assertSame([], $fields);
    }

    private function inspectionParams(string $file = 'pdf-test.pdf'): JSignParam
    {
        $params = JSignParam::instance();
        $params->setPdf(file_get_contents(__DIR__ . '/../resources/' . $file));
        $params->setEnvironmentVariables([
            'HOME' => '/tmp',
            'XDG_CONFIG_HOME' => '/tmp/.config',
            'LANG' => 'C.UTF-8',
            'LC_ALL' => 'C.UTF-8',
        ]);

        return $params;
    }

    public function testGetSignatureFieldsReturnsFieldsReportedByJSignPdf(): void
    {
        $fields = JSignPDF::instance(
            $this->inspectionParams('signature-fields.pdf')
        )->getSignatureFields();

        $this->assertCount(8, $fields);

        $this->assertSame('Customer Signature', $fields[0]->getName());
        $this->assertSame(1, $fields[0]->getPage());
        $this->assertSame(70.0, $fields[0]->getLlx());
        $this->assertSame(700.0, $fields[0]->getLly());
        $this->assertSame(300.0, $fields[0]->getUrx());
        $this->assertSame(760.0, $fields[0]->getUry());
        $this->assertTrue($fields[0]->isBlank());
        $this->assertFalse($fields[0]->isHidden());
        $this->assertTrue($fields[0]->hasVisibleRectangle());

        $this->assertSame('Invisible', $fields[1]->getName());
        $this->assertSame(1, $fields[1]->getPage());
        $this->assertSame(0.0, $fields[1]->getLlx());
        $this->assertSame(0.0, $fields[1]->getLly());
        $this->assertSame(0.0, $fields[1]->getUrx());
        $this->assertSame(0.0, $fields[1]->getUry());
        $this->assertTrue($fields[1]->isBlank());
        $this->assertFalse($fields[1]->hasVisibleRectangle());

        $this->assertSame('Hidden', $fields[2]->getName());
        $this->assertTrue($fields[2]->isBlank());
        $this->assertTrue($fields[2]->isHidden());

        $this->assertSame('auto', $fields[3]->getName());
        $this->assertTrue($fields[3]->isBlank());

        $this->assertSame('#1', $fields[4]->getName());
        $this->assertTrue($fields[4]->isBlank());

        $this->assertSame(
            'This signature field name is much longer than thirty characters',
            $fields[5]->getName()
        );
        $this->assertTrue($fields[5]->isBlank());

        $this->assertSame('Podpis zákazníka', $fields[6]->getName());
        $this->assertSame(2, $fields[6]->getPage());
        $this->assertTrue($fields[6]->isBlank());
        $this->assertFalse($fields[6]->isSigned());

        $this->assertSame('Already Signed', $fields[7]->getName());
        $this->assertSame(2, $fields[7]->getPage());
        $this->assertFalse($fields[7]->isBlank());
        $this->assertTrue($fields[7]->isSigned());
    }

    public function testSignsIntoExistingSignatureFieldByName(): void
    {
        $params = $this->params();
        $params->setPdf(
            file_get_contents(__DIR__ . '/../resources/signature-fields.pdf')
        );
        $params->setSignatureField('Customer Signature');
        $params->setEnvironmentVariables([
            'HOME' => '/tmp',
            'XDG_CONFIG_HOME' => '/tmp/.config',
            'LANG' => 'C.UTF-8',
            'LC_ALL' => 'C.UTF-8',
        ]);

        $signedPdf = JSignPDF::instance($params)->sign();

        $inspectionParams = $this->inspectionParams();
        $inspectionParams->setPdf($signedPdf);

        $fields = JSignPDF::instance($inspectionParams)->getSignatureFields();

        $customerSignature = array_values(
            array_filter(
                $fields,
                static fn ($field): bool =>
                $field->getName() === 'Customer Signature'
            )
        );

        $this->assertCount(1, $customerSignature);
        $this->assertTrue($customerSignature[0]->isSigned());
        $this->assertFalse($customerSignature[0]->isBlank());
    }

    public function testSignsIntoExistingUnicodeSignatureField(): void
    {
        $params = $this->params();
        $params->setPdf(
            file_get_contents(__DIR__ . '/../resources/signature-fields.pdf')
        );
        $params->setSignatureField('Podpis zákazníka');
        $params->setEnvironmentVariables([
            'HOME' => '/tmp',
            'XDG_CONFIG_HOME' => '/tmp/.config',
            'LANG' => 'C.UTF-8',
            'LC_ALL' => 'C.UTF-8',
        ]);

        $signedPdf = JSignPDF::instance($params)->sign();

        $inspectionParams = $this->inspectionParams();
        $inspectionParams->setPdf($signedPdf);

        $fields = JSignPDF::instance($inspectionParams)->getSignatureFields();

        $unicodeField = array_values(
            array_filter(
                $fields,
                static fn ($field): bool =>
                $field->getName() === 'Podpis zákazníka'
            )
        );

        $this->assertCount(1, $unicodeField);
        $this->assertTrue($unicodeField[0]->isSigned());
    }

    public function testSignsIntoFieldLiterallyNamedAuto(): void
    {
        $params = $this->params();
        $params->setPdf(
            file_get_contents(__DIR__ . '/../resources/signature-fields.pdf')
        );
        $params->setSignatureField('auto');
        $params->setEnvironmentVariables([
            'HOME' => '/tmp',
            'XDG_CONFIG_HOME' => '/tmp/.config',
            'LANG' => 'C.UTF-8',
            'LC_ALL' => 'C.UTF-8',
        ]);

        $signedPdf = JSignPDF::instance($params)->sign();

        $inspectionParams = $this->inspectionParams();
        $inspectionParams->setPdf($signedPdf);

        $fields = JSignPDF::instance($inspectionParams)->getSignatureFields();

        $field = array_values(
            array_filter(
                $fields,
                static fn ($field): bool => $field->getName() === 'auto'
            )
        );

        $this->assertCount(1, $field);
        $this->assertTrue($field[0]->isSigned());
    }

    public function testSignsIntoFieldLiterallyNamedNumberSelector(): void
    {
        $params = $this->params();
        $params->setPdf(
            file_get_contents(__DIR__ . '/../resources/signature-fields.pdf')
        );
        $params->setSignatureField('#1');
        $params->setEnvironmentVariables([
            'HOME' => '/tmp',
            'XDG_CONFIG_HOME' => '/tmp/.config',
            'LANG' => 'C.UTF-8',
            'LC_ALL' => 'C.UTF-8',
        ]);

        $signedPdf = JSignPDF::instance($params)->sign();

        $inspectionParams = $this->inspectionParams();
        $inspectionParams->setPdf($signedPdf);

        $fields = JSignPDF::instance($inspectionParams)->getSignatureFields();

        $field = array_values(
            array_filter(
                $fields,
                static fn ($field): bool => $field->getName() === '#1'
            )
        );

        $this->assertCount(1, $field);
        $this->assertTrue($field[0]->isSigned());
    }

    public function testSigningAlreadySignedFieldPreservesJSignPdfFailure(): void
    {
        $params = $this->params();
        $params->setPdf(
            file_get_contents(__DIR__ . '/../resources/signature-fields.pdf')
        );
        $params->setSignatureField('Already Signed');
        $params->setEnvironmentVariables([
            'HOME' => '/tmp',
            'XDG_CONFIG_HOME' => '/tmp/.config',
            'LANG' => 'C.UTF-8',
            'LC_ALL' => 'C.UTF-8',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "The signature field 'Already Signed' is already signed, choose a blank one."
        );

        JSignPDF::instance($params)->sign();
    }

    public function testSigningMissingFieldPreservesJSignPdfFailure(): void
    {
        $params = $this->params();
        $params->setPdf(
            file_get_contents(__DIR__ . '/../resources/signature-fields.pdf')
        );
        $params->setSignatureField('Field That Does Not Exist');
        $params->setEnvironmentVariables([
            'HOME' => '/tmp',
            'XDG_CONFIG_HOME' => '/tmp/.config',
            'LANG' => 'C.UTF-8',
            'LC_ALL' => 'C.UTF-8',
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            "No signature field matches 'Field That Does Not Exist'."
        );

        JSignPDF::instance($params)->sign();
    }

    public function testGetSignatureFieldsRejectsInvalidPdf(): void
    {
        $params = $this->inspectionParams();
        $params->setPdf('not a pdf');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Can not read the signature fields');

        JSignPDF::instance($params)->getSignatureFields();
    }
}
