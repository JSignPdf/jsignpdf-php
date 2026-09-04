<?php

namespace Jeidison\JSignPDF\Tests\Runtime;

use donatj\MockWebServer\MockWebServer;
use donatj\MockWebServer\Response;
use InvalidArgumentException;
use Jeidison\JSignPDF\Runtime\JSignPdfRuntimeService;
use Jeidison\JSignPDF\Sign\JSignParam;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class JSignPdfRuntimeServiceTest extends TestCase
{
    private string $testTmpDir = '';

    protected function setUp(): void
    {
        $this->testTmpDir = sys_get_temp_dir() . '/jsignpdf_zip_dir_' . uniqid();
        mkdir(directory: $this->testTmpDir, recursive: true);
    }

    private function createZip(string $path, string $rootDir, array $files): void
    {
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addEmptyDir($rootDir);
        foreach ($files as $name => $content) {
            $zip->addFromString($rootDir . '/' . $name, $content);
        }
        $zip->close();
    }

    private function serve(string $zipPath, string $filename): string
    {
        $server = new MockWebServer();
        $server->start();
        $server->setResponseOfPath(
            '/' . $filename,
            new Response(file_get_contents($zipPath)),
        );
        return $server->getServerRoot() . '/' . $filename;
    }

    public function testGetPathWithCustomAndValidJarPath(): void
    {
        $jsignParam = new JSignParam();
        $service = new JSignPdfRuntimeService();
        touch($this->testTmpDir . '/JSignPdf.jar');
        $jsignParam->setJSignPdfPath($this->testTmpDir);
        $jsignParam->setJSignPdfDownloadUrl('');
        $this->assertEquals($this->testTmpDir, $service->getPath($jsignParam));
    }

    public function testGetPathWithoutFatJarButWithLibDirectory(): void
    {
        $jsignParam = new JSignParam();
        $service = new JSignPdfRuntimeService();
        mkdir($this->testTmpDir . '/lib');
        $jsignParam->setJSignPdfPath($this->testTmpDir);
        $jsignParam->setJSignPdfDownloadUrl('');
        $this->assertEquals($this->testTmpDir, $service->getPath($jsignParam));
    }

    public function testGetPathWhenNothingIsInstalled(): void
    {
        $jsignParam = new JSignParam();
        $service = new JSignPdfRuntimeService();
        $jsignParam->setJSignPdfPath($this->testTmpDir);
        $jsignParam->setJSignPdfDownloadUrl('');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/JSignPDF not found/');
        $service->getPath($jsignParam);
    }

    public function testGetPathWithoutJarPath(): void
    {
        $jsignParam = new JSignParam();
        $service = new JSignPdfRuntimeService();
        $jsignParam->setJSignPdfPath('');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Java not found/');
        $service->getPath($jsignParam);
    }

    public function testDownloadAndExtractDistributionWithFatJar(): void
    {
        $zipPath = $this->testTmpDir . '/source.zip';
        $this->createZip($zipPath, 'jsignpdf-3.0.1', ['JSignPdf.jar' => 'fake jar content']);
        $url = $this->serve($zipPath, 'jsignpdf-3.0.1.zip');
        unlink($zipPath);

        $jsignParam = new JSignParam();
        $service = new JSignPdfRuntimeService();
        $jsignParam->setJSignPdfPath($this->testTmpDir . '/install');
        $jsignParam->setJSignPdfDownloadUrl($url);

        $service->getPath($jsignParam);

        $this->assertFileExists($this->testTmpDir . '/install/JSignPdf.jar');
        $this->assertFileExists($this->testTmpDir . '/install/.jsignpdf_version_jsignpdf-3.0.1.zip');
        $this->assertFileDoesNotExist($this->testTmpDir . '/install/jsignpdf-3.0.1');
    }

    public function testDownloadAndExtractDistributionWithoutFatJar(): void
    {
        $zipPath = $this->testTmpDir . '/source.zip';
        $this->createZip($zipPath, 'jsignpdf-3.1.0', [
            'lib/jsignpdf-engine-api-3.1.0.jar' => 'fake jar content',
            'bin/jsignpdf.sh' => 'fake launcher',
        ]);
        $url = $this->serve($zipPath, 'jsignpdf-3.1.0-minimal.zip');
        unlink($zipPath);

        $jsignParam = new JSignParam();
        $service = new JSignPdfRuntimeService();
        $jsignParam->setJSignPdfPath($this->testTmpDir . '/install');
        $jsignParam->setJSignPdfDownloadUrl($url);

        $service->getPath($jsignParam);

        $this->assertDirectoryExists($this->testTmpDir . '/install/lib');
        $this->assertFileExists($this->testTmpDir . '/install/lib/jsignpdf-engine-api-3.1.0.jar');
        $this->assertFileExists($this->testTmpDir . '/install/.jsignpdf_version_jsignpdf-3.1.0-minimal.zip');
        $this->assertFileDoesNotExist($this->testTmpDir . '/install/JSignPdf.jar');
        $this->assertFileDoesNotExist($this->testTmpDir . '/install/jsignpdf-3.1.0');
    }

    public function testUpgradeFromADistributionWithFatJarRemovesTheOldJar(): void
    {
        $zipPath = $this->testTmpDir . '/source.zip';
        $this->createZip($zipPath, 'jsignpdf-3.1.0', [
            'lib/engine.jar' => 'new jar content',
            'bin/jsignpdf.sh' => 'launcher',
        ]);
        $url = $this->serve($zipPath, 'jsignpdf-3.1.0-minimal.zip');
        unlink($zipPath);

        mkdir($this->testTmpDir . '/install');
        file_put_contents($this->testTmpDir . '/install/JSignPdf.jar', 'old fat jar');
        touch($this->testTmpDir . '/install/.jsignpdf_version_jsignpdf-2.3.0.zip');

        $jsignParam = new JSignParam();
        $jsignParam->setJSignPdfPath($this->testTmpDir . '/install');
        $jsignParam->setJSignPdfDownloadUrl($url);
        (new JSignPdfRuntimeService())->getPath($jsignParam);

        $this->assertFileDoesNotExist($this->testTmpDir . '/install/JSignPdf.jar');
        $this->assertFileDoesNotExist($this->testTmpDir . '/install/.jsignpdf_version_jsignpdf-2.3.0.zip');
        $this->assertFileExists($this->testTmpDir . '/install/lib/engine.jar');
        $this->assertFileExists($this->testTmpDir . '/install/.jsignpdf_version_jsignpdf-3.1.0-minimal.zip');
    }

    public function testUpgradeBetweenDistributionsWithoutFatJarReplacesTheLibDirectory(): void
    {
        $zipPath = $this->testTmpDir . '/source.zip';
        $this->createZip($zipPath, 'jsignpdf-3.2.0', ['lib/engine-3.2.0.jar' => 'new jar content']);
        $url = $this->serve($zipPath, 'jsignpdf-3.2.0-minimal.zip');
        unlink($zipPath);

        mkdir($this->testTmpDir . '/install/lib', 0755, true);
        file_put_contents($this->testTmpDir . '/install/lib/engine-3.1.0.jar', 'old jar');
        touch($this->testTmpDir . '/install/.jsignpdf_version_jsignpdf-3.1.0-minimal.zip');

        $jsignParam = new JSignParam();
        $jsignParam->setJSignPdfPath($this->testTmpDir . '/install');
        $jsignParam->setJSignPdfDownloadUrl($url);
        (new JSignPdfRuntimeService())->getPath($jsignParam);

        $this->assertFileExists($this->testTmpDir . '/install/lib/engine-3.2.0.jar');
        $this->assertFileDoesNotExist($this->testTmpDir . '/install/lib/engine-3.1.0.jar');
        $this->assertFileExists($this->testTmpDir . '/install/.jsignpdf_version_jsignpdf-3.2.0-minimal.zip');
    }

    public function testDownloadAndExtractArchiveWithoutRootDirectoryEntry(): void
    {
        $zipPath = $this->testTmpDir . '/source.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('jsignpdf-3.1.0/lib/engine.jar', 'jar content');
        $zip->close();
        $url = $this->serve($zipPath, 'jsignpdf-3.1.0-minimal.zip');
        unlink($zipPath);

        $jsignParam = new JSignParam();
        $jsignParam->setJSignPdfPath($this->testTmpDir . '/install');
        $jsignParam->setJSignPdfDownloadUrl($url);
        (new JSignPdfRuntimeService())->getPath($jsignParam);

        $this->assertFileExists($this->testTmpDir . '/install/lib/engine.jar');
    }

    public function testStagingDirectoryIsRemovedAfterTheInstall(): void
    {
        $zipPath = $this->testTmpDir . '/source.zip';
        $this->createZip($zipPath, 'jsignpdf-3.1.0', ['lib/engine.jar' => 'jar content']);
        $url = $this->serve($zipPath, 'jsignpdf-3.1.0-minimal.zip');
        unlink($zipPath);

        $jsignParam = new JSignParam();
        $jsignParam->setJSignPdfPath($this->testTmpDir . '/install');
        $jsignParam->setJSignPdfDownloadUrl($url);
        (new JSignPdfRuntimeService())->getPath($jsignParam);

        $this->assertEmpty(glob($this->testTmpDir . '/install/.jsignpdf_staging_*'));
        $this->assertFileDoesNotExist($this->testTmpDir . '/install/jsignpdf.zip');
    }

    public function testDownloadIsSkippedWhenTheInstalledVersionMatches(): void
    {
        $jsignParam = new JSignParam();
        $service = new JSignPdfRuntimeService();

        mkdir($this->testTmpDir . '/lib');
        touch($this->testTmpDir . '/.jsignpdf_version_jsignpdf-3.1.0-minimal.zip');
        $jsignParam->setJSignPdfPath($this->testTmpDir);
        $jsignParam->setJSignPdfDownloadUrl('https://fake.url/jsignpdf-3.1.0-minimal.zip');

        $this->assertEquals($this->testTmpDir, $service->getPath($jsignParam));
    }

    public function testDownloadWithInvalidUrl(): void
    {
        $jsignParam = new JSignParam();
        $service = new JSignPdfRuntimeService();
        $jsignParam->setJSignPdfPath($this->testTmpDir . '/install');
        $jsignParam->setJSignPdfDownloadUrl('invalid_url');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/url.*invalid/');
        $service->getPath($jsignParam);
    }

    public function testDownloadWithInvalidZipFile(): void
    {
        $server = new MockWebServer();
        $server->start();
        $server->setResponseOfPath('/jsignpdf.zip', new Response('invalid body response'));

        $jsignParam = new JSignParam();
        $service = new JSignPdfRuntimeService();
        $jsignParam->setJSignPdfPath($this->testTmpDir . '/install');
        $jsignParam->setJSignPdfDownloadUrl($server->getServerRoot() . '/jsignpdf.zip');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be extracted/');
        $service->getPath($jsignParam);
    }

    protected function tearDown(): void
    {
        $dirs = glob(sys_get_temp_dir() . '/jsignpdf_zip_dir_*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $this->removeDirectoryContents($dir);
            rmdir($dir);
        }
    }

    private function removeDirectoryContents(string $dir): void
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
