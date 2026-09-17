<?php

namespace Jeidison\JSignPDF\Tests;

use Jeidison\JSignPDF\Sign\SignatureField;
use PHPUnit\Framework\TestCase;

class SignatureFieldTest extends TestCase
{
    public function testExposesFieldValues(): void
    {
        $field = new SignatureField(
            'Customer Signature',
            2,
            70.0,
            600.0,
            300.0,
            660.0,
            true,
            true,
        );

        $this->assertSame('Customer Signature', $field->getName());
        $this->assertSame(2, $field->getPage());
        $this->assertSame(70.0, $field->getLlx());
        $this->assertSame(600.0, $field->getLly());
        $this->assertSame(300.0, $field->getUrx());
        $this->assertSame(660.0, $field->getUry());
        $this->assertTrue($field->isSigned());
        $this->assertFalse($field->isBlank());
        $this->assertTrue($field->isHidden());
        $this->assertTrue($field->hasVisibleRectangle());
    }

    public function testBlankField(): void
    {
        $field = new SignatureField(
            'CustomerSignature',
            1,
            70.0,
            700.0,
            300.0,
            760.0,
            false,
            false,
        );

        $this->assertFalse($field->isSigned());
        $this->assertTrue($field->isBlank());
        $this->assertFalse($field->isHidden());
        $this->assertTrue($field->hasVisibleRectangle());
    }

    public function testZeroSizeRectangleIsNotVisible(): void
    {
        $field = new SignatureField(
            'InvisibleSignature',
            1,
            0.0,
            0.0,
            0.0,
            0.0,
            false,
            false,
        );

        $this->assertFalse($field->hasVisibleRectangle());
    }
}
