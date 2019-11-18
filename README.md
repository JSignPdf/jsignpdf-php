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

try {
    $jSignPdf = new JSignPDF();
    $jSignPdf->setCertificate(file_get_contents('caminho_do_seu_certificado_aqui.pfx'));
    $jSignPdf->setPassword('senha_do_seu_certificado_aqui');
    $jSignPdf->setPdf(file_get_contents('caminho_do_pdf_que_voce_quer_assinar.pdf'));
    $fileSigned = $jSignPdf->sign()->output();
    file_put_contents('caminho_onde_voce_quer_salvar_aqui.pdf', $fileSigned);
} catch (Exception $e) {
    var_dump($e);
}
```

### Without composer:

- Download Repository
- Unzip file

```php
<?php

require_once "path/to/JSignPDF.php";

use Jeidison\JSignPDF\JSignPDF;

try {
    $jSignPdf = new JSignPDF();
    $jSignPdf->setCertificate(file_get_contents('caminho_do_seu_certificado_aqui.pfx'));
    $jSignPdf->setPassword('senha_do_seu_certificado_aqui');
    $jSignPdf->setPdf(file_get_contents('caminho_do_pdf_que_voce_quer_assinar.pdf'));
    $fileSigned = $jSignPdf->sign()->output();
    file_put_contents('caminho_onde_voce_quer_salvar_aqui.pdf', $fileSigned);
} catch (Exception $e) {
    var_dump($e);
}
```
