<?php

namespace Jeidison\JSignPDF\Sign;

function exec(string $command, ?array &$output = null, ?int &$return_var = null) {
    global $mockExec;
    if ($mockExec) {
        $output = $mockExec;
        return $output;
    }
    return \exec($command, $output, $return_var);
}

namespace Jeidison\JSignPDF\Tests;

use org\bovigo\vfs\vfsStream;
use Exception;
use Jeidison\JSignPDF\Sign\JSignService;
use Jeidison\JSignPDF\Tests\Builder\JSignParamBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 */
class JSignPDFTest extends TestCase
{
    private JSignService $service;

    protected function setUp(): void
    {
        global $mockExec;
        $mockExec = null;
        $this->service = new JSignService();
    }

    private function getNewCert($password)
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $csrNames = ['commonName' => 'Jhon Doe'];

        $csr = openssl_csr_new($csrNames, $privateKey, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $privateKey, $days = 365, ['digest_alg' => 'sha256']);

        openssl_x509_export($x509, $rootCertificate);
        openssl_pkey_export($privateKey, $rootPrivateKey);

        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'cfg');
        $csr = openssl_csr_new($csrNames, $privateKey);
        $x509 = openssl_csr_sign($csr, $rootCertificate, $rootPrivateKey, 365);
        $certContent = null;
        openssl_pkcs12_export(
            $x509,
            $certContent,
            $privateKey,
            $password,
        );
        return $certContent;
    }

    public function testSignSuccess()
    {
        if (!class_exists('JSignPDF\JSignPDFBin\JavaCommandService')) {
            $this->markTestSkipped('Install jsignpdf/jsignpdf-bin');
        }
        $params = JSignParamBuilder::instance()->withDefault();
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $fileSigned = $this->service->sign($params);
        $this->assertNotNull($fileSigned);
    }

    #[DataProvider('providerSignUsingDifferentPasswords')]
    public function testSignUsingDifferentPasswords(string $password): void
    {
        global $mockExec;
        if (!class_exists('JSignPDF\JSignPDFBin\JavaCommandService')) {
            $this->markTestSkipped('Install jsignpdf/jsignpdf-bin');
        }
        $params = JSignParamBuilder::instance()->withDefault();
        $params->setCertificate($this->getNewCert($password));
        $params->setPassword($password);
        $mockExec = 'Finished: Signature succesfully created.';
        $path = $params->getTempPdfSignedPath();
        file_put_contents($path, 'dummy');
        $fileSigned = $this->service->sign($params);
        $this->assertNotNull($fileSigned);
    }

    public static function providerSignUsingDifferentPasswords(): array
    {
        return [
            ["with ' quote"],
            ['with ( parentheis )'],
            ['with $ dollar'],
            ['with 😃 unicode'],
        ];
    }

    public function testCertificateExpired()
    {
        if (!class_exists('JSignPDF\JSignPDFBin\JavaCommandService')) {
            $this->markTestSkipped('Install jsignpdf/jsignpdf-bin');
        }
        $this->expectExceptionMessage('Certificate expired.');
        $params = JSignParamBuilder::instance()->withDefault();
        $fileSigned = $this->service->sign($params);
        $this->assertNotNull($fileSigned);
    }

    public function testSignError()
    {
        $this->expectException(Exception::class);
        $params = JSignParamBuilder::instance();
        $this->service->sign($params->getParams());
    }


    public function testWithWhenResponseIsBase64()
    {
        if (!class_exists('JSignPDF\JSignPDFBin\JavaCommandService')) {
            $this->markTestSkipped('Install jsignpdf/jsignpdf-bin');
        }
        $params = JSignParamBuilder::instance()->withDefault()->setIsOutputTypeBase64(true);
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $fileSigned = $this->service->sign($params);
        $this->assertTrue(base64_decode($fileSigned, true) == true);
    }

    public function testSignWhenCertificateIsNull()
    {
        $this->expectExceptionMessage('Certificate is Empty or Invalid.');
        $params = JSignParamBuilder::instance()->withDefault()->setCertificate(null);
        $this->service->sign($params);
    }

    public function testSignWhenPdfIsNull()
    {
        $this->expectExceptionMessage('PDF is Empty or Invalid.');
        $params = JSignParamBuilder::instance()->withDefault()->setPdf(null);
        $this->service->sign($params);
    }

    public function testSignWhenPasswordIsNull()
    {
        $this->expectExceptionMessage('Certificate Password is Empty.');
        $params = JSignParamBuilder::instance()->withDefault()->setPassword(null);
        $this->service->sign($params);
    }

    public function testSignWhenTempPathIsInvalid()
    {
        $this->expectExceptionMessage('Temp Path is invalid or has not permission to writable.');
        $params = JSignParamBuilder::instance()->withDefault()->setTempPath(null);
        $this->service->sign($params);
    }

    public function testSignWhenPasswordIsInvalid()
    {
        $this->expectExceptionMessage('Certificate Password Invalid.');
        $params = JSignParamBuilder::instance()->withDefault()->setPassword('123456');
        $this->service->sign($params);
    }

    public function testJSignPDFNotFound()
    {
        $this->expectExceptionMessageMatches('/JSignPDF not found/');
        $params = JSignParamBuilder::instance()->withDefault();
        $params->setJSignPdfDownloadUrl('');
        $params->setjSignPdfJarPath('invalid_path');
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setIsUseJavaInstalled(true);
        $this->service->getVersion($params);
    }

    public function testSignWhenJavaNotFound()
    {
        $javaVersion    = exec("java -version 2>&1");
        $hasJavaVersion = strpos($javaVersion, 'not found') === false;
        if ($hasJavaVersion) {
            $this->markTestSkipped('Java is already installed, impossible to test if it is not installed');
        }
        $this->expectExceptionMessage('Java not installed, set the flag "isUseJavaInstalled" as false or install java.');
        $params = JSignParamBuilder::instance()->withDefault()->setIsUseJavaInstalled(true);
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $this->service->sign($params);
    }

    public function testGetVersion()
    {
        global $mockExec;
        $mockExec = ['JSignPdf version 2.3.0'];

        $params = JSignParamBuilder::instance()->withDefault();
        vfsStream::setup('download');
        mkdir('vfs://download/bin');
        touch('vfs://download/bin/java');
        chmod('vfs://download/bin/java', 0755);
        $params->setJavaPath('vfs://download/bin/java');
        $params->getJSignPdfDownloadUrl('fake_url');
        $params->setIsUseJavaInstalled(true);
        $params->setjSignPdfJarPath('faje_path');
        $version = $this->service->getVersion($params);
        $this->assertNotEmpty($version);
    }
}
