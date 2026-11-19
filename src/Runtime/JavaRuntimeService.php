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
            return $javaPath;
        }

        if ($downloadUrl && $javaPath) {
            $baseDir = preg_replace('/\/bin\/java$/', '', $javaPath);
            if (!is_string($baseDir)) {
                throw new InvalidArgumentException('Invalid JsignParamPath');
            }
            if (!is_dir($baseDir)) {
                $ok = mkdir($baseDir, 0755, true);
                if ($ok === false) {
                    throw new InvalidArgumentException('The java base dir is not a real directory. Create this directory first: '. $baseDir);
                }
            }
            if (!self::validateVersion($params)) {
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
        $javaPath = $params->getJavaPath();
        $baseDir = preg_replace('/\/bin\/java$/', '', $javaPath);
        if (!is_string($baseDir)) {
            throw new InvalidArgumentException('Invalid JsignParamPath');
        }
        $lastVersion = $baseDir . '/.java_version_' . basename($params->getJavaDownloadUrl());
        return file_exists($lastVersion);
    }

    private function downloadAndExtract(string $url, string $baseDir): void
    {
        $baseDir = preg_replace('/\/bin\/java$/', '', $baseDir);
        if (!is_string($baseDir)) {
            throw new InvalidArgumentException('Invalid JsignParamPath');
        }

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
            $this->deletePath($baseDir . '/java.tar.gz');
            throw new InvalidArgumentException('The file ' . $baseDir . '/java.tar.gz cannot be extracted');
        }
        $staging = $baseDir . '/.java_staging_' . bin2hex(random_bytes(8));
        try {
            $rootDirInsideTar = $this->findRootDir($tar, $baseDir . '/java.tar.gz');
            if (empty($rootDirInsideTar)) {
                throw new InvalidArgumentException('Invalid tar content.');
            }
            $tar->extractTo(directory: $staging, overwrite: true);
            $runtime = $staging . '/' . $rootDirInsideTar;
            if (!is_file($runtime . '/bin/java')) {
                throw new RuntimeException('Java binary not found in the file downloaded from: ' . $url);
            }
            chmod($runtime . '/bin/java', 0700);
            foreach (glob($baseDir . '/.java_version_*') ?: [] as $previousVersion) {
                unlink($previousVersion);
            }
            $this->replaceInstall($runtime, $baseDir);
        } finally {
            $this->deletePath($staging);
            $this->deletePath($baseDir . '/java.tar.gz');
        }
        touch($baseDir . '/.java_version_' . basename($url));
    }

    private function replaceInstall(string $source, string $baseDir): void
    {
        $previous = $baseDir . '/.java_previous_' . bin2hex(random_bytes(8));
        if (!mkdir($previous, 0700)) {
            throw new RuntimeException('Failure to create the folder: ' . $previous);
        }
        $movedAside = [];
        $installed = [];
        try {
            foreach (array_diff(scandir($source) ?: [], ['.', '..']) as $entry) {
                $target = $baseDir . '/' . $entry;
                if (file_exists($target) || is_link($target)) {
                    $this->move($target, $previous . '/' . $entry);
                    $movedAside[] = $entry;
                }
                $this->move($source . '/' . $entry, $target);
                $installed[] = $entry;
            }
        } catch (RuntimeException $exception) {
            foreach ($installed as $entry) {
                $this->deletePath($baseDir . '/' . $entry);
            }
            foreach ($movedAside as $entry) {
                @rename($previous . '/' . $entry, $baseDir . '/' . $entry);
            }
            throw $exception;
        } finally {
            $this->deletePath($previous);
        }
    }

    private function move(string $source, string $target): void
    {
        if (!@rename($source, $target)) {
            throw new RuntimeException('Failure to replace the Java runtime at: ' . $target);
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

    private function findRootDir(PharData $phar, string $rootDir): string
    {
        $files = new \RecursiveIteratorIterator($phar, \RecursiveIteratorIterator::CHILD_FIRST);
        $rootDir = realpath($rootDir);
        if (!is_string($rootDir) || empty($rootDir)) {
            throw new InvalidArgumentException('Invalid tar content.');
        }

        foreach ($files as $file) {
            $pathName = $file->getPathname();
            if (str_contains($pathName, '/bin/') || str_contains($pathName, '/bin/')) {
                $parts = explode($rootDir, $pathName);
                $internalFullPath = end($parts);
                $parts = explode('/bin/', $internalFullPath);
                return trim($parts[0], '/');
            }
        }
        return '';
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
