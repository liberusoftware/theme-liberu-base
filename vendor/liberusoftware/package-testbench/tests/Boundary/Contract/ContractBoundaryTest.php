<?php

declare(strict_types=1);

use Liberu\PackageTestbench\BoundaryAssertions;
use Liberu\PackageTestbench\PackageRoot;

/*
 * Shipped boundary suite for contract packages and the Composer installer.
 *
 *   <directory suffix="Test.php">vendor/liberusoftware/package-testbench/tests/Boundary/Contract</directory>
 *
 * Deliberately does NOT use PackageTestCase. A contract package has no service
 * provider and no framework, so this suite boots nothing — it relies only on
 * PackageRoot and BoundaryAssertions, which are plain PHP.
 */

it('exposes internally consistent package metadata', function () {
    BoundaryAssertions::contractMetadataIsConsistent(PackageRoot::locate((string) getcwd()));
});

it('stays free of the framework', function () {
    BoundaryAssertions::contractIsFrameworkFree(PackageRoot::locate((string) getcwd()));
});

it('ships every file a package repository requires', function () {
    BoundaryAssertions::shipsRequiredFiles(PackageRoot::locate((string) getcwd()));
});
