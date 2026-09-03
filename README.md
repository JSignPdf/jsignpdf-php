# jsignpdf-php

This package is only wrapper of [JSignPdf](http://jsignpdf.sourceforge.net/) to use in PHP

## Installation:

```bash
$ composer require jeidison/jsignpdf-php
```

## Examples

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
$param->setIsUseJavaInstalled(true);
```

With standalone Java:
```php
$param->setJavaPath('/path/to/bin/java');
```

With JSignPDF bin:
```php
$param->setJSignPdfPath('/path/to/jsignpdf');
```
With specific Java or JSignPdf version:
```php
$params->getJSignPdfDownloadUrl('the url to download the zip here');
$params->setJavaDownloadUrl('the url to download the .tar.gz here');
```

Without JSignPDF bin:
```bash
composer require jsignpdf/jsignpdf-bin
```

File signed as base64:
```php
$param->setIsOutputTypeBase64(true);
```

Change temp directory:
```php
$param->setTempPath('/path/temp/to/sign/files/');
```

Change parameters of JSignPDF:
```php
$param->setJSignParameters("-a -kst PKCS12 -ts https://freetsa.org/tsr");
```

A string is passed to JSignPdf as written, so it is the one place where the
content is not escaped for you. Never build one from untrusted input. To let
the package escape the values, pass a list of options and values instead:

```php
$param->setJSignParameters(['-kst', 'PKCS12', '-ts', 'https://freetsa.org/tsr']);
```

## Passwords

Besides the certificate password of `setPassword()`, JSignPdf takes a password
for the private key, for encrypted documents and for the timestamping server.
None of them is passed on the command line, where any user of the machine could
read it from `ps` or `/proc/<pid>/cmdline`: the package sends every one of them
to JSignPdf through stdin.

```php
$param->setKeyPassword('private key password');      // -kp
$param->setOwnerPassword('owner password');          // -opwd
$param->setUserPassword('user password');            // -upwd
$param->setTsaCertPassword('tsa cert password');     // -tscp
$param->setTsaPassword('tsa password');              // -tsp
```

Passing one of those options to `setJSignParameters()` as a list works too, and
the value is taken out of the command line just the same:

```php
$param->setJSignParameters(['-ts', 'https://freetsa.org/tsr', '-ta', 'PASSWORD', '-tsu', 'jhon', '-tsp', 'tsa password']);
```

The string form is the exception, as nothing in it is parsed: a password
written there does reach the command line. Use a setter or a list for it.

## JSignPdf 3.x

This package targets JSignPdf 3.x, which needs a Java 21+ runtime. JSignPdf 2.x
is no longer supported: the certificate password is now sent to JSignPdf
through stdin, and 2.x has no option to read it from there. Pointing
`setJSignPdfDownloadUrl()` or `setJSignPdfPath()` at a 2.x release stops
working.

Two changes of JSignPdf 3.1 are worth knowing about:

- the default hash algorithm is now SHA-256, which requires at least a PDF-1.6;
- the CLI appends the signature by default, and the append mode cannot upgrade
  the PDF version.

Together they make signing a PDF older than 1.6 fail with the default
parameters. To sign such a file, either turn off the append mode with
`--overwrite` or pick an algorithm the PDF version supports:

```php
$param->setJSignParameters('-kst PKCS12 --overwrite');
```

The `-a` flag is kept by JSignPdf 3.1 as a no-op.

## Docker Environment

The repository ships a minimal Docker setup (`Dockerfile` and `compose.yml`) with the extensions
the test suite needs.

```bash
docker compose build
docker compose run --rm php composer install
```

Checks, the same ones the CI runs:

```bash
docker compose run --rm php composer run test:unit
docker compose run --rm php composer run cs:check   # cs:fix to apply the formatting
docker compose run --rm php composer run psalm
```

### Usage example

To sign a PDF end to end. It downloads the JRE and JSignPdf into `tmp/` on the first run:

```bash
docker compose run --rm php php example/index.php
```

## Credits
- [Jeidison Farias](https://github.com/jeidison)
