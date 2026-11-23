# AGENTS.md

This file provides guidance to coding agents (Claude Code, Codex, etc.) when working with code in this repository.

## Keeping this file current

Whenever a change makes any guidance here outdated, update this file in the same pull request. Keep it limited to stable operational guidance — conventions, compatibility requirements, commands, testing patterns and constraints. Implementation details (classes, methods, internal behavior) do not belong here: they go stale on the first refactor, and an agent may mistake a current limitation for a rule to preserve.

## What this is

A thin PHP wrapper around [JSignPdf](http://jsignpdf.sourceforge.net/) (a Java CLI tool) for digitally signing PDFs with a PKCS#12 certificate. The library shells out to java and can download both the JRE and JSignPdf on demand.

Package name is `jsignpdf/jsignpdf-php`, but the PSR-4 namespace is `Jeidison\JSignPDF\` (`src/`) and `Jeidison\JSignPDF\Tests\` (`tests/`).

## Commands

Dev tooling lives in isolated `vendor-bin/*` directories managed by `bamarni/composer-bin-plugin`; `composer install` installs them all and creates bin-links in `vendor/bin`. The authoritative list of commands is the `scripts` section of `composer.json`.

```bash
composer install                 # deps + all vendor-bin tools
composer run test:unit           # PHPUnit (fails on warning/risky)
composer run test:integration    # PHPUnit, integration group only
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

JSignPdf 3.x is the supported target and needs a Java 21+ runtime. Two distribution layouts have to keep working: the fat jar shipped up to 3.0.x, and the `lib/` directory shipped since 3.1, which is started from the classpath instead. JSignPdf 2.x is not supported — it has no way to read passwords from stdin.

The default JRE and JSignPdf downloads are pinned by SHA-256. Changing a default download URL means changing its SHA-256 in the same commit, taken from the digest the release publishes.

The package's major version follows the JSignPdf major it supports. Minor and patch follow semver for the package's own API: a release that adds public API is a minor, one with fixes only is a patch. Releases keep three numbers, since Composer reads `v3.2.1` and `v3.2.1.0` as the same version.

## Constraints

Everything reaching a shell must go through `escapeshellarg()`. Secrets must never be passed through argv — use stdin instead. JSignPdf reads a password from stdin when the option value is `-` and `--enable-stdin-passwords` is set. Every password option goes through it, not only the keystore one: values are read one line each, in the fixed order `-ksp`, `-kp`, `-opwd`, `-upwd`, `-tscp`, `-tsp`, so `JSignParam::getPasswords()` keeps that order and `JSignService` writes the lines in it. `setJSignParameters()`/`addJSignParameters()` only accept an array where an option that takes a value is keyed by the option (`'-kst' => 'PKCS12'`) and a flag is an item without a key, which the package escapes; there is no string form to bypass that. The keys are what tells an option from its value, so a value that looks like a password option (`'--l2-text' => '-tsp'`) is never taken as one. JSignPdf's parser reads an argument that looks like an option as an option even right after one that takes a value, so an option and its value reach JSignPdf as a single argument in the long form, `--keystore-type=PKCS12`, whether the key is the short or the long name. The parser accepts `=` only after a long name (`-kst=PKCS12` is an unrecognized option), so an option that has no long name (`-llx`, `-lly`, `-urx`, `-ury`) keeps its value as a separate argument. The package does not keep its own list of valid options, because a custom JSignPdf 3.x runtime can declare other ones: an unknown option, a flag given a value or an option that takes a value given as a flag reaches JSignPdf, which rejects the command, and its message is part of the exception. A password option given without its value, and the keystore password (which belongs to `setPassword()`), are rejected with an `InvalidArgumentException` so that no password reaches argv through them. A password cannot contain a line break either, because each one is read as a single line from stdin; the setters and the parameters reject it the same way. The temporary PDF and certificate files the package writes are created readable only by their owner and never reuse a path that already exists, so the temporary path has to be a local directory; their name and the password used to repack a certificate come from `random_bytes()`. `setJavaPath()` takes only the `java` executable path; JVM options and environment variables for the process that runs it have their own setters (`setJavaOptions()`, `setEnvironmentVariables()`) instead of being folded into the path.

## Testing patterns

- `tests/` mirrors the `src/` tree. Preserve the same relative path and append `Test` to the source class name. For example, `src/Runtime/JavaRuntimeService.php` is covered by `tests/Runtime/JavaRuntimeServiceTest.php`. `tests/Builder/`, `tests/resources/` and `tests/Integration/` are examples of support directories outside this mirror.
- `tests/Integration/` holds the tests that run the real JSignPdf. They are tagged with `#[Group('integration')]`, excluded from `test:unit` and run by `test:integration`. They need network access and download the JRE and JSignPdf into `tmp/` on the first run. CI runs them in their own job, separate from the PHP version matrix.
- Shell calls are covered by declaring `exec()`, `proc_open()` and `proc_close()` functions inside the tested namespace, shadowing the global ones for that file, driven by a `$mockExec` global set per test (see `tests/JSignPDFTest.php`). The `proc_open()` shadow records the command in `$mockProcCommand`, the environment it received in `$mockProcEnv`, and writes the stdin it receives to `$mockProcStdinFile`; the `proc_close()` shadow returns `$mockProcExitCode` (0 by default), so tests can assert both what was passed as arguments and what was kept out of them, and simulate a failing exit code.
- The mapping of short options to long ones is checked against the options printed by the help of the pinned JSignPdf in `tests/Integration/`, so updating the pinned version fails there if the options changed.
- `vfsStream` fakes the filesystem (temp paths, unwritable directories, ownership) and `donatj/mock-webserver` fakes the JRE/jar http download endpoints.
- `tests/Builder/JSignParamBuilder::withDefault()` returns a `JSignParam` preloaded with `tests/resources/certificado.pfx` (password `123`) and `tests/resources/pdf-test.pdf`.
- Psalm's baseline is `tests/psalm-baseline.xml` with `findUnusedBaselineEntry` on — removing an error means the baseline entry must go too and is updated by `composer psalm:update-baseline`.
