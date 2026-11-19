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
        $file = @fopen($filename, 'x');
        if ($file === false) {
            throw new RuntimeException('Failure to create the temporary file: ' . $filename);
        }
        chmod($filename, 0600);
        $written = fwrite($file, $content);
        fclose($file);
        if ($written !== strlen($content)) {
            throw new RuntimeException('Failure to write the temporary file: ' . $filename);
        }
        return $filename;
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
