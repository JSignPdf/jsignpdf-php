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
        $jsignPdfPath = $params->getjSignPdfJarPath();
        $downloadUrl = $params->getJSignPdfDownloadUrl();

        if ($jsignPdfPath && !$downloadUrl) {
            if (self::isInstalled($jsignPdfPath)) {
                return $jsignPdfPath;
            }
            throw new InvalidArgumentException('Jar of JSignPDF not found on path: '. $jsignPdfPath);
        }

        if ($downloadUrl && $jsignPdfPath) {
            $baseDir = self::baseDir($jsignPdfPath);
            if (!is_dir($baseDir)) {
                $ok = mkdir($baseDir, 0755, true);
                if ($ok === false) {
                    throw new InvalidArgumentException('The JSignPdf base dir cannot be created: '. $baseDir);
                }
            }
            if (!self::isInstalled($jsignPdfPath) || !self::validateVersion($params)) {
                self::downloadAndExtract($params);
            }
            return $jsignPdfPath;
        }

        throw new InvalidArgumentException('Java not found.');
    }

    public static function baseDir(string $jsignPdfPath): string
    {
        $baseDir = preg_replace('/\/JSignPdf.jar$/', '', $jsignPdfPath);
        if (!is_string($baseDir)) {
            throw new InvalidArgumentException('Invalid JsignParamPath');
        }
        return $baseDir;
    }

    private static function isInstalled(string $jsignPdfPath): bool
    {
        return file_exists($jsignPdfPath) || is_dir(self::baseDir($jsignPdfPath) . '/lib');
    }

    private function validateVersion(JSignParam $params): bool
    {
        $baseDir = self::baseDir($params->getjSignPdfJarPath());
        $versionCacheFile = $baseDir . '/.jsignpdf_version_' . basename($params->getJSignPdfDownloadUrl());
        return file_exists($versionCacheFile);
    }

    private function downloadAndExtract(JSignParam $params): void
    {
        $jsignPdfPath = $params->getjSignPdfJarPath();
        $url = $params->getJSignPdfDownloadUrl();

        $baseDir = self::baseDir($jsignPdfPath);
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
        $z = new ZipArchive();
        $ok = $z->open($baseDir . '/jsignpdf.zip');
        if ($ok !== true) {
            throw new InvalidArgumentException('The file ' . $baseDir . '/jsignpdf.zip cannot be extracted');
        }
        $rootDirInsideZip = $z->getNameIndex(0);
        if (!is_string($rootDirInsideZip)) {
            throw new InvalidArgumentException('The file ' . $baseDir . '/jsignpdf.zip is empty');
        }
        $ok = $z->extractTo($baseDir);
        if ($ok !== true) {
            throw new InvalidArgumentException('The file ' . $baseDir . '/jsignpdf.zip cannot be extracted');
        }
        @exec('mv ' . escapeshellarg($baseDir . '/'. $rootDirInsideZip) . '/* ' . escapeshellarg($baseDir));
        @exec('rm -rf ' . escapeshellarg($baseDir . '/'. $rootDirInsideZip));
        @exec('rm -f ' . escapeshellarg($baseDir) . '/.jsignpdf_version_*');
        unlink($baseDir . '/jsignpdf.zip');
        if (!self::isInstalled($jsignPdfPath)) {
            throw new RuntimeException('JSignPdf not found at: ' . $baseDir);
        }
        touch($baseDir . '/.jsignpdf_version_' . basename($url));
    }

    private function chunkDownload(string $url, string $destination): void
    {
        $fp = fopen($destination, 'w');

        if ($fp) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new InvalidArgumentException('Failure to download file using the url ' . $url);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $response = curl_exec($ch);
            if ($response === false) {
                throw new InvalidArgumentException('Failure to download file using the url ' . $url);
            }
            curl_close($ch);
            fclose($fp);
        } else {
            throw new InvalidArgumentException("Failute to download file using the url $url");
        }
    }
}
