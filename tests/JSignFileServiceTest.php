<?php

namespace Jeidison\JSignPDF\Tests;

use Jeidison\JSignPDF\JSignFileService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class JSignFileServiceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'jsignpdf_' . bin2hex(random_bytes(8)) . DIRECTORY_SEPARATOR;
        mkdir($this->directory, 0700);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testStoreFileWritesTheContent(): void
    {
        $filename = JSignFileService::instance()->storeFile($this->directory, 'certificate.pfx', 'content');

        $this->assertSame($this->directory . 'certificate.pfx', $filename);
        $this->assertSame('content', file_get_contents($filename));
    }

    public function testStoreFileKeepsTheFileReadableOnlyByItsOwner(): void
    {
        $previousUmask = umask(0);
        try {
            $filename = JSignFileService::instance()->storeFile($this->directory, 'certificate.pfx', 'content');
        } finally {
            umask($previousUmask);
        }

        clearstatcache();
        $this->assertSame(0600, fileperms($filename) & 0777);
    }

    public function testStoreFileDoesNotWriteIntoAFileThatAlreadyExists(): void
    {
        file_put_contents($this->directory . 'certificate.pfx', 'planted');

        try {
            JSignFileService::instance()->storeFile($this->directory, 'certificate.pfx', 'content');
            $this->fail('An existing file was reused.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('certificate.pfx', $exception->getMessage());
        }

        $this->assertSame('planted', file_get_contents($this->directory . 'certificate.pfx'));
    }

    public function testStoreFileDoesNotFollowASymlinkLeftInItsPlace(): void
    {
        $target = $this->directory . 'target';
        touch($target);
        symlink($target, $this->directory . 'certificate.pfx');

        try {
            JSignFileService::instance()->storeFile($this->directory, 'certificate.pfx', 'content');
            $this->fail('The symlink was followed.');
        } catch (RuntimeException) {
        }

        $this->assertSame('', file_get_contents($target));
    }
}
