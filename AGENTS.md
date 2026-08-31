# AGENTS.md

This file provides guidance to coding agents (Claude Code, Codex, etc.) when working with code in this repository.

## Keeping this file current

Whenever a change makes any guidance here outdated, update this file in the same pull request. Keep it limited to stable operational guidance — conventions, compatibility requirements, commands, testing patterns and constraints. Implementation details (classes, methods, internal behavior) do not belong here: they go stale on the first refactor, and an agent may mistake a current limitation for a rule to preserve.

## What this is

A thin PHP wrapper around [JSignPdf](http://jsignpdf.sourceforge.net/) (a Java CLI tool) for digitally signing PDFs with a PKCS#12 certificate. The library shells out to `java -jar JSignPdf.jar` and can download both the JRE and the JSignPdf jar on demand.

Package name is `jsignpdf/jsignpdf-php`, but the PSR-4 namespace is `Jeidison\JSignPDF\` (`src/`) and `Jeidison\JSignPDF\Tests\` (`tests/`).

## Commands

Dev tooling lives in isolated `vendor-bin/*` directories managed by `bamarni/composer-bin-plugin`; `composer install` installs them all and creates bin-links in `vendor/bin`. The authoritative list of commands is the `scripts` section of `composer.json`.

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

## Compatibility

Minimum supported version is PHP 8.1 and `composer.json` pins `config.platform.php` to 8.1 — don't use syntax or stdlib newer than that. CI (on PRs only) runs php-cs-fixer, psalm, and PHPUnit on PHP 8.1–8.4.

## Constraints

Everything reaching a shell must go through `escapeshellarg()`. Secrets (certificate passwords in particular) must never be passed through argv — use stdin instead.

## Testing patterns

- `tests/` mirrors the `src/` tree. Preserve the same relative path and append `Test` to the source class name. For example, `src/Runtime/JavaRuntimeService.php` is covered by `tests/Runtime/JavaRuntimeServiceTest.php`. `tests/Builder/` and `tests/resources/` are examples of support directories outside this mirror.
- Shell calls are covered by declaring an `exec()` function inside the tested namespace, shadowing the global one for that file, driven by a `$mockExec` global set per test (see `tests/JSignPDFTest.php`).
- `vfsStream` fakes the filesystem (temp paths, unwritable directories, ownership) and `donatj/mock-webserver` fakes the JRE/jar download endpoints.
- `tests/Builder/JSignParamBuilder::withDefault()` returns a `JSignParam` preloaded with `tests/resources/certificado.pfx` (password `123`) and `tests/resources/pdf-test.pdf`.
- Psalm's baseline is `tests/psalm-baseline.xml` with `findUnusedBaselineEntry` on — removing an error means the baseline entry must go too.
