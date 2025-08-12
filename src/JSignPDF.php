<?php

namespace Jeidison\JSignPDF;

use Exception;
use Jeidison\JSignPDF\Sign\JSignParam;
use Jeidison\JSignPDF\Sign\JSignService;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 */
class JSignPDF
{
    private JSignService $service;
    private ?JSignParam $param = null;

    public function __construct(?JSignParam $param = null)
    {
        $this->service = new JSignService();
        $this->param   = $param;
    }

    public static function instance(?JSignParam $param = null): self
    {
        return new self($param);
    }

    public function sign(): string
    {
        if (!$this->param instanceof JSignParam) {
            throw new Exception('Invalid JSignParam instance');
        }
        return $this->service->sign($this->param);
    }

    public function getVersion(): string
    {
        if (!$this->param instanceof JSignParam) {
            throw new Exception('Invalid JSignParam instance');
        }
        return $this->service->getVersion($this->param);
    }

    public function setParam(JSignParam $param): void
    {
        $this->param = $param;
    }

}
