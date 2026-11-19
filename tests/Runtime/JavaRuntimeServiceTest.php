<?php

namespace Jeidison\JSignPDF\Tests;

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;
use org\bovigo\vfs\vfsStream;
use InvalidArgumentException;
use Jeidison\JSignPDF\Runtime\JavaRuntimeService;
use Jeidison\JSignPDF\Sign\JSignParam;
use PharData;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class JavaRuntimeServiceTest extends TestCase
{
    public string $testTmpDir = '';
    private ?MockWebServer $server = null;
    protected function setUp(): void
    {
        $this->testTmpDir = sys_get_temp_dir() . '/jsignpdf_temp_dir_' . uniqid();
        mkdir(directory: $this->testTmpDir, recursive: true);
    }

    public function testGetPathWhenJavaIsInstalled(): void
    {
        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();
        $jsignParam->setIsUseJavaInstalled(true);
        $path = $service->getPath($jsignParam);
        $this->assertEquals('java', $path);
    }

    public function testGetPathWithCustomAndValidJavaPath(): void
    {
        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();
        vfsStream::setup('download');
        mkdir('vfs://download/bin');
        touch('vfs://download/bin/java');
        chmod('vfs://download/bin/java', 0755);
        $jsignParam->setJavaPath('vfs://download/bin/java');
        $jsignParam->setJavaDownloadUrl('');
        $path = $service->getPath($jsignParam);
        $this->assertEquals('vfs://download/bin/java', $path);
    }

    public function testGetPathWithCustomJavaPath(): void
    {
        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();
        $expectedPath = __FILE__;
        $jsignParam->setJavaPath($expectedPath);
        $jsignParam->setJavaDownloadUrl('');
        $actual = $service->getPath($jsignParam);
        $this->assertEquals($expectedPath, $actual);
    }

    public function testGetPathWithDownloadUrlAndNotRealDirectory(): void
    {
        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();
        $root = vfsStream::setup('download');
        $root->chmod(0770)
            ->chgrp(vfsStream::GROUP_USER_1)
            ->chown(vfsStream::OWNER_USER_1);
        chgrp('vfs://download', 44);
        $jsignParam->setJavaPath('vfs://download/not_real_directory');
        $jsignParam->setJavaDownloadUrl('https://fake.url');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a real directory/');
        $service->getPath($jsignParam);
    }

    public function testGetPathWithDownloadUrlWithInvalidUrl(): void
    {
        vfsStream::setup('download');
        mkdir('vfs://download/bin');
        touch('vfs://download/bin/java');

        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();
        $jsignParam->setJavaPath('vfs://download/bin/java');
        $jsignParam->setJavaDownloadUrl('invalid_url');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/url.*invalid/');
        $service->getPath($jsignParam);
    }

    public function testGetPathWithDownloadUrlWith4xxError(): void
    {
        vfsStream::setup('download');
        mkdir('vfs://download/bin');
        touch('vfs://download/bin/java');

        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();
        $jsignParam->setJavaPath('vfs://download/bin/java');
        $jsignParam->setJavaDownloadUrl('https://404.domain');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Failure to download/');
        $service->getPath($jsignParam);
    }

    public function testGetPathWithDownloadUrlWithInvalidGzipedFile(): void
    {
        vfsStream::setup('download');
        mkdir('vfs://download/bin');
        touch('vfs://download/bin/java');


        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();
        $jsignParam->setJavaPath('vfs://download/bin/java');

        $server = new MockWebServer();
        $server->start();
        $server->setResponseOfPath(
            '/',
            new Response(
                'invalid body response',
            )
        );
        $url = $server->getServerRoot();
        $jsignParam->setJavaDownloadUrl($url);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be extracted/');
        $service->getPath($jsignParam);
    }

    public function testGetPathWithDownloadUrlWithInvalidJavaPackage(): void
    {
        mkdir($this->testTmpDir . '/bin', 0755, true);

        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();
        $jsignParam->setJavaPath($this->testTmpDir . '/bin/java');

        $tar = new PharData($this->testTmpDir . '/temp.tar.gz');
        $tar->addFromString('file.txt', 'invalid file');

        $server = new MockWebServer();
        $server->start();
        $server->setResponseOfPath(
            '/',
            new Response(
                gzencode(file_get_contents($this->testTmpDir . '/temp.tar.gz')),
            )
        );
        unlink($this->testTmpDir . '/temp.tar.gz');
        $url = $server->getServerRoot();
        $jsignParam->setJavaDownloadUrl($url);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid tar content/');
        $service->getPath($jsignParam);
    }

    public function testGetPathWithDownloadUrlWithInvalidVersion(): void
    {
        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();

        // When the version is invalid, will try to download the package
        $server = new MockWebServer();
        $server->start();
        $server->setResponseOfPath(
            '/',
            new Response('invalid response'),
        );
        $baseUrl = $server->getServerRoot();
        $url = $baseUrl . '/OpenJDK21U-jre_x64_linux_hotspot_21.0.8_9.tar.gz';
        $jsignParam->setJavaDownloadUrl($url);
        $jsignParam->setJavaPath($this->testTmpDir . '/bin/java');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be extracted/');
        $service->getPath($jsignParam);
    }

    public function testGetPathWithDownloadUrlWithValidVersion(): void
    {
        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();

        $tarGzFilename = 'OpenJDK21U-jre_x64_linux_hotspot_21.0.8_9.tar.gz';
        $url = 'https://fake.url/' . $tarGzFilename;
        $jsignParam->setJavaDownloadUrl($url);

        // When have a file with an expected name, will consider that the
        // downloaded java version is right
        touch($this->testTmpDir . '/.java_version_' . $tarGzFilename);
        $jsignParam->setJavaPath($this->testTmpDir . '/bin/java');

        $javaPath = $service->getPath($jsignParam);
        $this->assertEquals($jsignParam->getJavaPath(), $javaPath);
    }

    public function testGetPathInstallsADownloadMatchingTheGivenSha256(): void
    {
        [$url, $sha256] = $this->serveJavaPackage(['jdk-21.0.13+9-jre/bin/java' => 'java']);
        $jsignParam = new JSignParam();
        $jsignParam->setJavaPath($this->testTmpDir . '/bin/java');
        $jsignParam->setJavaDownloadUrl($url, $sha256);

        (new JavaRuntimeService())->getPath($jsignParam);

        $this->assertSame('java', file_get_contents($this->testTmpDir . '/bin/java'));
    }

    public function testGetPathRejectsADownloadNotMatchingTheGivenSha256(): void
    {
        [$url] = $this->serveJavaPackage(['jdk-21.0.13+9-jre/bin/java' => 'tampered java']);
        $jsignParam = new JSignParam();
        $jsignParam->setJavaPath($this->testTmpDir . '/bin/java');
        $jsignParam->setJavaDownloadUrl($url, str_repeat('0', 64));

        try {
            (new JavaRuntimeService())->getPath($jsignParam);
            $this->fail('A download that does not match the SHA-256 was accepted.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('does not match the expected SHA-256', $exception->getMessage());
        }

        $this->assertSame([], array_values(array_diff(scandir($this->testTmpDir), ['.', '..'])));
    }

    public function testGetPathDownloadsAgainWhenTheInstalledVersionWasNotCheckedAgainstTheGivenSha256(): void
    {
        [$url, $sha256] = $this->serveJavaPackage(['jdk-21.0.13+9-jre/bin/java' => 'java']);
        touch($this->testTmpDir . '/.java_version_' . basename($url));
        $jsignParam = new JSignParam();
        $jsignParam->setJavaPath($this->testTmpDir . '/bin/java');
        $jsignParam->setJavaDownloadUrl($url, $sha256);

        (new JavaRuntimeService())->getPath($jsignParam);

        $this->assertSame('java', file_get_contents($this->testTmpDir . '/bin/java'));
    }

    public function testGetPathDoesNotDownloadAgainAVersionCheckedAgainstTheGivenSha256(): void
    {
        [$url, $sha256] = $this->serveJavaPackage(['jdk-21.0.13+9-jre/bin/java' => 'java']);
        $firstParams = new JSignParam();
        $firstParams->setJavaPath($this->testTmpDir . '/bin/java');
        $firstParams->setJavaDownloadUrl($url, $sha256);
        (new JavaRuntimeService())->getPath($firstParams);
        $this->server?->stop();

        $secondParams = new JSignParam();
        $secondParams->setJavaPath($this->testTmpDir . '/bin/java');
        $secondParams->setJavaDownloadUrl($url, $sha256);

        $this->assertSame($this->testTmpDir . '/bin/java', (new JavaRuntimeService())->getPath($secondParams));
    }

    public function testGetPathRejectsAnHttpErrorResponse(): void
    {
        $this->server = new MockWebServer();
        $this->server->start();
        $this->server->setResponseOfPath('/java.tar.gz', new Response('not found', [], 404));
        $jsignParam = new JSignParam();
        $jsignParam->setJavaPath($this->testTmpDir . '/bin/java');
        $jsignParam->setJavaDownloadUrl($this->server->getServerRoot() . '/java.tar.gz');

        try {
            (new JavaRuntimeService())->getPath($jsignParam);
            $this->fail('An HTTP error response was accepted as the Java package.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Failure to download', $exception->getMessage());
        }

        $this->assertSame([], array_values(array_diff(scandir($this->testTmpDir), ['.', '..'])));
    }

    public function testGetPathLeavesNothingBehindWhenTheDownloadFails(): void
    {
        $this->server = new MockWebServer();
        $this->server->start();
        $url = $this->server->getServerRoot() . '/java.tar.gz';
        $this->server->stop();
        $jsignParam = new JSignParam();
        $jsignParam->setJavaPath($this->testTmpDir . '/bin/java');
        $jsignParam->setJavaDownloadUrl($url);

        try {
            (new JavaRuntimeService())->getPath($jsignParam);
            $this->fail('A failed download was accepted.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Failure to download', $exception->getMessage());
        }

        $this->assertSame([], array_values(array_diff(scandir($this->testTmpDir), ['.', '..'])));
    }

    public function testGetPathInstallsTheDownloadedJava(): void
    {
        [$url] = $this->serveJavaPackage([
            'jdk-21.0.13+9-jre/bin/java' => 'new java',
            'jdk-21.0.13+9-jre/lib/modules' => 'new modules',
        ]);
        $jsignParam = new JSignParam();
        $jsignParam->setJavaPath($this->testTmpDir . '/bin/java');
        $jsignParam->setJavaDownloadUrl($url);

        $javaPath = (new JavaRuntimeService())->getPath($jsignParam);

        $this->assertSame($this->testTmpDir . '/bin/java', $javaPath);
        $this->assertSame('new java', file_get_contents($this->testTmpDir . '/bin/java'));
        $this->assertSame('new modules', file_get_contents($this->testTmpDir . '/lib/modules'));
        $this->assertSame(
            ['.java_version_OpenJDK21U-jre_x64_linux_hotspot_21.0.13_9.tar.gz', 'bin', 'lib'],
            $this->entries($this->testTmpDir)
        );
    }

    public function testGetPathReplacesThePreviouslyInstalledJava(): void
    {
        $this->installPreviousJava();
        file_put_contents($this->testTmpDir . '/lib/removed', 'only in the old java');
        [$url] = $this->serveJavaPackage([
            'jdk-21.0.13+9-jre/bin/java' => 'new java',
            'jdk-21.0.13+9-jre/lib/modules' => 'new modules',
        ]);
        $jsignParam = new JSignParam();
        $jsignParam->setJavaPath($this->testTmpDir . '/bin/java');
        $jsignParam->setJavaDownloadUrl($url);

        (new JavaRuntimeService())->getPath($jsignParam);

        $this->assertSame('new java', file_get_contents($this->testTmpDir . '/bin/java'));
        $this->assertSame(['modules'], $this->entries($this->testTmpDir . '/lib'));
        $this->assertSame('new modules', file_get_contents($this->testTmpDir . '/lib/modules'));
        $this->assertSame(
            ['.java_version_OpenJDK21U-jre_x64_linux_hotspot_21.0.13_9.tar.gz', 'bin', 'lib'],
            $this->entries($this->testTmpDir)
        );
    }

    public function testGetPathKeepsThePreviousJavaWhenTheDownloadHasNoJavaBinary(): void
    {
        $this->installPreviousJava();
        [$url] = $this->serveJavaPackage([
            'jdk-21.0.13+9-jre/bin/keytool' => 'new keytool',
            'jdk-21.0.13+9-jre/lib/modules' => 'new modules',
        ]);
        $jsignParam = new JSignParam();
        $jsignParam->setJavaPath($this->testTmpDir . '/bin/java');
        $jsignParam->setJavaDownloadUrl($url);

        $this->assertGetPathFails($jsignParam, '/Java binary not found/');
        $this->assertSame('old java', file_get_contents($this->testTmpDir . '/bin/java'));
        $this->assertSame('old modules', file_get_contents($this->testTmpDir . '/lib/modules'));
        $this->assertSame(
            ['.java_version_OpenJDK21U-jre_x64_linux_hotspot_21.0.8_9.tar.gz', 'bin', 'lib'],
            $this->entries($this->testTmpDir)
        );

        $jsignParam->setJavaDownloadUrl($url);
        $this->assertGetPathFails($jsignParam, '/Java binary not found/');
    }

    public function testGetPathRestoresThePreviousJavaWhenTheReplacementFails(): void
    {
        $this->installPreviousJava();
        chmod($this->testTmpDir . '/lib', 0555);
        [$url] = $this->serveJavaPackage([
            'jdk-21.0.13+9-jre/bin/java' => 'new java',
            'jdk-21.0.13+9-jre/lib/modules' => 'new modules',
        ]);
        $jsignParam = new JSignParam();
        $jsignParam->setJavaPath($this->testTmpDir . '/bin/java');
        $jsignParam->setJavaDownloadUrl($url);

        try {
            $this->assertGetPathFails($jsignParam, '/Failure to replace the Java runtime/');
        } finally {
            chmod($this->testTmpDir . '/lib', 0755);
        }
        $this->assertSame('old java', file_get_contents($this->testTmpDir . '/bin/java'));
        $this->assertSame('old modules', file_get_contents($this->testTmpDir . '/lib/modules'));
        $this->assertSame(['bin', 'lib'], $this->entries($this->testTmpDir));
    }

    private function installPreviousJava(): void
    {
        mkdir($this->testTmpDir . '/bin', 0755, true);
        mkdir($this->testTmpDir . '/lib', 0755, true);
        file_put_contents($this->testTmpDir . '/bin/java', 'old java');
        file_put_contents($this->testTmpDir . '/lib/modules', 'old modules');
        touch($this->testTmpDir . '/.java_version_OpenJDK21U-jre_x64_linux_hotspot_21.0.8_9.tar.gz');
    }

    private function assertGetPathFails(JSignParam $jsignParam, string $messagePattern): void
    {
        try {
            (new JavaRuntimeService())->getPath($jsignParam);
        } catch (RuntimeException $exception) {
            $this->assertMatchesRegularExpression($messagePattern, $exception->getMessage());
            return;
        }
        $this->fail('The Java runtime was expected to fail to install.');
    }

    /**
     * @param array<string, string> $files
     * @return array{string, string}
     */
    private function serveJavaPackage(array $files): array
    {
        $tarPath = sys_get_temp_dir() . '/jsignpdf_temp_package_' . uniqid() . '.tar';
        $tar = new PharData($tarPath);
        foreach ($files as $path => $content) {
            $tar->addFromString($path, $content);
        }
        $content = (string) gzencode((string) file_get_contents($tarPath));
        unset($tar);
        unlink($tarPath);

        $this->server = new MockWebServer();
        $this->server->start();
        $this->server->setResponseOfPath('/OpenJDK21U-jre_x64_linux_hotspot_21.0.13_9.tar.gz', new Response($content));
        return [
            $this->server->getServerRoot() . '/OpenJDK21U-jre_x64_linux_hotspot_21.0.13_9.tar.gz',
            hash('sha256', $content),
        ];
    }

    /**
     * @return list<string>
     */
    private function entries(string $directory): array
    {
        $entries = array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
        sort($entries);
        return $entries;
    }

    public function testGetPathWithoutJavaFallback(): void
    {
        mkdir($this->testTmpDir . '/bin', 0755, true);

        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();

        $jsignParam->setJavaPath('');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Java not found/');
        $service->getPath($jsignParam);
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
        $dirs = glob(sys_get_temp_dir() . '/jsignpdf_temp_*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            if (is_dir($dir)) {
                $this->removeDirectoryContents($dir);
                rmdir($dir);
            }
        }
    }

    private function removeDirectoryContents($dir): void
    {
        $it = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }
    }
}
