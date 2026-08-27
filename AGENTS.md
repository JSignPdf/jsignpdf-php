# AGENTS.md

This file provides guidance to coding agents (Claude Code, Codex, etc.) when working with code in this repository.

## What this is

A thin PHP wrapper around [JSignPdf](http://jsignpdf.sourceforge.net/) (a Java CLI tool) for digitally signing PDFs with a PKCS#12 certificate. The library shells out to `java -jar JSignPdf.jar` and can download both the JRE and the JSignPdf jar on demand.

Package name is `jsignpdf/jsignpdf-php`, but the PSR-4 namespace is `Jeidison\JSignPDF\` (`src/`) and `Jeidison\JSignPDF\Tests\` (`tests/`).

## Commands

Dev tooling lives in isolated `vendor-bin/*` directories managed by `bamarni/composer-bin-plugin`; `composer install` installs them all and creates bin-links in `vendor/bin`.

```bash
composer install                 # deps + all vendor-bin tools
composer run test:unit           # PHPUnit (fails on warning/risky)
composer run test:coverage       # with xdebug coverage
composer run cs:check            # php-cs-fixer dry-run (CI lint)
composer run cs:fix              # apply formatting
composer run psalm               # static analysis, errorLevel 8
composer run psalm:update-baseline

# single test / single method
vendor/bin/phpunit --filter testSignSuccess
vendor/bin/phpunit tests/Runtime/JavaRuntimeServiceTest.php
```

`example/index.php` is a runnable end-to-end smoke test (generates a self-signed cert, signs `tests/resources/pdf-test.pdf`, writes to `tmp/`). It needs a real Java + jar, so it actually downloads them on first run.

CI (on PRs only) runs php-cs-fixer, psalm, and PHPUnit on PHP 8.1–8.4. Minimum supported version is PHP 8.1 and `composer.json` pins `config.platform.php` to 8.1 — don't use syntax or stdlib newer than that.

## Architecture

Flow of a `sign()` call:

1. `JSignPDF` (`src/JSignPDF.php`) — public facade, holds a `JSignParam` and delegates to `JSignService`.
2. `JSignParam` (`src/Sign/JSignParam.php`) — fluent setter/getter value object holding *file contents* (not paths) for the PDF and the `.pfx`, plus every path/URL knob. Its constructor generates a random `tempName` and defaults `tempPath` to `src/../tmp/`, with `javaPath` and `jSignPdfJarPath` derived from it. It also carries the default download URLs (Temurin JRE 21 tarball, JSignPdf 2.3.0 zip).
3. `JSignService` (`src/Sign/JSignService.php`) — the core. Validates params, resolves the Java and jar paths through the runtime services, writes the PDF and certificate to temp files, builds and `exec()`s the CLI command, then reads back `<tempName>_signed.pdf` and deletes all temp files (also on failure, in the `catch`).
4. `JSignFileService` (`src/JSignFileService.php`) — read/write/delete of the temp files.

Success is detected by string-matching `"Finished: Signature succesfully created."` in the command output (note the typo — it comes from JSignPdf itself); anything else becomes an `Exception` carrying the raw output.

### Runtime resolution (`src/Runtime/`)

Both `JavaRuntimeService` and `JSignPdfRuntimeService` follow the same `getPath(JSignParam)` contract and the same precedence:

- Java: `isUseJavaInstalled` → literal `java`; else a `javaPath` with no download URL → used as-is; else path + URL → download and extract on demand; else throw.
- Jar: an existing `jSignPdfJarPath` with no download URL → used as-is; else path + URL → download/extract on demand; else throw.

Downloads are cached by a marker file next to the binary (`.java_version_<basename-of-url>` / `.jsignpdf_version_<basename-of-url>`), so changing the download URL invalidates the cache and re-downloads. Extraction uses `PharData` for the `.tar.gz` and `ZipArchive` for the zip, then shells out to `mv`/`rm` to flatten the archive's root directory.

### Certificate handling quirks

`JSignService::pkcs12Read()` has two workarounds that are easy to break:

- **Legacy PKCS#12 / OpenSSL 3**: when `openssl_pkcs12_read` fails with `error:0308010C:digital envelope routines::unsupported`, the cert is repacked via the `openssl pkcs12 -legacy` CLI (password passed through stdin, never argv) and the repacked content is written back into the `JSignParam`.
- **Non-ASCII passwords**: JSignPdf's CLI mishandles them, so the certificate is re-exported in memory under a fresh random password and both the password and certificate on the `JSignParam` are swapped out.

Because of this, `JSignParam` is mutated during signing — treat it as single-use per sign.

Everything reaching a shell must go through `escapeshellarg()` (see `commandSign()` and `safeExec()`).

## Testing notes

- `tests/JSignPDFTest.php` declares a `Jeidison\JSignPDF\Sign\exec()` function at the top of the file that shadows the global one for that namespace, driven by a `$mockExec` global set per test. Set `$mockExec = ['Finished: Signature succesfully created.']` to simulate a successful sign; `setUp()` resets it to `null`.
- `vfsStream` fakes the filesystem (temp paths, unwritable directories, ownership) and `donatj/mock-webserver` fakes the JRE/jar download endpoints.
- `tests/Builder/JSignParamBuilder::withDefault()` returns a `JSignParam` preloaded with `tests/resources/certificado.pfx` (password `123`) and `tests/resources/pdf-test.pdf`.
- Psalm's baseline is `tests/psalm-baseline.xml` with `findUnusedBaselineEntry` on — removing an error means the baseline entry must go too.
