<?php

declare(strict_types=1);

use Liberu\PackageTestbench\Tests\Fixtures\FixturePackage;

/*
 * Run the shipped boundary suites here, against a fixture package.
 *
 * They find the package under test from the working directory, because in a
 * consumer they execute from inside vendor/. So running them means standing in
 * a package: each suite gets a throwaway fixture of the kind it is written for.
 *
 * It has to be `beforeAll`. The test case reads the package's dependencies when
 * it builds the application, which happens in `setUp` — before any `beforeEach`
 * hook runs. A consumer never notices, because its working directory is its own
 * package root for the whole run.
 *
 * This file is not shipped-and-run: a consumer's phpunit.xml points at
 * tests/Boundary/<Kind> alone, and Pest reads its Pest.php from the consumer's
 * own tests directory. The hooks below apply to this repository's run only.
 *
 * Without them, the one thing this package exists to distribute is the one thing
 * its CI never executes — a shipped suite that did not even load was found by a
 * 44-package fleet sweep instead of by its own suite.
 */

$previous = null;

$enter = function (string $fixture) use (&$previous): void {
    $previous = getcwd();
    chdir($fixture);
};

$restore = function () use (&$previous): void {
    if (is_string($previous)) {
        chdir($previous);
    }
};

pest()
    ->beforeAll(function () use ($enter) {
        // A domain module with source to read: the Filament rule scans src/, and an
        // empty package would let it pass having examined nothing.
        $enter(FixturePackage::module([
            'manifest' => ['category' => 'foundation'],
            'files' => ['src/Service.php' => "<?php\n\nuse Illuminate\\Support\\Str;\n"],
        ]));
    })
    ->afterAll($restore)
    ->in('Boundary/Module');

pest()
    ->beforeAll(function () use ($enter) {
        $enter(FixturePackage::theme());
    })
    ->afterAll($restore)
    ->in('Boundary/Theme');

pest()
    ->beforeAll(function () use ($enter) {
        $enter(FixturePackage::contract());
    })
    ->afterAll($restore)
    ->in('Boundary/Contract');
