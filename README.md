# jsignpdf-php

### Installation using composer:

```sh
$ composer require jeidison/jsignpdf-php
```
    
Examples:

```php
use Jeidison\JSignPDF\JSignPDF;

$jSignPdf = new JSignPDF();
$jSignPdf->setCertificate(file_get_contents($this->pathCertificateTest));
$jSignPdf->setPassword($this->passwordCertificateTest);
$jSignPdf->setPdf(file_get_contents($this->pathPdfTest));
$fileSigned = $jSignPdf->sign()->output();
```
