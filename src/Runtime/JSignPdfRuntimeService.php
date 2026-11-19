<?php

declare(strict_types=1);

namespace Jeidison\JSignPDF\Runtime;

use InvalidArgumentException;
use Jeidison\JSignPDF\Sign\JSignParam;
use RuntimeException;
use ZipArchive;

class JSignPdfRuntimeService
{
    public function getPath(JSignParam $params): string
    {
        $jsignPdfPath = $params->getJSignPdfPath();
        $downloadUrl = $params->getJSignPdfDownloadUrl();

        if ($jsignPdfPath && !$downloadUrl) {
            if (self::isInstalled($jsignPdfPath)) {
                return $jsignPdfPath;
            }
            throw new InvalidArgumentException('JSignPDF not found on path: '. $jsignPdfPath);
        }

        if ($downloadUrl && $jsignPdfPath) {
            if (!is_dir($jsignPdfPath)) {
                $ok = mkdir($jsignPdfPath, 0755, true);
                if ($ok === false) {
                    throw new InvalidArgumentException('The JSignPdf base dir cannot be created: '. $jsignPdfPath);
                }
            }
            if (!self::isInstalled($jsignPdfPath) || !self::validateVersion($params)) {
                self::downloadAndExtract($params);
            }
            return $jsignPdfPath;
        }

        throw new InvalidArgumentException('Java not found.');
    }

    private static function isInstalled(string $jsignPdfPath): bool
    {
        return is_dir($jsignPdfPath . '/lib') || file_exists($jsignPdfPath . '/JSignPdf.jar');
    }

    private function validateVersion(JSignParam $params): bool
    {
        $baseDir = $params->getJSignPdfPath();
        return file_exists($baseDir . '/' . $this->versionMarker($params));
    }

    private function versionMarker(JSignParam $params): string
    {
        $sha256 = $params->getJSignPdfDownloadSha256();
        return '.jsignpdf_version_' . basename($params->getJSignPdfDownloadUrl()) . ($sha256 === '' ? '' : '.' . $sha256);
    }

    private function downloadAndExtract(JSignParam $params): void
    {
        $baseDir = $params->getJSignPdfPath();
        $url = $params->getJSignPdfDownloadUrl();

        if (!is_dir($baseDir)) {
            $ok = mkdir($baseDir, 0755, true);
            if (!$ok) {
                throw new RuntimeException('Failure to create the folder: ' . $baseDir);
            }
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('The url to download Java is invalid: ' . $url);
        }
        $this->chunkDownload($url, $baseDir . '/jsignpdf.zip');
        $this->verifySha256($baseDir . '/jsignpdf.zip', $params->getJSignPdfDownloadSha256(), $url);
        $zip = new ZipArchive();
        $ok = $zip->open($baseDir . '/jsignpdf.zip');
        if ($ok !== true) {
            throw new InvalidArgumentException('The file ' . $baseDir . '/jsignpdf.zip cannot be extracted');
        }
        $staging = $baseDir . '/.jsignpdf_staging_' . uniqid();
        $ok = $zip->extractTo($staging);
        $zip->close();
        if ($ok !== true) {
            $this->deletePath($staging);
            throw new InvalidArgumentException('The file ' . $baseDir . '/jsignpdf.zip cannot be extracted');
        }
        unlink($baseDir . '/jsignpdf.zip');
        try {
            $this->replaceInstall($this->archiveRoot($staging), $baseDir);
        } finally {
            $this->deletePath($staging);
        }
        foreach (glob($baseDir . '/.jsignpdf_version_*') ?: [] as $previousVersion) {
            unlink($previousVersion);
        }
        if (!self::isInstalled($baseDir)) {
            throw new RuntimeException('JSignPdf not found at: ' . $baseDir);
        }
        touch($baseDir . '/' . $this->versionMarker($params));
    }

    private function archiveRoot(string $staging): string
    {
        $entries = array_values(array_diff(scandir($staging) ?: [], ['.', '..']));
        if (count($entries) === 1 && is_dir($staging . '/' . $entries[0])) {
            return $staging . '/' . $entries[0];
        }
        return $staging;
    }

    private function replaceInstall(string $source, string $baseDir): void
    {
        $this->deletePath($baseDir . '/JSignPdf.jar');
        foreach (array_diff(scandir($source) ?: [], ['.', '..']) as $entry) {
            $target = $baseDir . '/' . $entry;
            $this->deletePath($target);
            if (!rename($source . '/' . $entry, $target)) {
                throw new RuntimeException('Failure to install JSignPdf at: ' . $target);
            }
        }
    }

    private function deletePath(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        $contents = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($contents as $item) {
            if ($item->isDir() && !$item->isLink()) {
                rmdir($item->getPathname());
                continue;
            }
            unlink($item->getPathname());
        }
        rmdir($path);
    }

    private function chunkDownload(string $url, string $destination): void
    {
        $file = fopen($destination, 'w');
        if ($file === false) {
            throw new InvalidArgumentException('Failure to download file using the url ' . $url);
        }
        $curl = curl_init($url);
        if ($curl === false) {
            fclose($file);
            unlink($destination);
            throw new InvalidArgumentException('Failure to download file using the url ' . $url);
        }
        curl_setopt($curl, CURLOPT_FILE, $file);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        $downloaded = curl_exec($curl);
        $error = curl_error($curl);
        fclose($file);
        if ($downloaded === false) {
            unlink($destination);
            throw new InvalidArgumentException('Failure to download file using the url ' . $url . ': ' . $error);
        }
    }

    private function verifySha256(string $path, string $sha256, string $url): void
    {
        if ($sha256 === '') {
            return;
        }
        $downloadedSha256 = hash_file('sha256', $path);
        if ($downloadedSha256 === false || !hash_equals($sha256, $downloadedSha256)) {
            unlink($path);
            throw new RuntimeException('The file downloaded from ' . $url . ' does not match the expected SHA-256.');
        }
    }
}
