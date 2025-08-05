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
use UnexpectedValueException;

class JavaRuntimeServiceTest extends TestCase
{
    public string $testTmpDir = '';
    protected function setUp(): void {
        $this->testTmpDir = sys_get_temp_dir() . '/jsignpdf_temp_dir_' . uniqid();
        mkdir(directory: $this->testTmpDir, recursive: true);
    }

    public function testGetPathWhenJavaIsInstalled(): void {
        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();
        $jsignParam->setIsUseJavaInstalled(true);
        $path = $service->getPath($jsignParam);
        $this->assertEquals('java', $path);
    }

    public function testGetPathWithCustomAndValidJavaPath(): void {
        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();
        $jsignParam->setJavaPath(PHP_BINARY);
        $path = $service->getPath($jsignParam);
        $this->assertEquals(PHP_BINARY, $path);
    }

    public function testGetPathWithCustomAndInvalidJavaPath(): void {
        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();
        $jsignParam->setJavaPath(__FILE__);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not executable/');
        $service->getPath($jsignParam);
    }

    public function testGetPathWithDownloadUrlAndNotRealDirectory(): void {
        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();
        $jsignParam->setJavaPath(__FILE__);
        $jsignParam->setJavaDownloadUrl('https://fake.url');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not a real directory/');
        $service->getPath($jsignParam);
    }

    public function testGetPathWithDownloadUrlAndEmptyJavaVersion(): void {
        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();
        $jsignParam->setJavaPath(realpath(__DIR__ . '/../../tmp/'));
        $jsignParam->setJavaDownloadUrl('https://fake.url');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Java version required/');
        $service->getPath($jsignParam);
    }

    public function testGetPathWithDownloadUrlWithInvalidUrl(): void {
        vfsStream::setup('download');
        mkdir('vfs://download/bin');
        touch('vfs://download/bin/java');

        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();
        $jsignParam->setJavaPath('vfs://download/bin/java');
        $jsignParam->setJavaDownloadUrl('invalid_url');
        $jsignParam->setJavaVersion('21.0.0');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/url.*invalid/');
        $service->getPath($jsignParam);
    }

    public function testGetPathWithDownloadUrlWith4xxError(): void {
        vfsStream::setup('download');
        mkdir('vfs://download/bin');
        touch('vfs://download/bin/java');

        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();
        $jsignParam->setJavaPath('vfs://download/bin/java');
        $jsignParam->setJavaDownloadUrl('https://404.domain');
        $jsignParam->setJavaVersion('21.0.0');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Failure to download/');
        $service->getPath($jsignParam);
    }

    public function testGetPathWithDownloadUrlWithInvalidGzipedFile(): void {
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

        $jsignParam->setJavaVersion('21.0.0');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be extracted/');
        $service->getPath($jsignParam);
    }

    public function testGetPathWithDownloadUrlWithInvalidJavaPackage(): void {
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

        $jsignParam->setJavaVersion('21.0.0');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid tar content/');
        $service->getPath($jsignParam);
    }

    public function testGetPathWithoutJavaFallback(): void {
        mkdir($this->testTmpDir . '/bin', 0755, true);

        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();

        $jsignParam->setJavaVersion('21.0.0');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Java not found/');
        $service->getPath($jsignParam);
    }

    protected function tearDown(): void
    {
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
