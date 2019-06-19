<?php

use Jeidison\JSignPDF\JSignPDF;

class JSignPDFTest extends PHPUnit_Framework_TestCase
{

    private $pathPdfTest;
    private $pathCertificateTest;
    private $passwordCertificateTest = 123;

    public function setUp()
    {
        $this->pathPdfTest = __DIR__ . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "pdf-test.pdf";
        $this->pathCertificateTest = __DIR__ . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "certificado-test.pfx";
    }

    public function testSign()
    {
        $jSignPdf = new JSignPDF();
        $jSignPdf->setCertificate(file_get_contents($this->pathCertificateTest));
        $jSignPdf->setPassword($this->passwordCertificateTest);
        $jSignPdf->setPdf(file_get_contents($this->pathPdfTest));
        $this->assertNotNull($jSignPdf->sign()->output());
    }

}
