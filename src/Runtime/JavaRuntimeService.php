<?php

declare(strict_types=1);

namespace Jeidison\JSignPDF\Runtime;

use InvalidArgumentException;
use Jeidison\JSignPDF\Sign\JSignParam;
use PharData;
use RuntimeException;

class JavaRuntimeService
{
    public function getPath(JSignParam $params): string
    {
        if ($params->isUseJavaInstalled()) {
            return 'java';
        }

        $javaPath = $params->getJavaPath();
        $downloadUrl = $params->getJavaDownloadUrl();

        if ($javaPath && !$downloadUrl) {
            if (is_file($javaPath) && is_executable($javaPath)) {
                return $javaPath;
            }
            throw new InvalidArgumentException('Java path defined is not executable: ' . $javaPath);
        }

        if ($downloadUrl && $javaPath) {
            $baseDir = rtrim($javaPath, '/bin/java');
            if (!is_dir($baseDir)) {
                throw new InvalidArgumentException('The java base dir is not a real directory: '. $baseDir);
            }
            try {
                self::validateVersion($params);
            } catch (RuntimeException) {
                self::downloadAndExtract($downloadUrl, $javaPath);
            }
            return $javaPath;
        }

        throw new InvalidArgumentException('Java not found.');
    }

    private function validateVersion(JSignParam $params): bool
    {
        $version = $params->getJavaVersion();
        if (!$version) {
            throw new InvalidArgumentException('Java version required');
        }
        $javaPath = $params->getJavaPath();
        \exec($javaPath . ' -version 2>&1', $javaVersion, $resultCode);
        if (empty($javaVersion)) {
            throw new RuntimeException('Failed to execute Java. Sounds that your operational system is blocking the JVM.');
        }
        if ($resultCode !== 0) {
            throw new RuntimeException('Failure to check Java version.');
        }
        $javaVersion = current($javaVersion);
        return $javaVersion === $version;
    }

    private function downloadAndExtract(string $url, string $baseDir): void
    {
        $baseDir = rtrim($baseDir, '/bin/java');

        if (!is_dir($baseDir)) {
            $ok = mkdir($baseDir, 0755, true);
            if (!$ok) {
                throw new RuntimeException('Failure to create the folder: ' . $baseDir);
            }
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('The url to download Java is invalid: ' . $url);
        }
        $content = @file_get_contents($url);
        if ($content === false) {
            throw new InvalidArgumentException("Failute to download file using the url $url, error: " . print_r(error_get_last(), true));
        }
        if (!$content) {
            throw new InvalidArgumentException('The url returned empty content: ' . $url);
        }
        $decompressedContent = @gzdecode($content);
        if ($decompressedContent === false) {
            throw new InvalidArgumentException('The file downloaded from follow URL cannot be gzdecoded: ' . $url);
        }
        file_put_contents($baseDir . '/java.tar.gz', $decompressedContent);
        $tar = new PharData($baseDir . '/java.tar.gz');
        $tar->extractTo($baseDir, null, true);
        unlink($baseDir . '/java.tar.gz');
        if (!file_exists($baseDir . '/bin/java')) {
            throw new RuntimeException('Java binary not found at: ' . $baseDir . '/bin/java');
        }
        chmod($baseDir . '/bin/java', 0700);
    }
}
