<?php

namespace Jeidison\JSignPDF;

use RuntimeException;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 */
class JSignFileService
{
    public static function instance(): self
    {
        return new self();
    }

    public function contentFile(string $path, bool $isInBase64 = false): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            return '';
        }
        return $isInBase64 ? base64_encode($content) : $content;
    }

    public function storeFile(string $path, string $name, string $content): string
    {
        $filename = $path . $name;
        $reservation = @fopen($filename, 'x');
        if ($reservation === false) {
            throw new RuntimeException('Failure to create the temporary file: ' . $filename);
        }
        fclose($reservation);
        try {
            $this->replaceWithPrivateFile($path, $filename, $content);
        } catch (RuntimeException $exception) {
            $this->deleteFile($filename);
            throw $exception;
        }
        return $filename;
    }

    private function replaceWithPrivateFile(string $path, string $filename, string $content): void
    {
        $directory = realpath($path);
        $privateFilename = $directory === false ? false : @tempnam($directory, 'jsignpdf');
        if ($privateFilename === false || dirname($privateFilename) !== $directory) {
            if ($privateFilename !== false) {
                $this->deleteFile($privateFilename);
            }
            throw new RuntimeException('Failure to create the temporary file: ' . $filename);
        }
        $file = fopen($privateFilename, 'w');
        $written = $file === false ? false : fwrite($file, $content);
        if ($file !== false) {
            fclose($file);
        }
        if ($written !== strlen($content) || !@rename($privateFilename, $filename)) {
            $this->deleteFile($privateFilename);
            throw new RuntimeException('Failure to write the temporary file: ' . $filename);
        }
    }

    public function deleteFile(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function deleteTempFiles(string $pathTemp, string $name): void
    {
        $pathPfxFile       = "$pathTemp$name.pfx";
        $pathPdfFile       = "$pathTemp$name.pdf";
        $pathPdfSignedFile = "{$pathTemp}{$name}_signed.pdf";
        $tempFiles         = [$pathPfxFile, $pathPdfFile, $pathPdfSignedFile];
        foreach ($tempFiles as $path) {
            $this->deleteFile($path);
        }
    }

}
