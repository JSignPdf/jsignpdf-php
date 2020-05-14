<?php

namespace Jeidison\JSignPDF;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 */
class JSignFileService
{

    public static function instance()
    {
        return new self();
    }

    public function contentFile($path, $isInBase64 = false)
    {
        $content = file_get_contents($path);
        return $isInBase64 ? base64_encode($content) : $content;
    }

    public function storeFile($path, $name, $content)
    {
        $filename = $path . $name;
        file_put_contents($filename, $content);
        return $filename;
    }

    public function deleteFile(string $path)
    {
        if (is_file($path))
            unlink($path);
    }

    public function deleteTempFiles(string $pathTemp, string $name)
    {
        $pathPfxFile       = "$pathTemp$name.pfx";
        $pathPdfFile       = "$pathTemp$name.pdf";
        $pathPdfSignedFile = "{$pathTemp}{$name}_signed.pdf";
        $tempFiles         = [$pathPfxFile, $pathPdfFile, $pathPdfSignedFile];
        foreach ($tempFiles as $path)
            $this->deleteFile($path);
    }

}
