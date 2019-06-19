<?php

namespace Jeidison\JSignPDF;

use Exception;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 */
class JSignPDF
{

    private $pdf;
    private $pathPdfSigned;
    private $parameters = "-a -kst PKCS12";
    private $certificate;
    private $password;
    private $fileName;

    public function sign()
    {
        if (empty($this->pdf))
            throw new Exception("PDF is Empty");

        if (empty($this->certificate))
            throw new Exception("Certificate is Empty");

        if (empty($this->password))
            throw new Exception("Certificate Password is Empty");

        $this->signFile();

        return $this;
    }

    /**
     * @param string $type (B = Base64 of file signed; P = Path o file Signed; null = Bytes of file (Default))
     * @return mixed
     */
    public function output($type = null)
    {
        $data = $this->getData($type);
        $this->removeFiles();
        return $data;
    }

    public function getParameters()
    {
        return $this->parameters;
    }

    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
        return $this;
    }

    public function getCertificate()
    {
        return $this->certificate;
    }

    public function setCertificate($certificate)
    {
        $this->certificate = $this->saveCertificate($certificate);
        return $this;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function setPassword($password)
    {
        $this->password = $password;
        return $this;
    }

    public function getPdf()
    {
        return $this->pdf;
    }

    public function setPdf($pdf)
    {
        $this->pdf = $this->savePdf($pdf);
        return $this;
    }

    private function signFile()
    {
        $pathJar = $this->retrievePathJar();
        $pathTemp = $this->retrievePathTemp();
        $output = exec("java -jar {$pathJar} {$this->pdf} -ksf {$this->certificate} -ksp {$this->password} {$this->parameters} -d {$pathTemp}");
        if (strpos($output, 'java: not found') !== false) {
            throw new Exception($output);
        }
        $this->pathPdfSigned = $pathTemp . str_replace('.pdf', '', $this->fileName) . '_signed.pdf';
    }

    private function retrievePathJar()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'jsignpdf-1.6.4' . DIRECTORY_SEPARATOR . 'JSignPdf.jar';
    }

    private function retrievePathTemp()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR;
    }

    private function saveCertificate($certificate)
    {
        $name = md5(uniqid() . mt_rand()) . ".pfx";
        $path = "{$this->retrievePathTemp()}{$name}";
        file_put_contents($path, $certificate);
        return $path;
    }

    private function savePdf($pdf)
    {
        $this->fileName = md5(uniqid() . mt_rand()) . ".pdf";
        $path = "{$this->retrievePathTemp()}{$this->fileName}";
        file_put_contents($path, $pdf);
        return $path;
    }

    private function removeFiles()
    {
        $files = glob($this->retrievePathTemp() . '/*');
        foreach($files as $file){
            if(is_file($file)){
                unlink($file);
            }
        }
    }

    private function getData($type = null)
    {
        switch ($type) {
            case null;
                return file_get_contents($this->pathPdfSigned);
            case "B";
                return base64_encode(file_get_contents($this->pathPdfSigned));
            case "P";
                return $this->pathPdfSigned;
            default;
                new Exception("Invalid Type");
        }
    }

}
