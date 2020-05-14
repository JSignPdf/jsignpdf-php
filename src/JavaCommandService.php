<?php

namespace Jeidison\JSignPDF;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 */
class JavaCommandService
{
    public static function instance()
    {
        return new self();
    }

    public function command($isInstalled = false)
    {
        if ($isInstalled)
            return "java";

        return $this->builderPathJre(PHP_OS);
    }

    private function builderPathJre($os)
    {
        return __DIR__ .
            DIRECTORY_SEPARATOR .
            '..' .
            DIRECTORY_SEPARATOR .
            'bin' .
            DIRECTORY_SEPARATOR .
            "jre1.8.0_241_".strtolower($os) .
            DIRECTORY_SEPARATOR .
            'bin' .
            DIRECTORY_SEPARATOR .
            'java';
    }

}