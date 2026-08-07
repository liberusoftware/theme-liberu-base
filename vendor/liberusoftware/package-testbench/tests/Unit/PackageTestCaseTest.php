<?php

declare(strict_types=1);

use Liberu\PackageTestbench\PackageTestCase;
use Liberu\PackageTestbench\Tests\Fixtures\FixturePackage;
use Liberu\PackageTestbench\Tests\Fixtures\FixtureServiceProvider;

/*
 * Testbench runs no package discovery, so what a package's own suite boots is
 * decided here. A module whose provider reaches for a Livewire binding could not
 * boot at all until this resolved its dependencies' providers too.
 */

/**
 * The provider list the case would hand Testbench, from inside the given package.
 *
 * @return array<int, string>
 */
function providersFor(string $root): array
{
    $previous = (string) getcwd();
    chdir($root);

    try {
        $case = new class('boundary') extends PackageTestCase {};

        return (fn (): array => $this->getPackageProviders(null))->call($case);
    } finally {
        chdir($previous);
    }
}

/**
 * A dependency as Composer installs it: a directory under the package's own
 * vendor/, carrying the composer.json it was published with.
 *
 * @param  array<string, mixed>  $composer
 * @return array<string, string>
 */
function installedDependency(string $name, array $composer): array
{
    return ['vendor/'.$name.'/composer.json' => (string) json_encode($composer + ['name' => $name])];
}

it('boots only its own provider when it depends on nothing', function () {
    expect(providersFor(FixturePackage::module()))->toBe([FixtureServiceProvider::class]);
});

// This is Laravel's own package discovery, scoped to what the package requires.
it('boots the providers a third-party dependency declares', function () {
    $root = FixturePackage::module([
        'composer' => ['require' => ['php' => '^8.5', 'livewire/livewire' => '^4.0']],
        'manifest' => ['requires' => ['packages' => []]],
        'files' => installedDependency('livewire/livewire', [
            'extra' => ['laravel' => ['providers' => ['Livewire\\LivewireServiceProvider']]],
        ]),
    ]);

    expect(providersFor($root))->toBe(['Livewire\\LivewireServiceProvider', FixtureServiceProvider::class]);
});

// A sibling module declares that array empty precisely so installing it never
// boots it. Honouring that is what keeps enablement an explicit decision.
it('does not boot a sibling module it requires at runtime', function () {
    $root = FixturePackage::module([
        'files' => installedDependency('liberusoftware/other', [
            'extra' => ['laravel' => ['providers' => []]],
        ]) + ['vendor/liberusoftware/other/module.json' => (string) json_encode(['provider' => 'Liberu\\Other\\OtherServiceProvider'])],
    ]);

    expect(providersFor($root))->toBe([FixtureServiceProvider::class]);
});

// A dev requirement on a sibling is a statement about what this package is
// tested against — the only way an adapter can boot against a real
// implementation of the contract it depends on.
it('boots a sibling module it requires for development', function () {
    $root = FixturePackage::module([
        'composer' => ['require-dev' => ['liberusoftware/other' => '^1.0']],
        'files' => ['vendor/liberusoftware/other/composer.json' => (string) json_encode(['name' => 'liberusoftware/other'])]
            + ['vendor/liberusoftware/other/module.json' => (string) json_encode(['provider' => 'Liberu\\Other\\OtherServiceProvider'])],
    ]);

    expect(providersFor($root))->toBe(['Liberu\\Other\\OtherServiceProvider', FixtureServiceProvider::class]);
});

it('ignores a declared dependency that is not installed', function () {
    $root = FixturePackage::module([
        'composer' => ['require-dev' => ['liberusoftware/absent' => '^1.0']],
    ]);

    expect(providersFor($root))->toBe([FixtureServiceProvider::class]);
});
