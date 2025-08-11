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
        vfsStream::setup('download');
        mkdir('vfs://download/bin');
        touch('vfs://download/bin/java');
        chmod('vfs://download/bin/java', 0755);
        $jsignParam->setJavaPath('vfs://download/bin/java');
        $jsignParam->setJavaDownloadUrl('');
        $path = $service->getPath($jsignParam);
        $this->assertEquals('vfs://download/bin/java', $path);
    }

    public function testGetPathWithCustomAndInvalidJavaPath(): void {
        $jsignParam = new JSignParam();
        $service = new JavaRuntimeService();
        $jsignParam->setJavaPath(__FILE__);
        $jsignParam->setJavaDownloadUrl('');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not executable/');
        $service->getPath($jsignParam);
    }

    public function testGetPathWithDownloadUrlAndNotRealDirectory(): void {
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

    public function testGetPathWithDownloadUrlWithInvalidUrl(): void {
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

    public function testGetPathWithDownloadUrlWith4xxError(): void {
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

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid tar content/');
        $service->getPath($jsignParam);
    }

    public function testGetPathWithDownloadUrlWithInvalidVersion(): void {
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

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be extracted/');
        $service->getPath($jsignParam);
    }

    public function testGetPathWithDownloadUrlWithValidVersion(): void {
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

    public function testGetPathWithoutJavaFallback(): void {
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
