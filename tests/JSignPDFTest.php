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

function proc_open(string $command, array $descriptor_spec, ?array &$pipes, ?string $cwd = null, ?array $env_vars = null)
{
    global $mockExec, $mockProcCommand, $mockProcStdinFile, $mockProcEnv;
    if (!$mockExec) {
        return \proc_open($command, $descriptor_spec, $pipes, $cwd, $env_vars);
    }
    $mockProcCommand = $command;
    $mockProcEnv = $env_vars;
    $mockProcStdinFile = tempnam(sys_get_temp_dir(), 'jsignpdf_stdin_');
    $stdout = fopen('php://memory', 'w+');
    fwrite($stdout, implode(PHP_EOL, $mockExec));
    rewind($stdout);
    $pipes = [fopen($mockProcStdinFile, 'w'), $stdout];
    return $stdout;
}

function proc_close($process)
{
    global $mockExec, $mockProcExitCode;
    return $mockExec ? $mockProcExitCode ?? 0 : \proc_close($process);
}

namespace Jeidison\JSignPDF\Tests;

use org\bovigo\vfs\vfsStream;
use Exception;
use Jeidison\JSignPDF\JSignPDF;
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
        global $mockExec, $mockProcCommand, $mockProcStdinFile, $mockProcEnv, $mockProcExitCode;
        $mockExec = null;
        $mockProcCommand = null;
        $mockProcStdinFile = null;
        $mockProcEnv = null;
        $mockProcExitCode = null;
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

    public function testGetVersionPassesEnvironmentVariablesToTheProcess(): void
    {
        global $mockExec, $mockProcEnv;
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
        $params->setEnvironmentVariables(['JSIGNPDF_HOME' => '/tmp/jsignpdf-home']);

        $this->service->getVersion($params);

        $this->assertIsArray($mockProcEnv);
        $this->assertSame('/tmp/jsignpdf-home', $mockProcEnv['JSIGNPDF_HOME']);
    }

    public function testSignWhenJSignPdfReportsAFailure(): void
    {
        global $mockExec, $mockProcExitCode;
        $mockExec = [
            'INFO Creating signature',
            'INFO Finished: Creating of signature failed.',
        ];
        $mockProcExitCode = 4;
        $params = $this->withFakeRuntime();
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');

        $this->expectExceptionMessageMatches('/Creating of signature failed/');
        $this->service->sign($params);
    }

    public function testSignSucceedsBasedOnTheExitCodeNotOnTheOutputText(): void
    {
        global $mockExec;
        $mockExec = ['some unrelated log line, no success message here'];
        $params = $this->withFakeRuntime();
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');
        file_put_contents($params->getTempPdfSignedPath(), 'signed file content');

        $fileSignedContent = $this->service->sign($params);

        $this->assertEquals('signed file content', $fileSignedContent);
    }

    public function testSignFailsBasedOnTheExitCodeEvenWithoutAFailureMessage(): void
    {
        global $mockExec, $mockProcExitCode;
        $mockExec = ['some unrelated log line, no failure message here'];
        $mockProcExitCode = 1;
        $params = $this->withFakeRuntime();
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');

        $this->expectExceptionMessageMatches('/Error to sign PDF/');
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

    public function testSignPassesCustomJavaOptionsToTheJvm(): void
    {
        global $mockExec, $mockProcCommand;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        $params->setJavaOptions(['-Duser.home=/tmp/jsignpdf-home']);
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');
        file_put_contents($params->getTempPdfSignedPath(), 'signed file content');

        $this->service->sign($params);

        $this->assertStringContainsString(
            '-Duser.language=en ' . escapeshellarg('-Duser.home=/tmp/jsignpdf-home'),
            $mockProcCommand
        );
    }

    public function testSignDoesNotOverrideTheEnvironmentByDefault(): void
    {
        global $mockExec, $mockProcEnv;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');
        file_put_contents($params->getTempPdfSignedPath(), 'signed file content');

        $this->service->sign($params);

        $this->assertNull($mockProcEnv);
    }

    public function testSignPassesEnvironmentVariablesToTheProcess(): void
    {
        global $mockExec, $mockProcEnv;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        $params->setEnvironmentVariables(['JSIGNPDF_HOME' => '/tmp/jsignpdf-home']);
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');
        file_put_contents($params->getTempPdfSignedPath(), 'signed file content');

        $this->service->sign($params);

        $this->assertIsArray($mockProcEnv);
        $this->assertSame('/tmp/jsignpdf-home', $mockProcEnv['JSIGNPDF_HOME']);
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
            '-kst' => 'PKCS12',
            '-ts' => 'https://tsa.example/tsr?first=1&second=2',
            '-o' => "reason with space, ' quote and ; semicolon",
        ];
        $params = $this->withFakeRuntime();
        $params->setJSignParameters($options);
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');
        file_put_contents($params->getTempPdfSignedPath(), 'signed file content');

        $this->service->sign($params);

        foreach ($options as $option => $value) {
            $this->assertStringContainsString(
                escapeshellarg($option) . ' ' . escapeshellarg($value),
                $mockProcCommand
            );
        }
    }

    public function testAddJSignParametersAppendsToTheDefaultOptions(): void
    {
        global $mockExec, $mockProcCommand;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        $params->addJSignParameters(['-ha' => 'SHA512']);
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');
        file_put_contents($params->getTempPdfSignedPath(), 'signed file content');

        $this->service->sign($params);

        $this->assertStringContainsString(
            escapeshellarg('-a') . ' ' . escapeshellarg('-kst') . ' ' . escapeshellarg('PKCS12')
                . ' ' . escapeshellarg('-ha') . ' ' . escapeshellarg('SHA512'),
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
            '-kst' => 'PKCS12',
            '--overwrite',
            '-ts' => 'https://tsa.example/tsr',
            '-ta' => 'PASSWORD',
            '-tsu' => 'jhon',
            '-tsp' => 'tsa secret',
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
            'short option' => [['-tsp' => 'tsa secret']],
            'long option' => [['--tsa-password' => 'tsa secret']],
            'short option with assignment' => [['-tsp=tsa secret']],
            'long option with assignment' => [['--tsa-password=tsa secret']],
        ];
    }

    public function testSignPrefersThePasswordOfTheSetterOverTheOneOfTheOptionList(): void
    {
        global $mockExec, $mockProcStdinFile;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        $params->setJSignParameters(['-tsp' => 'from the list']);
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
        $params->setJSignParameters(['-tsp' => 'tsa secret']);
        $params->setJSignParameters(['-kst' => 'PKCS12']);

        $this->signWithFakeRuntime($params);

        $this->assertStringNotContainsString('-tsp', $mockProcCommand);
        $this->assertEquals($params->getPassword() . PHP_EOL, file_get_contents($mockProcStdinFile));
    }

    public function testAddJSignParametersKeepsThePasswordsAlreadySetThroughTheOptionList(): void
    {
        global $mockExec, $mockProcCommand, $mockProcStdinFile;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        $params->setJSignParameters(['-tsp' => 'tsa secret']);
        $params->addJSignParameters(['-kst' => 'PKCS12']);

        $this->signWithFakeRuntime($params);

        $this->assertStringContainsString('--enable-stdin-passwords -ksp - -tsp - ', $mockProcCommand);
        $this->assertStringNotContainsString('tsa secret', $mockProcCommand);
        $this->assertEquals(
            $params->getPassword() . PHP_EOL . 'tsa secret' . PHP_EOL,
            file_get_contents($mockProcStdinFile)
        );
    }

    #[DataProvider('providerDashAsPasswordSpellings')]
    public function testSignSendsADashThroughStdinAsAnyOtherPassword(array $parameters): void
    {
        global $mockExec, $mockProcCommand, $mockProcStdinFile;
        $mockExec = ['Finished: Signature succesfully created.'];
        $params = $this->withFakeRuntime();
        $params->setJSignParameters($parameters);

        $this->signWithFakeRuntime($params);

        $this->assertStringContainsString('--enable-stdin-passwords -ksp - -tsp - ', $mockProcCommand);
        $this->assertEquals(
            $params->getPassword() . PHP_EOL . '-' . PHP_EOL,
            file_get_contents($mockProcStdinFile)
        );
    }

    public static function providerDashAsPasswordSpellings(): array
    {
        return [
            'short option' => [['-tsp' => '-']],
            'short option with assignment' => [['-tsp=-']],
        ];
    }

    public function testSignPassesTheConfiguredSignatureField(): void
    {
        global $mockExec, $mockProcCommand;
        $mockExec = ['Finished: Signature succesfully created.'];

        $params = $this->withFakeRuntime();
        $params->setSignatureField("Customer Signature 'Main'");
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');
        file_put_contents($params->getTempPdfSignedPath(), 'signed file content');

        $this->service->sign($params);

        $this->assertStringContainsString(
            '--sig-field ' . escapeshellarg("Customer Signature 'Main'"),
            $mockProcCommand
        );
    }

    public function testSignDoesNotPassSignatureFieldWhenItIsNotConfigured(): void
    {
        global $mockExec, $mockProcCommand;
        $mockExec = ['Finished: Signature succesfully created.'];

        $params = $this->withFakeRuntime();
        $params->setCertificate($this->getNewCert($params->getPassword()));
        $params->setPathPdfSigned('vfs://download/temp');
        file_put_contents($params->getTempPdfSignedPath(), 'signed file content');

        $this->service->sign($params);

        $this->assertStringNotContainsString('--sig-field', $mockProcCommand);
    }

    public function testGetSignatureFieldsUsesTheInspectionCommandWithoutSigningCredentials(): void
    {
        global $mockExec, $mockProcCommand;

        $mockExec = ['document.pdf: no signature fields'];

        $params = $this->withFakeRuntime();
        $params->setCertificate('');
        $params->setPassword('');

        $fields = $this->service->getSignatureFields($params);

        $this->assertSame([], $fields);
        $this->assertStringContainsString('--quiet --list-sig-fields', $mockProcCommand);
        $this->assertStringContainsString('-Duser.language=en', $mockProcCommand);
        $this->assertStringNotContainsString('-ksf', $mockProcCommand);
        $this->assertStringNotContainsString('--enable-stdin-passwords', $mockProcCommand);
    }

    public function testGetSignatureFieldsDeletesTheTemporaryPdfAfterInspection(): void
    {
        global $mockExec;

        $mockExec = ['document.pdf: no signature fields'];

        $params = $this->withFakeRuntime();
        $tempPdf = $params->getTempPdfPath();

        $this->service->getSignatureFields($params);

        $this->assertFileDoesNotExist($tempPdf);
    }

    public function testGetSignatureFieldsParsesJSignPdfOutput(): void
    {
        global $mockExec;

        $mockExec = [
            '#1   Customer Signature              page 1    [70.0 700.0 300.0 760.0] blank',
            '#2   Manager Signature               page 2    [70.5 600.25 300.75 660.0] signed, hidden',
            '#3   Podpis zákazníka                page 3    [-10.5 -20.25 0.0 0.0] blank hidden',
        ];

        $params = $this->withFakeRuntime();

        $fields = $this->service->getSignatureFields($params);

        $this->assertCount(3, $fields);

        $this->assertSame('Customer Signature', $fields[0]->getName());
        $this->assertSame(1, $fields[0]->getPage());
        $this->assertSame(70.0, $fields[0]->getLlx());
        $this->assertSame(700.0, $fields[0]->getLly());
        $this->assertSame(300.0, $fields[0]->getUrx());
        $this->assertSame(760.0, $fields[0]->getUry());
        $this->assertTrue($fields[0]->isBlank());
        $this->assertFalse($fields[0]->isHidden());

        $this->assertSame('Manager Signature', $fields[1]->getName());
        $this->assertSame(2, $fields[1]->getPage());
        $this->assertTrue($fields[1]->isSigned());
        $this->assertTrue($fields[1]->isHidden());

        $this->assertSame('Podpis zákazníka', $fields[2]->getName());
        $this->assertSame(-10.5, $fields[2]->getLlx());
        $this->assertSame(-20.25, $fields[2]->getLly());
        $this->assertTrue($fields[2]->isBlank());
        $this->assertTrue($fields[2]->isHidden());
    }

    public function testGetSignatureFieldsParsesNamesLongerThanTheDisplayWidth(): void
    {
        global $mockExec;

        $name = 'This signature field name is much longer than thirty characters';

        $mockExec = [
            "#1   $name page 1    [10.0 20.0 30.0 40.0] blank",
        ];

        $fields = $this->service->getSignatureFields($this->withFakeRuntime());

        $this->assertSame($name, $fields[0]->getName());
    }

    public function testGetSignatureFieldsIgnoresSelectorShadowSuffix(): void
    {
        global $mockExec;

        $suffix = ' - this field name shadows the selector of the same name, the field name wins';

        $mockExec = [
            '#1   auto                           page 1    [10.0 20.0 30.0 40.0] blank' . $suffix,
            '#2   #1                             page 2    [50.0 60.0 70.0 80.0] signed' . $suffix,
        ];

        $fields = $this->service->getSignatureFields($this->withFakeRuntime());

        $this->assertSame('auto', $fields[0]->getName());
        $this->assertSame('#1', $fields[1]->getName());
    }

    public function testGetSignatureFieldsRejectsMalformedFieldOutput(): void
    {
        global $mockExec;

        $mockExec = [
            '#1 broken signature field output',
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('#1 broken signature field output');

        $this->service->getSignatureFields($this->withFakeRuntime());
    }

    public function testGetSignatureFieldsPreservesJSignPdfFailureDiagnostic(): void
    {
        global $mockExec, $mockProcExitCode;

        $mockExec = [
            "Can not read the signature fields of '/tmp/document.pdf': Invalid PDF",
        ];
        $mockProcExitCode = 5;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Can not read the signature fields of '/tmp/document.pdf': Invalid PDF"
        );

        $this->service->getSignatureFields($this->withFakeRuntime());
    }

    public function testGetSignatureFieldsDeletesTemporaryPdfWhenExecutionFails(): void
    {
        global $mockExec, $mockProcExitCode;

        $mockExec = [
            "Can not read the signature fields of '/tmp/document.pdf': Invalid PDF",
        ];
        $mockProcExitCode = 5;

        $params = $this->withFakeRuntime();
        $tempPdf = $params->getTempPdfPath();

        try {
            $this->service->getSignatureFields($params);
            $this->fail('Expected signature field inspection to fail.');
        } catch (Exception $e) {
            $this->assertStringContainsString(
                'Can not read the signature fields',
                $e->getMessage()
            );
        }

        $this->assertFileDoesNotExist($tempPdf);
    }

    public function testGetSignatureFieldsDeletesTemporaryPdfWhenParsingFails(): void
    {
        global $mockExec;

        $mockExec = [
            '#1 malformed output',
        ];

        $params = $this->withFakeRuntime();
        $tempPdf = $params->getTempPdfPath();

        try {
            $this->service->getSignatureFields($params);
            $this->fail('Expected signature field parsing to fail.');
        } catch (Exception $e) {
            $this->assertStringContainsString(
                '#1 malformed output',
                $e->getMessage()
            );
        }

        $this->assertFileDoesNotExist($tempPdf);
    }

    public function testGetSignatureFieldsThroughFacadeRequiresParams(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid JSignParam instance');

        JSignPDF::instance()->getSignatureFields();
    }

    public function testGetSignatureFieldsParsesHeaderBeforeFields(): void
    {
        global $mockExec;

        $mockExec = [
            'Signature fields of /tmp/example.pdf:',
            '#1   Customer Signature             page 1    [70.0 700.0 300.0 760.0] blank',
        ];

        $fields = $this->service->getSignatureFields(
            $this->withFakeRuntime()
        );

        $this->assertCount(1, $fields);
        $this->assertSame('Customer Signature', $fields[0]->getName());
    }

    public function testGetSignatureFieldsRejectsEmptySuccessfulOutput(): void
    {
        global $mockExec;

        $mockExec = [''];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unexpected signature field output');

        $this->service->getSignatureFields(
            $this->withFakeRuntime()
        );
    }

    public function testGetSignatureFieldsRejectsFieldAfterNoFieldsOutput(): void
    {
        global $mockExec;

        $mockExec = [
            '/tmp/example.pdf: no signature fields',
            '#1   Customer Signature             page 1    [70.0 700.0 300.0 760.0] blank',
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Unexpected signature field output'
        );

        $this->service->getSignatureFields(
            $this->withFakeRuntime()
        );
    }
}
