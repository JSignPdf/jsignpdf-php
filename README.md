# jsignpdf-php

This package is only wrapper of [JSignPdf](http://jsignpdf.sourceforge.net/) to use in PHP

### Installation:

```sh
$ composer require jeidison/jsignpdf-php
```
    
Examples:

```php
use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;

$param = JSignParam::instance();
$param->setCertificate(file_get_contents('/path/to/file/certificate.pfx'));
$param->setPdf(file_get_contents('/path/to/file/pdf_to_sign.pdf'));
$param->setPassword('certificate_password');

$jSignPdf   = new JSignPDF($param);
$fileSigned = $jSignPdf->sign();
file_put_contents('/path/to/file/file_signed.pdf', $fileSigned);
```

With Java Installed:
```php
use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;

$param = JSignParam::instance();
$param->setCertificate(file_get_contents('/path/to/file/certificate.pfx'));
$param->setPdf(file_get_contents('/path/to/file/pdf_to_sign.pdf'));
$param->setPassword('certificate_password');
$param->setIsUseJavaInstalled(true);

$jSignPdf   = new JSignPDF($param);
$fileSigned = $jSignPdf->sign();
file_put_contents('/path/to/file/file_signed.pdf', $fileSigned);
```

File signed as base64:
```php
use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;

$param = JSignParam::instance();
$param->setCertificate(file_get_contents('/path/to/file/certificate.pfx'));
$param->setPdf(file_get_contents('/path/to/file/pdf_to_sign.pdf'));
$param->setPassword('certificate_password');
$param->setIsOutputTypeBase64(true);

$jSignPdf           = new JSignPDF($param);
$fileSignedAsBase64 = $jSignPdf->sign();
file_put_contents('/path/to/file/file_signed.pdf', base64_decode($fileSignedAsBase64));
```

Change temp directory:
```php
use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;

$param = JSignParam::instance();
$param->setCertificate(file_get_contents('/path/to/file/certificate.pfx'));
$param->setPdf(file_get_contents('/path/to/file/pdf_to_sign.pdf'));
$param->setPassword('certificate_password');
$param->setTempPath('/path/temp/to/sign/files/');

$jSignPdf   = new JSignPDF($param);
$fileSigned = $jSignPdf->sign();
file_put_contents('/path/to/file/file_signed.pdf', $fileSigned);
```

Change parameters of JSignPDF:
```php
use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;

$param = JSignParam::instance();
$param->setCertificate(file_get_contents('/path/to/file/certificate.pfx'));
$param->setPdf(file_get_contents('/path/to/file/pdf_to_sign.pdf'));
$param->setPassword('certificate_password');
$param->setJSignParameters("-a -kst PKCS12 -ts https://freetsa.org/tsr");

$jSignPdf   = new JSignPDF($param);
$fileSigned = $jSignPdf->sign();
file_put_contents('/path/to/file/file_signed.pdf', $fileSigned);
```