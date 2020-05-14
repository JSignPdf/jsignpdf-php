# jsignpdf-php

This package is only wrapper of [JSignPdf](http://jsignpdf.sourceforge.net/) to use in PHP

### Installation:

```bash
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

...
$param->setIsUseJavaInstalled(true);
...
```

File signed as base64:
```php
...
$param->setIsOutputTypeBase64(true);
...
$fileSignedAsBase64 = $jSignPdf->sign();
file_put_contents('/path/to/file/file_signed.pdf', base64_decode($fileSignedAsBase64));
```

Change temp directory:
```php
...
$param->setTempPath('/path/temp/to/sign/files/');
...
```

Change parameters of JSignPDF:
```php
...
$param->setJSignParameters("-a -kst PKCS12 -ts https://freetsa.org/tsr");
...
```

## Credits
- [Jeidison Farias](https://github.com/jeidison)
