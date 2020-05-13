<?php

require_once "../src/JSignPDF.php";

use Jeidison\JSignPDF\JSignPDF;

try {
    $jSignPdf = new JSignPDF();
    $jSignPdf->setCertificate(file_get_contents("../tests/resources/teste.pfx"));
    $jSignPdf->setPassword('lorena131');
    $jSignPdf->setPdf(file_get_contents('../tests/resources/pdf-test.pdf'));
    $fileSigned = $jSignPdf->sign()->output();
    file_put_contents('../tmp/teste.pdf', $fileSigned);
} catch (Exception $e) {
    var_dump($e);
}