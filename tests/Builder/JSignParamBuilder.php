<?php

namespace Jeidison\JSignPDF\Tests\Builder;

use Jeidison\JSignPDF\Sign\JSignParam;

class JSignParamBuilder
{
    private $params;

    public function __construct()
    {
        $this->params = JSignParam::instance();
    }

    public static function instance()
    {
        return new self();
    }

    public function getParams()
    {
        return $this->params;
    }

    public function withDefault()
    {
        $params = JSignParam::instance();
        $params->setCertificate(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR. '..' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'certificado.pfx'));
        $params->setPdf(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR. '..' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'pdf-test.pdf'));
        $params->setPassword('123');
        return $params;
    }

}