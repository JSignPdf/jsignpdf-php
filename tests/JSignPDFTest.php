<?php

namespace Jeidison\JSignPDF\Sign;

function exec(string $command, ?array &$output = null, ?int &$return_var = null)
{
    global $mockExec;
    if ($mockExec) {
        $output = $mockExec;
        return $output;
    }
    return \exec($command, $output, $return_var);
}

function proc_open(string $command, array $descriptor_spec, ?array &$pipes)
{
    global $mockExec, $mockProcCommand, $mockProcStdinFile;
    if (!$mockExec) {
        return \proc_open($command, $descriptor_spec, $pipes);
    }
    $mockProcCommand = $command;
    $mockProcStdinFile = tempnam(sys_get_temp_dir(), 'jsignpdf_stdin_');
    $stdout = fopen('php://memory', 'w+');
    fwrite($stdout, implode(PHP_EOL, $mockExec));
    rewind($stdout);
    $pipes = [fopen($mockProcStdinFile, 'w'), $stdout];
    return $stdout;
}

function proc_close($process)
{
    global $mockExec;
    return $mockExec ? 0 : \proc_close($process);
}

namespace Jeidison\JSignPDF\Tests;

use org\bovigo\vfs\vfsStream;
use Exception;
use Jeidison\JSignPDF\Sign\JSignParam;
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
        global $mockExec, $mockProcCommand, $mockProcStdinFile;
        $mockExec = null;
        $mockProcCommand = null;
        $mockProcStdinFile = null;
        $this->service = new JSignService();
    }

    protected function tearDown(): void
    {
        global $mockProcStdinFile;
        if ($mockProcStdinFile && is_file($mockProcStdinFile)) {
            unlink($mockProcStdinFile);
        }
    }

    private function withFakeRuntime(): JSignParam
    {
        $params = JSignParamBuilder::instance()->withDefault();
        vfsStream::setup('download');
        mkdir('vfs://download/jvava/bin', 0755, true);
        touch('vfs://download/jvava/bin/java');
        chmod('vfs://download/jvava/bin/java', 0755);
        $params->setJavaPath('vfs://download/jvava/bin/java');
        $params->setJavaDownloadUrl('');
        mkdir('vfs://download/jsignpdf', 0755, true);
        touch('vfs://download/jsignpdf/JSignPdf.jar');
        $params->setJSignPdfPath('vfs://download/jsignpdf');
        $params->setJSignPdfDownloadUrl('');
        return $params;
    }

    private function getNewCert($password, $expireDays = 365)
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $csrNames = ['commonName' => 'Jhon Doe'];

        $csr = openssl_csr_new($csrNames, $privateKey, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, null, $privateKey, $expireDays);

        openssl_pkcs12_export(
            $x509,
            $pfxCertificateContent,
            $privateKey,
            $password,
        );
        return $pfxCertificateContent;
    }

    public function testSignSuccess()
    {
        global $mockExec;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = JSignParamBuilder::instance()->withDefault();
        vfsStream::setup('download');
        mkdir('vfs://download/jvava/bin', 0755, true);
        touch('vfs://download/jvava/bin/java');
        chmod('vfs://download/jvava/bin/java', 0755);
        $params->setJavaPath('vfs://download/jvava/bin/java');
        $params->setJavaDownloadUrl('');
        mkdir('vfs://download/jsignpdf', 0755, true);
        touch('vfs://download/jsignpdf/JSignPdf.jar');
        $params->setJSignPdfPath('vfs://download/jsignpdf');
        $params->setJSignPdfDownloadUrl('');
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');
        $signedFilePath = $params->getTempPdfSignedPath();
        file_put_contents($signedFilePath, 'signed file content');
        $fileSignedContent = $this->service->sign($params);
        $this->assertEquals('signed file content', $fileSignedContent);
    }

    #[DataProvider('providerSignUsingDifferentPasswords')]
    public function testSignUsingDifferentPasswords(string $password): void
    {
        global $mockExec;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = JSignParamBuilder::instance()->withDefault();
        vfsStream::setup('download');
        mkdir('vfs://download/jvava/bin', 0755, true);
        touch('vfs://download/jvava/bin/java');
        chmod('vfs://download/jvava/bin/java', 0755);
        $params->setJavaPath('vfs://download/jvava/bin/java');
        $params->setJavaDownloadUrl('');
        mkdir('vfs://download/jsignpdf', 0755, true);
        touch('vfs://download/jsignpdf/JSignPdf.jar');
        $params->setJSignPdfPath('vfs://download/jsignpdf');
        $params->setJSignPdfDownloadUrl('');
        $params->setCertificate($this->getNewCert($password));
        $params->setPassword($password);
        $params->setPathPdfSigned('vfs://download/temp');
        $signedFilePath = $params->getTempPdfSignedPath();
        file_put_contents($signedFilePath, 'signed file content');
        $fileSignedContent = $this->service->sign($params);
        $this->assertEquals('signed file content', $fileSignedContent);
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
        $this->expectExceptionMessage('Certificate expired.');
        $params = JSignParamBuilder::instance()->withDefault();
        vfsStream::setup('download');
        mkdir('vfs://download/jvava/bin', 0755, true);
        touch('vfs://download/jvava/bin/java');
        chmod('vfs://download/jvava/bin/java', 0755);
        $params->setJavaPath('vfs://download/jvava/bin/java');
        $params->setJavaDownloadUrl('');
        mkdir('vfs://download/jsignpdf', 0755, true);
        touch('vfs://download/jsignpdf/JSignPdf.jar');
        $params->setJSignPdfPath('vfs://download/jsignpdf');
        $params->setJSignPdfDownloadUrl('');
        $params->setCertificate($this->getNewCert('123', 0));
        $params->setPassword('123');
        $signedFilePath = $params->getTempPdfSignedPath();
        file_put_contents($signedFilePath, 'signed file content');
        $this->service->sign($params);
    }

    public function testSignError()
    {
        $this->expectException(Exception::class);
        $params = JSignParamBuilder::instance();
        $this->service->sign($params->getParams());
    }


    public function testWithWhenResponseIsBase64()
    {
        global $mockExec;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = JSignParamBuilder::instance()->withDefault();
        vfsStream::setup('download');
        mkdir('vfs://download/jvava/bin', 0755, true);
        touch('vfs://download/jvava/bin/java');
        chmod('vfs://download/jvava/bin/java', 0755);
        $params->setJavaPath('vfs://download/jvava/bin/java');
        $params->setJavaDownloadUrl('');
        mkdir('vfs://download/jsignpdf', 0755, true);
        touch('vfs://download/jsignpdf/JSignPdf.jar');
        $params->setJSignPdfPath('vfs://download/jsignpdf');
        $params->setJSignPdfDownloadUrl('');
        $params->setCertificate($this->getNewCert('123'));
        $params->setPassword('123');
        $signedFilePath = $params->getTempPdfSignedPath();
        file_put_contents($signedFilePath, 'signed file content');
        $params->setIsOutputTypeBase64(true);
        $signedContent = $this->service->sign($params);
        $this->assertEquals(base64_encode('signed file content'), $signedContent);
    }

    public function testSignWhenCertificateIsEmpty()
    {
        $this->expectExceptionMessage('Certificate is Empty or Invalid.');
        $params = JSignParamBuilder::instance()->withDefault()->setCertificate('');
        $this->service->sign($params);
    }

    public function testSignWhenPdfIsEmpty()
    {
        $this->expectExceptionMessage('PDF is Empty or Invalid.');
        $params = JSignParamBuilder::instance()->withDefault()->setPdf('');
        $this->service->sign($params);
    }

    public function testSignWhenPasswordIsEmpty()
    {
        $this->expectExceptionMessage('Certificate Password is Empty.');
        $params = JSignParamBuilder::instance()->withDefault()->setPassword('');
        $this->service->sign($params);
    }

    public function testSignWhenTempPathIsInvalid()
    {
        $this->expectExceptionMessage('Temp Path is invalid or has not permission to writable.');
        $params = JSignParamBuilder::instance()->withDefault()->setTempPath('');
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
        $params->setJSignPdfPath('invalid_path');
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setIsUseJavaInstalled(true);
        $this->service->getVersion($params);
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
        mkdir('vfs://download/jsignpdf_fake_path/');
        touch('vfs://download/jsignpdf_fake_path/JSignPdf.jar');
        touch('vfs://download/jsignpdf_fake_path/.jsignpdf_version_fake_url');
        $params->setJavaPath('vfs://download/bin/java');
        $params->setJSignPdfDownloadUrl('fake_url');
        $params->setIsUseJavaInstalled(true);
        $params->setJSignPdfPath('vfs://download/jsignpdf_fake_path');
        $version = $this->service->getVersion($params);
        $this->assertNotEmpty($version);
    }

    public function testGetVersionOfJSignPdf3(): void
    {
        global $mockExec;
        $mockExec = ['JSignPdf version 3.1.0'];

        $params = JSignParamBuilder::instance()->withDefault();
        vfsStream::setup('download');
        mkdir('vfs://download/bin');
        touch('vfs://download/bin/java');
        chmod('vfs://download/bin/java', 0755);
        mkdir('vfs://download/jsignpdf_fake_path/');
        touch('vfs://download/jsignpdf_fake_path/JSignPdf.jar');
        touch('vfs://download/jsignpdf_fake_path/.jsignpdf_version_fake_url');
        $params->setJavaPath('vfs://download/bin/java');
        $params->setJSignPdfDownloadUrl('fake_url');
        $params->setIsUseJavaInstalled(true);
        $params->setJSignPdfPath('vfs://download/jsignpdf_fake_path');
        $version = $this->service->getVersion($params);
        $this->assertEquals('3.1.0', $version);
    }

    public function testSignWhenJSignPdfReportsAFailure(): void
    {
        global $mockExec;
        $mockExec = [
            'INFO Creating signature',
            'INFO Finished: Creating of signature failed.',
        ];
        $params = $this->withFakeRuntime();
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');

        $this->expectExceptionMessageMatches('/Creating of signature failed/');
        $this->service->sign($params);
    }

    public function testSignSendsThePasswordThroughStdinAndNotThroughArgv(): void
    {
        global $mockExec, $mockProcCommand, $mockProcStdinFile;
        $mockExec = ['Finished: Signature succesfully created.'];
        $password = 'with space $and `backtick` and ; semicolon';
        $params = $this->withFakeRuntime();
        $params->setCertificate($this->getNewCert($password));
        $params->setPassword($password);
        $params->setPathPdfSigned('vfs://download/temp');
        file_put_contents($params->getTempPdfSignedPath(), 'signed file content');

        $this->service->sign($params);

        $this->assertStringNotContainsString($password, $mockProcCommand);
        $this->assertStringContainsString('--enable-stdin-passwords -ksp -', $mockProcCommand);
        $this->assertEquals($password . PHP_EOL, file_get_contents($mockProcStdinFile));
    }

    public function testSignEscapesEveryPathOfTheCommand(): void
    {
        global $mockExec, $mockProcCommand;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        mkdir("vfs://download/temp dir with 'quote'", 0755, true);
        $params->setTempPath("vfs://download/temp dir with 'quote'/");
        $params->setCertificate($this->getNewCert($params->getPassword()));
        file_put_contents($params->getTempPdfSignedPath(), 'signed file content');

        $this->service->sign($params);

        $this->assertStringContainsString(escapeshellarg('vfs://download/jvava/bin/java'), $mockProcCommand);
        $this->assertStringContainsString(escapeshellarg($params->getTempPdfPath()), $mockProcCommand);
        $this->assertStringContainsString('-ksf ' . escapeshellarg($params->getTempCertificatePath()), $mockProcCommand);
        $this->assertStringContainsString('-d ' . escapeshellarg($params->getPathPdfSigned()), $mockProcCommand);
    }

    public function testSignUsesTheFatJarWhenTheDistributionShipsOne(): void
    {
        global $mockExec, $mockProcCommand;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');
        file_put_contents($params->getTempPdfSignedPath(), 'signed file content');

        $this->service->sign($params);

        $this->assertStringContainsString('-jar ' . escapeshellarg('vfs://download/jsignpdf/JSignPdf.jar'), $mockProcCommand);
    }

    public function testSignPrefersTheClasspathOverALeftoverFatJar(): void
    {
        global $mockExec, $mockProcCommand;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        mkdir('vfs://download/jsignpdf/lib', 0755, true);
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');
        file_put_contents($params->getTempPdfSignedPath(), 'signed file content');

        $this->service->sign($params);

        $this->assertStringContainsString(
            '-classpath ' . escapeshellarg('vfs://download/jsignpdf/lib/*'),
            $mockProcCommand
        );
        $this->assertStringNotContainsString('-jar ', $mockProcCommand);
    }

    public function testSignEscapesOptionValuesGivenAsAList(): void
    {
        global $mockExec, $mockProcCommand;
        $mockExec = ['Finished: Signature succesfully created.'];
        $options = [
            '-kst',
            'PKCS12',
            '-ts',
            'https://tsa.example/tsr?first=1&second=2',
            '-o',
            "reason with space, ' quote and ; semicolon",
        ];
        $params = $this->withFakeRuntime();
        $params->setJSignParameters($options);
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');
        file_put_contents($params->getTempPdfSignedPath(), 'signed file content');

        $this->service->sign($params);

        $this->assertStringContainsString(
            implode(' ', array_map('escapeshellarg', $options)),
            $mockProcCommand
        );
    }

    public function testAddJSignParametersAppendsToTheDefaultOptions(): void
    {
        global $mockExec, $mockProcCommand;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        $params->addJSignParameters(['-ha', 'SHA512']);
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');
        file_put_contents($params->getTempPdfSignedPath(), 'signed file content');

        $this->service->sign($params);

        $this->assertStringContainsString(
            implode(' ', array_map('escapeshellarg', ['-a', '-kst', 'PKCS12', '-ha', 'SHA512'])),
            $mockProcCommand
        );
    }

    public function testSignUsesTheClasspathWhenTheDistributionHasNoFatJar(): void
    {
        global $mockExec, $mockProcCommand;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        unlink('vfs://download/jsignpdf/JSignPdf.jar');
        mkdir('vfs://download/jsignpdf/lib', 0755, true);
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');
        file_put_contents($params->getTempPdfSignedPath(), 'signed file content');

        $this->service->sign($params);

        $this->assertStringContainsString(
            '-classpath ' . escapeshellarg('vfs://download/jsignpdf/lib/*') . ' com.intoolswetrust.jsignpdf.Bootstrap',
            $mockProcCommand
        );
    }

    private function signWithFakeRuntime(JSignParam $params): void
    {
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');
        file_put_contents($params->getTempPdfSignedPath(), 'signed file content');

        $this->service->sign($params);
    }

    public function testSignSendsEveryPasswordThroughStdinInTheOrderJSignPdfReadsThem(): void
    {
        global $mockExec, $mockProcCommand, $mockProcStdinFile;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        $params->setTsaPassword('tsa secret');
        $params->setKeyPassword('key secret');
        $params->setUserPassword('user secret');
        $params->setTsaCertPassword('tsa cert secret');
        $params->setOwnerPassword('owner secret');

        $this->signWithFakeRuntime($params);

        $this->assertStringContainsString(
            '--enable-stdin-passwords -ksp - -kp - -opwd - -upwd - -tscp - -tsp - ',
            $mockProcCommand
        );
        $this->assertEquals(
            implode(PHP_EOL, [
                $params->getPassword(),
                'key secret',
                'owner secret',
                'user secret',
                'tsa cert secret',
                'tsa secret',
            ]) . PHP_EOL,
            file_get_contents($mockProcStdinFile)
        );
        foreach (['tsa secret', 'key secret', 'user secret', 'tsa cert secret', 'owner secret'] as $password) {
            $this->assertStringNotContainsString($password, $mockProcCommand);
        }
    }

    public function testSignKeepsThePasswordsOfTheOptionListOutOfArgv(): void
    {
        global $mockExec, $mockProcCommand, $mockProcStdinFile;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        $params->setJSignParameters([
            '-kst', 'PKCS12',
            '--overwrite',
            '-ts', 'https://tsa.example/tsr',
            '-ta', 'PASSWORD',
            '-tsu', 'jhon',
            '-tsp', 'tsa secret',
        ]);

        $this->signWithFakeRuntime($params);

        $this->assertStringContainsString('--enable-stdin-passwords -ksp - -tsp - ', $mockProcCommand);
        $this->assertStringNotContainsString('tsa secret', $mockProcCommand);
        $this->assertStringContainsString(
            implode(' ', array_map('escapeshellarg', ['-kst', 'PKCS12', '--overwrite', '-ts', 'https://tsa.example/tsr', '-ta', 'PASSWORD', '-tsu', 'jhon'])),
            $mockProcCommand
        );
        $this->assertEquals(
            $params->getPassword() . PHP_EOL . 'tsa secret' . PHP_EOL,
            file_get_contents($mockProcStdinFile)
        );
    }

    #[DataProvider('providerPasswordOptionSpellings')]
    public function testSignKeepsThePasswordOutOfArgvForEverySpellingOfTheOption(array $parameters): void
    {
        global $mockExec, $mockProcCommand, $mockProcStdinFile;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        $params->setJSignParameters($parameters);

        $this->signWithFakeRuntime($params);

        $this->assertStringContainsString('--enable-stdin-passwords -ksp - -tsp - ', $mockProcCommand);
        $this->assertStringNotContainsString('tsa secret', $mockProcCommand);
        $this->assertEquals(
            $params->getPassword() . PHP_EOL . 'tsa secret' . PHP_EOL,
            file_get_contents($mockProcStdinFile)
        );
    }

    public static function providerPasswordOptionSpellings(): array
    {
        return [
            'short option' => [['-tsp', 'tsa secret']],
            'long option' => [['--tsa-password', 'tsa secret']],
            'short option with assignment' => [['-tsp=tsa secret']],
            'long option with assignment' => [['--tsa-password=tsa secret']],
        ];
    }

    public function testSignPrefersThePasswordOfTheSetterOverTheOneOfTheOptionList(): void
    {
        global $mockExec, $mockProcStdinFile;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        $params->setJSignParameters(['-tsp', 'from the list']);
        $params->setTsaPassword('from the setter');

        $this->signWithFakeRuntime($params);

        $this->assertEquals(
            $params->getPassword() . PHP_EOL . 'from the setter' . PHP_EOL,
            file_get_contents($mockProcStdinFile)
        );
    }

    public function testSignForgetsThePasswordsOfAReplacedOptionList(): void
    {
        global $mockExec, $mockProcCommand, $mockProcStdinFile;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        $params->setJSignParameters(['-tsp', 'tsa secret']);
        $params->setJSignParameters(['-kst', 'PKCS12']);

        $this->signWithFakeRuntime($params);

        $this->assertStringNotContainsString('-tsp', $mockProcCommand);
        $this->assertEquals($params->getPassword() . PHP_EOL, file_get_contents($mockProcStdinFile));
    }

    public function testAddJSignParametersKeepsThePasswordsAlreadySetThroughTheOptionList(): void
    {
        global $mockExec, $mockProcCommand, $mockProcStdinFile;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        $params->setJSignParameters(['-tsp', 'tsa secret']);
        $params->addJSignParameters(['-kst', 'PKCS12']);

        $this->signWithFakeRuntime($params);

        $this->assertStringContainsString('--enable-stdin-passwords -ksp - -tsp - ', $mockProcCommand);
        $this->assertStringNotContainsString('tsa secret', $mockProcCommand);
        $this->assertEquals(
            $params->getPassword() . PHP_EOL . 'tsa secret' . PHP_EOL,
            file_get_contents($mockProcStdinFile)
        );
    }

    public function testSignKeepsAPasswordAlreadyMarkedAsReadFromStdin(): void
    {
        global $mockExec, $mockProcCommand, $mockProcStdinFile;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        $params->setJSignParameters(['-tsp', '-']);

        $this->signWithFakeRuntime($params);

        $this->assertStringContainsString(escapeshellarg('-tsp') . ' ' . escapeshellarg('-'), $mockProcCommand);
        $this->assertEquals($params->getPassword() . PHP_EOL, file_get_contents($mockProcStdinFile));
    }
}
