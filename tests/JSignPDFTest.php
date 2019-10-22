<?php

use Jeidison\JSignPDF\JSignPDF;

class JSignPDFTest extends PHPUnit_Framework_TestCase
{

    private $pathPdfTest;
    private $pathCertificateTest;
    private $passwordCertificateTest = 123;
    private $pathToSaveTmpFiles = __DIR__;

    public function setUp()
    {
        $this->pathPdfTest = __DIR__ . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "pdf-test.pdf";
        $this->pathCertificateTest = __DIR__ . DIRECTORY_SEPARATOR . "resources" . DIRECTORY_SEPARATOR . "certificado-test.pfx";
    }

    public function testSign()
    {
        $certificate = base64_decode(file_get_contents($this->pathCertificateTest));
        $pdfFile     = base64_decode(file_get_contents($this->pathPdfTest));
        $password    = $this->passwordCertificateTest;
        $jSign       = new JSignPDF();
        $response = $jSign->setBasePath($this->pathToSaveTmpFiles)
                          ->setCertificate($certificate)
                          ->setPassword($password)
                          ->setPdf($pdfFile)
                          ->sign()
                          ->output();

        $this->assertNotNull($response);
    }

}
