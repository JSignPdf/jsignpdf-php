<?php

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 */
require_once "../src/JSignPDF.php";

use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;

$param = JSignParam::instance();
$param->setCertificate(file_get_contents('../tests/resources/certificado.pfx'));
$param->setPdf(file_get_contents('../tests/resources/pdf-test.pdf'));
$param->setPassword('123');

$jSignPdf = new JSignPDF($param);
$fileSigned = $jSignPdf->sign();
file_put_contents('../tmp/file_signed.pdf', $fileSigned);