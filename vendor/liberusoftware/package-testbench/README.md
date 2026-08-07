# Liberu Package Testbench

> Shared test bootstrap and boundary suites for Liberu module, theme and contract packages.

[Software](https://liberusoftware.com) · [Hosting](https://liberuhosting.com) · [Services](https://liberuservices.com) · [Liberu Group](https://liberugroup.com)

[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://www.php.net/) [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE.md)

## Scope

Every Liberu package repository asserts the same handful of boundaries: its metadata is internally consistent, it ships the files a package repository must ship, and the provider its manifest names actually registers with an application. Before this package those assertions lived in byte-identical copies across the fleet, so adding a rule meant editing every repository.

They live here once. **Adding a boundary rule is a release of this package that every repository picks up on its next `composer update`.**

This package owns four things and nothing else:

| | |
|---|---|
| `PackageTestCase` | Testbench base case that registers the provider the manifest declares — no package writes a `getPackageProviders()` override |
| `PackageRoot` | Finds the package under test and reads its `composer.json` and manifest |
| `BoundaryAssertions` | The assertions themselves, as plain static calls |
| `tests/Boundary/{Module,Theme,Contract}` | Three shipped suites a consumer points its own test run at |

## Why three suites

The manifests genuinely differ, so one suite branching at runtime would let a package run half its assertions and still report green.

- **Module** — `module.json` declares `requires.packages`, which must match the sibling packages Composer requires.
- **Theme** — `theme.json` has no `requires` key at all. Themes declare a `parent` and their `assets`, and let Composer own dependencies.
- **Contract** — no manifest, no provider, no framework. This suite deliberately does **not** use `PackageTestCase`; it boots nothing.

## Requirements and installation

| Dependency | Supported version |
|---|---|
| `php` | `^8.5` |
| `orchestra/testbench` | `^11.1` |
| `pestphp/pest` | `^5.0` |

```bash
composer require --dev liberusoftware/package-testbench
```

Testbench and Pest come with it, so a consuming package drops both from its own `require-dev`.

## Wiring a package up

Point a testsuite at the directory matching your package's kind:

```xml
<testsuite name="Boundary">
    <directory suffix="Test.php">vendor/liberusoftware/package-testbench/tests/Boundary/Module</directory>
</testsuite>
```

**Pest requires a `tests/` directory at the package root**, even when every test it runs lives in `vendor/`. A package with tests of its own already has one. A package with *no* tests of its own — a contract package, typically — must instead name the suite directly:

```bash
vendor/bin/pest --test-directory=vendor/liberusoftware/package-testbench/tests/Boundary/Contract
```

Put whichever form applies in the repository's own `composer.json` script and its CI workflow. This package deliberately does not proxy test aliases.

The package under test is located from the working directory, so run the suite from the package root.

## The trade

Boundary tests are not visible in the package repository, and opting out of a rule means editing that `phpunit.xml`. That is the price of a rule change being one release rather than a sweep across every repository.

## Testing

```bash
composer update
vendor/bin/pint --test
vendor/bin/pest
```

The suite runs standalone. It exercises the assertions against throwaway package directories written to disk, because these assertions exist to read a package off disk — faking the disk would test nothing.

## Security

Do not report vulnerabilities through public issues. Email `security@liberusoftware.com` with reproduction details and the affected version.

## License

This package is open-source software under the [MIT License](LICENSE.md). The linked licence text is authoritative.

## Feedback and contributing

Focused issues and tested pull requests are welcome in the [GitHub repository](https://github.com/liberusoftware/package-testbench). Update tests, documentation, and `CHANGELOG.md` for user-visible changes.
