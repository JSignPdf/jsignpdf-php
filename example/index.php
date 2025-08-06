<?php

/**
 * @author Jeidison Farias <jeidison.farias@gmail.com>
 */
require_once __DIR__ . '/../vendor/autoload.php';

use Jeidison\JSignPDF\JSignPDF;
use Jeidison\JSignPDF\Sign\JSignParam;

$password = '123';

$privateKey = openssl_pkey_new([
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
]);

$csr = openssl_csr_new(['commonName' => 'John Doe'], $privateKey, ['digest_alg' => 'sha256']);
$x509 = openssl_csr_sign($csr, null, $privateKey, 365);

openssl_pkcs12_export(
    $x509,
    $pfxCertificateContent,
    $privateKey,
    $password,
);

$param = JSignParam::instance();

$param->setCertificate($pfxCertificateContent);
$param->setPdf(file_get_contents(__DIR__ . '/../tests/resources/pdf-test.pdf'));
$param->setPassword($password);

$jSignPdf = new JSignPDF($param);
$fileSigned = $jSignPdf->sign();
file_put_contents(__DIR__ . '/../tmp/file_signed.pdf', $fileSigned);
