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
            if (file_exists($jsignPdfPath)) {
                return $jsignPdfPath;
            }
            throw new InvalidArgumentException('Jar of JSignPDF not found on path: '. $jsignPdfPath);
        }

        if ($downloadUrl && $jsignPdfPath) {
            $baseDir = preg_replace('/\/JSignPdf.jar$/', '', $jsignPdfPath);
            if (!is_dir($baseDir)) {
                $ok = mkdir($baseDir, 0755, true);
                if ($ok === false) {
                    throw new InvalidArgumentException('The JSignPdf base dir cannot be created: '. $baseDir);
                }
            }
            if (!file_exists($jsignPdfPath)) {
                self::downloadAndExtract($params);
            }
            return $jsignPdfPath;
        }

        throw new InvalidArgumentException('Java not found.');
    }

    private function downloadAndExtract(JSignParam $params): void
    {
        $jsignPdfPath = $params->getjSignPdfJarPath();
        $url = $params->getJSignPdfDownloadUrl();

        $baseDir = preg_replace('/\/JSignPdf.jar$/', '', $jsignPdfPath);

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
        $ok = $z->extractTo(pathto: $baseDir, files: [$z->getNameIndex(0) . 'JSignPdf.jar']);
        if ($ok !== true) {
            throw new InvalidArgumentException('JSignPdf.jar not found inside path: ' . $z->getNameIndex(0) . 'JSignPdf.jar');
        }
        @exec('mv ' . escapeshellarg($baseDir . '/'. $z->getNameIndex(0)) . '/JSignPdf.jar ' . escapeshellarg($baseDir));
        @exec('rm -rf ' . escapeshellarg($baseDir . '/'. $z->getNameIndex(0)));
        unlink($baseDir . '/jsignpdf.zip');
        if (!file_exists($baseDir . '/JSignPdf.jar')) {
            throw new RuntimeException('Java binary not found at: ' . $baseDir . '/bin/java');
        }
    }

    private function chunkDownload(string $url, string $destination): void
    {
        $fp = fopen($destination, 'w');

        if ($fp) {
            $ch = curl_init($url);
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
