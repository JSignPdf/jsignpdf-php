<?php

namespace Jeidison\JSignPDF\Tests;

use Exception;
use Jeidison\JSignPDF\Sign\JSignService;
use Jeidison\JSignPDF\Tests\Builder\JSignParamBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 */
class JSignPDFTest extends TestCase
{
    private $service;

    protected function setUp(): void
    {
        $this->service = new JSignService();
    }

    public function testSignSuccess()
    {
        if (!class_exists('JSignPDF\JSignPDFBin\JavaCommandService')) {
            $this->markTestSkipped('Install jsignpdf/jsignpdf-bin');
        }
        $params = JSignParamBuilder::instance()->withDefault();
        $fileSigned = $this->service->sign($params);
        $this->assertNotNull($fileSigned);
    }

    public function testSignError()
    {
        $this->expectException(Exception::class);
        $params = JSignParamBuilder::instance();
        $this->service->sign($params->getParams());
    }


    public function testWithWhenResponseIsBase64()
    {
        if (!class_exists('JSignPDF\JSignPDFBin\JavaCommandService')) {
            $this->markTestSkipped('Install jsignpdf/jsignpdf-bin');
        }
        $params = JSignParamBuilder::instance()->withDefault()->setIsOutputTypeBase64(true);
        $fileSigned = $this->service->sign($params);
        $this->assertTrue(base64_decode($fileSigned, true) == true);
    }

    public function testSignWhenCertificateIsNull()
    {
        $this->expectExceptionMessage('Certificate is Empty or Invalid.');
        $params = JSignParamBuilder::instance()->withDefault()->setCertificate(null);
        $this->service->sign($params);
    }

    public function testSignWhenPdfIsNull()
    {
        $this->expectExceptionMessage('PDF is Empty or Invalid.');
        $params = JSignParamBuilder::instance()->withDefault()->setPdf(null);
        $this->service->sign($params);
    }

    public function testSignWhenPasswordIsNull()
    {
        $this->expectExceptionMessage('Certificate Password is Empty.');
        $params = JSignParamBuilder::instance()->withDefault()->setPassword(null);
        $this->service->sign($params);
    }

    public function testSignWhenTempPathIsInvalid()
    {
        $this->expectExceptionMessage('Temp Path is invalid or has not permission to writable.');
        $params = JSignParamBuilder::instance()->withDefault()->setTempPath(null);
        $this->service->sign($params);
    }

    public function testSignWhenPasswordIsInvalid()
    {
        $this->expectExceptionMessage('Certificate Password Invalid.');
        $params = JSignParamBuilder::instance()->withDefault()->setPassword('123456');
        $this->service->sign($params);
    }

    public function testSignWhenJavaNotFound()
    {
        $javaVersion    = exec("java -version 2>&1");
        $hasJavaVersion = strpos($javaVersion, 'not found') === false;
        if ($hasJavaVersion) {
            $this->markTestSkipped('Java is already installed, impossible to test if it is not installed');
        }
        $this->expectExceptionMessage('Java not installed, set the flag "isUseJavaInstalled" as false or install java.');
        $params = JSignParamBuilder::instance()->withDefault()->setIsUseJavaInstalled(true);
        $this->service->sign($params);
    }
}
