<?php

declare(strict_types=1);

use Liberu\PackageTestbench\BoundaryAssertions;
use Liberu\PackageTestbench\PackageRoot;
use Liberu\PackageTestbench\PackageTestCase;

/*
 * Shipped boundary suite for `liberu-module` packages.
 *
 * A module repository ships no boundary test files of its own. Its phpunit.xml
 * points a testsuite here:
 *
 *   <directory suffix="Test.php">vendor/liberusoftware/package-testbench/tests/Boundary/Module</directory>
 *
 * so a new rule is a testbench release every repository picks up, rather than a
 * change applied by hand across the fleet. Opting out of a rule means editing
 * that phpunit.xml.
 *
 * The package under test is found from the working directory, because these
 * files execute from inside vendor/.
 *
 * Three rules apply to one kind of module only. Their condition lives in a
 * `skip()` rather than a guard clause inside the assertion, so a module the rule
 * does not cover reports as skipped with its reason — a guard would leave the
 * test passing having asserted nothing, which Pest reports as risky and a reader
 * reports as coverage.
 */

uses(PackageTestCase::class);

$root = fn (): string => PackageRoot::locate((string) getcwd());

it('exposes internally consistent package metadata', function () {
    BoundaryAssertions::moduleMetadataIsConsistent(PackageRoot::locate((string) getcwd()));
});

it('ships every file a package repository requires', function () {
    BoundaryAssertions::shipsRequiredFiles(PackageRoot::locate((string) getcwd()));
});

it('registers its declared service provider with the application', function () {
    BoundaryAssertions::declaredProviderRegisters($this->app, PackageRoot::locate((string) getcwd()));
});

it('does not depend on the host application', function () {
    BoundaryAssertions::doesNotDependOnHostApplication(PackageRoot::locate((string) getcwd()));
});

it('keeps Filament out of a domain module', function () use ($root) {
    BoundaryAssertions::keepsFilamentOutOfDomain($root());
})->skip(
    fn (): bool => PackageRoot::category($root()) === 'presentation',
    'A presentation module is where Filament belongs.',
);

it('does not import domain models into an api adapter', function () use ($root) {
    BoundaryAssertions::apiAdapterAvoidsDomainModels($root());
})->skip(
    fn (): bool => ! PackageRoot::nameEndsWith($root(), '-api'),
    'Not an -api adapter.',
);

it('declares panel plugins when it is a filament module', function () use ($root) {
    BoundaryAssertions::presentationDeclaresPanelPlugins($root());
})->skip(
    fn (): bool => ! PackageRoot::nameEndsWith($root(), '-filament'),
    'Not a -filament module.',
);
