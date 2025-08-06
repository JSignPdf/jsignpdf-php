<?php

declare(strict_types=1);

namespace Jeidison\JSignPDF\Runtime;

use InvalidArgumentException;
use Jeidison\JSignPDF\Sign\JSignParam;
use PharData;
use PharException;
use RuntimeException;
use UnexpectedValueException;

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
            $baseDir = preg_replace('/\/bin\/java$/', '', $javaPath);
            if (!is_dir($baseDir)) {
                $ok = mkdir($baseDir, 0755, true);
                if ($ok === false) {
                    throw new InvalidArgumentException('The java base dir is not a real directory. Create this directory first: '. $baseDir);
                }
            }
            try {
                self::validateVersion($params);
            } catch (RuntimeException) {
                self::downloadAndExtract($downloadUrl, $javaPath);
            }
            $params->setJavaDownloadUrl('');
            $params->setJavaPath($javaPath);
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
        if (count($javaVersion) <= 1) {
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
        $baseDir = preg_replace('/\/bin\/java$/', '', $baseDir);

        if (!is_dir($baseDir)) {
            $ok = mkdir($baseDir, 0755, true);
            if (!$ok) {
                throw new RuntimeException('Failure to create the folder: ' . $baseDir);
            }
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('The url to download Java is invalid: ' . $url);
        }
        $this->chunkDownload($url, $baseDir . '/java.tar.gz');
        try {
            $tar = new PharData($baseDir . '/java.tar.gz');
        } catch (PharException|UnexpectedValueException $e) {
            throw new InvalidArgumentException('The file ' . $baseDir . '/java.tar.gz cannot be extracted');
        }
        $rootDirInsideTar = $this->findRootDir($tar, $baseDir . '/java.tar.gz');
        if (!$rootDirInsideTar) {
            throw new InvalidArgumentException('Invalid tar content.');
        }
        $tar->extractTo(directory: $baseDir, overwrite: true);
        @exec('mv ' . escapeshellarg($baseDir . '/'. $rootDirInsideTar) . '/* ' . escapeshellarg($baseDir));
        @exec('rm -rf ' . escapeshellarg($baseDir . '/'. $rootDirInsideTar));
        unlink($baseDir . '/java.tar.gz');
        if (!file_exists($baseDir . '/bin/java')) {
            throw new RuntimeException('Java binary not found at: ' . $baseDir . '/bin/java');
        }
        chmod($baseDir . '/bin/java', 0700);
    }

    private function findRootDir(PharData $phar, $rootDir) {
        $files = new \RecursiveIteratorIterator($phar, \RecursiveIteratorIterator::CHILD_FIRST);
        $rootDir = realpath($rootDir);

        foreach ($files as $file) {
            $pathName = $file->getPathname();
            if (str_contains($pathName, '/bin/') || str_contains($pathName, '/bin/')) {
                $parts = explode($rootDir, $pathName);
                $internalFullPath = end($parts);
                $parts = explode('/bin/', $internalFullPath);
                return trim($parts[0], '/');
            }
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
