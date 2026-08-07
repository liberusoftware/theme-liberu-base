<?php

declare(strict_types=1);

use Liberu\PackageTestbench\BoundaryAssertions;
use Liberu\PackageTestbench\PackageRoot;
use Liberu\PackageTestbench\PackageTestCase;

/*
 * Shipped boundary suite for `liberu-theme` packages.
 *
 *   <directory suffix="Test.php">vendor/liberusoftware/package-testbench/tests/Boundary/Theme</directory>
 *
 * Separate from the Module suite because theme.json carries no `requires` key
 * by design. One suite branching at runtime would let a theme run half its
 * assertions and still report green.
 */

uses(PackageTestCase::class);

it('exposes internally consistent package metadata', function () {
    BoundaryAssertions::themeMetadataIsConsistent(PackageRoot::locate((string) getcwd()));
});

it('ships every asset it declares', function () {
    BoundaryAssertions::themeShipsDeclaredAssets(PackageRoot::locate((string) getcwd()));
});

it('ships every file a package repository requires', function () {
    BoundaryAssertions::shipsRequiredFiles(PackageRoot::locate((string) getcwd()));
});

it('registers its declared service provider with the application', function () {
    BoundaryAssertions::declaredProviderRegisters($this->app, PackageRoot::locate((string) getcwd()));
});
