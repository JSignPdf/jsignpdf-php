# jsignpdf-php

### Installation:

```sh
$ composer require jeidison/jsignpdf-php
```

### Warning
This package use JAVA for sign PDFs
    
Examples:

```php
use Jeidison\JSignPDF\JSignPDF;

$jSignPdf = new JSignPDF();
$jSignPdf->setCertificate(file_get_contents($this->pathCertificateTest));
$jSignPdf->setPassword($this->passwordCertificateTest);
$jSignPdf->setPdf(file_get_contents($this->pathPdfTest));
$fileSigned = $jSignPdf->sign()->output();
```

### Without composer:

- Download Repository
- Unzip file

```php
<?php

require_once "path/to/JSignPDF.php";

use Jeidison\JSignPDF\JSignPDF;

$jSignPdf = new JSignPDF();
$jSignPdf->setCertificate(file_get_contents($this->pathCertificateTest));
$jSignPdf->setPassword($this->passwordCertificateTest);
$jSignPdf->setPdf(file_get_contents($this->pathPdfTest));
$fileSigned = $jSignPdf->sign()->output();
```
