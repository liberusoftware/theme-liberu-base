<?php

declare(strict_types=1);

use Liberu\PackageTestbench\PackageRoot;
use Liberu\PackageTestbench\Tests\Fixtures\FixturePackage;

it('locates a package from a directory inside it', function () {
    $root = FixturePackage::module(['files' => ['src/Deep/Nested/Thing.php' => '<?php']]);

    expect(PackageRoot::locate($root.'/src/Deep/Nested'))->toBe($root);
});

it('locates a package from a file inside it', function () {
    $root = FixturePackage::module(['files' => ['src/Thing.php' => '<?php']]);

    expect(PackageRoot::locate($root.'/src/Thing.php'))->toBe($root);
});

it('anchors on composer.json, so a package without a manifest is still found', function () {
    $root = FixturePackage::contract();

    expect(PackageRoot::locate($root.'/src'))->toBe($root)
        ->and(PackageRoot::manifest($root))->toBeNull();
});

it('fails when nothing above the path is a package', function () {
    PackageRoot::locate('/');
})->throws(RuntimeException::class);

it('reads whichever manifest the package ships', function (string $kind, string $expected) {
    $root = FixturePackage::$kind();

    expect(PackageRoot::manifest($root)['name'])->toBe($expected);
})->with([
    ['module', 'fixture'],
    ['theme', 'fixture'],
]);

it('derives the vendor prefix from the package name rather than hardcoding it', function (string $name, string $expected) {
    $root = FixturePackage::module(['composer' => ['name' => $name]]);

    expect(PackageRoot::vendor($root))->toBe($expected);
})->with([
    ['liberusoftware/search', 'liberusoftware/'],
    ['liberu/search', 'liberu/'],
    ['someone-else/search', 'someone-else/'],
]);

it('refuses a package whose Composer name is unusable', function (mixed $name) {
    $root = FixturePackage::module(['composer' => ['name' => $name]]);

    PackageRoot::vendor($root);
})->with([[''], ['no-slash'], [null]])->throws(RuntimeException::class);

/*
 * The two predicates below decide which boundary rules a package is subject to:
 * the shipped suite skips on them rather than branching inside an assertion. A
 * rule that stopped applying where it should would show up here as a wrong
 * answer, not as a suite that quietly asserted nothing.
 */

it('reads the category the boundary suite skips a Filament rule on', function (array $manifest, ?string $expected) {
    expect(PackageRoot::category(FixturePackage::module(['manifest' => $manifest])))->toBe($expected);
})->with([
    'presentation' => [['category' => 'presentation'], 'presentation'],
    'domain' => [['category' => 'foundation'], 'foundation'],
    'unstated' => [['category' => null], null],
]);

it('has no category for a package carrying no manifest at all', function () {
    expect(PackageRoot::category(FixturePackage::contract()))->toBeNull();
});

it('recognises the name suffixes that make a rule apply', function (string $name, string $suffix, bool $expected) {
    expect(PackageRoot::nameEndsWith(FixturePackage::module(['composer' => ['name' => $name]]), $suffix))->toBe($expected);
})->with([
    'adapter' => ['liberusoftware/search-api', '-api', true],
    'not an adapter' => ['liberusoftware/search', '-api', false],
    'presentation' => ['liberusoftware/settings-filament', '-filament', true],
    'domain' => ['liberusoftware/settings', '-filament', false],
    // `-api` must not match a package that merely contains the letters.
    'coincidence' => ['liberusoftware/api-access', '-api', false],
]);

it('reports malformed JSON against the file that holds it', function () {
    $root = FixturePackage::module();
    file_put_contents($root.'/module.json', '{ not json');

    PackageRoot::manifest($root);
})->throws(RuntimeException::class, 'module.json');
