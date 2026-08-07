<?php

declare(strict_types=1);

use Liberu\PackageTestbench\BoundaryAssertions;
use Liberu\PackageTestbench\Tests\Fixtures\FixturePackage;
use Liberu\PackageTestbench\Tests\Fixtures\FixtureServiceProvider;
use Liberu\PackageTestbench\Tests\Fixtures\NotAServiceProvider;
use PHPUnit\Framework\AssertionFailedError;

describe('module metadata', function () {
    it('accepts a well-formed module', function () {
        BoundaryAssertions::moduleMetadataIsConsistent(FixturePackage::module());
    });

    it('rejects a module whose metadata disagrees with itself', function (array $overrides) {
        BoundaryAssertions::moduleMetadataIsConsistent(FixturePackage::module($overrides));
    })->with([
        'wrong composer type' => [['composer' => ['type' => 'library']]],
        'version mismatch' => [['composer' => ['version' => '2.0.0']]],
        'installer name mismatch' => [['composer' => ['extra' => ['liberu' => ['name' => 'other']]]]],
        'provider does not exist' => [['manifest' => ['provider' => 'Liberu\\Nope\\Missing']]],
        'provider is not a provider' => [['manifest' => ['provider' => NotAServiceProvider::class]]],
        'auto-registers its provider' => [['composer' => ['extra' => ['laravel' => ['providers' => ['X']]]]]],
    ])->throws(AssertionFailedError::class);

    // The host's Manifest parser requires the key but never checks its shape, so a
    // version Composer cannot order otherwise surfaces at release time. Both files
    // carry it, because agreeing on a malformed version is not consistency.
    it('rejects a version that is not MAJOR.MINOR.PATCH', function (string $version) {
        BoundaryAssertions::moduleMetadataIsConsistent(FixturePackage::module([
            'composer' => ['version' => $version],
            'manifest' => ['version' => $version],
        ]));
    })->with(['1.0', 'v1.0.0', '1.0.0-beta.1', '1.0.0.0', ''])->throws(AssertionFailedError::class);

    it('rejects a module whose manifest and Composer requirements disagree', function () {
        BoundaryAssertions::moduleMetadataIsConsistent(FixturePackage::module([
            'composer' => ['require' => ['php' => '^8.5', 'liberusoftware/other' => '^2.0']],
        ]));
    })->throws(AssertionFailedError::class);

    // `module:features` folds case when searching, so a list that is empty makes the
    // module undiscoverable and one carrying two casings of the same feature reports a
    // duplicate hit. The host rejects both, but only once a composition installs the
    // module — here the owning repository finds out first.
    it('rejects an unusable feature list', function (array $features) {
        BoundaryAssertions::moduleMetadataIsConsistent(FixturePackage::module([
            'manifest' => ['features' => $features],
        ]));
    })->with([
        'no features' => [[]],
        'empty feature' => [['']],
        'padded feature' => [[' search ']],
        'duplicate feature' => [['search', 'search']],
        'duplicate ignoring case' => [['Search', 'search']],
        'non-string feature' => [[42]],
    ])->throws(AssertionFailedError::class);

    // The prototype's hardcoded prefix failed 43 packages under one vendor and
    // passed 44 under the other. Deriving it is what makes a vendor rename safe.
    it('compares sibling requirements under whatever vendor the package uses', function (string $vendor) {
        BoundaryAssertions::moduleMetadataIsConsistent(FixturePackage::module([], $vendor));
    })->with(['liberusoftware', 'liberu', 'someone-else']);
});

describe('module source boundaries', function () {
    it('accepts a module whose src reaches for nothing it should not', function () {
        $root = FixturePackage::module(['files' => ['src/Service.php' => "<?php\n\nuse Illuminate\\Support\\Str;\n"]]);

        expect(function () use ($root) {
            BoundaryAssertions::doesNotDependOnHostApplication($root);
            BoundaryAssertions::keepsFilamentOutOfDomain($root);
            BoundaryAssertions::apiAdapterAvoidsDomainModels($root);
        })->not->toThrow(AssertionFailedError::class);
    });

    // `App\` is the consuming application's namespace, so a module importing it is
    // installable in exactly one application — the opposite of being a package.
    it('rejects a module reaching into the host application', function (string $source) {
        BoundaryAssertions::doesNotDependOnHostApplication(
            FixturePackage::module(['files' => ['src/Service.php' => $source]]),
        );
    })->with([
        'import' => ["<?php\n\nuse App\\Models\\User;\n"],
        'instantiation' => ["<?php\n\n\$u = new App\\Models\\User();\n"],
        'inheritance' => ["<?php\n\nclass A extends App\\Base {}\n"],
        'implementation' => ["<?php\n\nclass A implements App\\Contract {}\n"],
    ])->throws(AssertionFailedError::class);

    it('rejects Filament inside a domain module', function () {
        BoundaryAssertions::keepsFilamentOutOfDomain(FixturePackage::module([
            'manifest' => ['category' => 'foundation'],
            'files' => ['src/Resource.php' => "<?php\n\nuse Filament\\Resources\\Resource;\n"],
        ]));
    })->throws(AssertionFailedError::class);

    it('rejects a domain model imported into an api adapter', function () {
        BoundaryAssertions::apiAdapterAvoidsDomainModels(FixturePackage::module([
            'composer' => ['name' => 'liberusoftware/fixture-api'],
            'files' => ['src/Controller.php' => "<?php\n\nuse Liberu\\Foundation\\Search\\Models\\Index;\n"],
        ]));
    })->throws(AssertionFailedError::class);
});

/*
 * Which packages these two rules apply to is not decided here. The shipped
 * boundary suite skips them on `PackageRoot::category()` and
 * `PackageRoot::nameEndsWith()`, covered in PackageRootTest — a guard clause
 * here would instead leave the rule passing having asserted nothing, which is
 * how a rule that no longer fires goes unnoticed.
 */
describe('presentation packages', function () {
    // A presentation package declaring no plugin is installed, booted and invisible.
    it('rejects a filament module that contributes no panel UI', function (array $manifest) {
        BoundaryAssertions::presentationDeclaresPanelPlugins(FixturePackage::module([
            'composer' => ['name' => 'liberusoftware/fixture-filament'],
            'manifest' => $manifest,
        ]));
    })->with([
        'wrong category' => [['category' => 'foundation', 'presentation' => ['filament' => ['admin' => ['X']]]]],
        'no plugins at all' => [['category' => 'presentation']],
        'panel with none' => [['category' => 'presentation', 'presentation' => ['filament' => ['admin' => []]]]],
        'plugin does not exist' => [['category' => 'presentation', 'presentation' => ['filament' => ['admin' => ['Liberu\\Nope\\Missing']]]]],
    ])->throws(AssertionFailedError::class);

    it('accepts a filament module declaring a real plugin', function () {
        BoundaryAssertions::presentationDeclaresPanelPlugins(FixturePackage::module([
            'composer' => ['name' => 'liberusoftware/fixture-filament'],
            'manifest' => ['category' => 'presentation', 'presentation' => ['filament' => ['admin' => [FixtureServiceProvider::class]]]],
        ]));
    });
});

describe('theme metadata', function () {
    it('accepts a well-formed theme', function () {
        $root = FixturePackage::theme();

        BoundaryAssertions::themeMetadataIsConsistent($root);
        BoundaryAssertions::themeShipsDeclaredAssets($root);
    });

    // theme.json has no `requires` key by design, so the module assertion must
    // not be reused for themes — it would fail every theme in the fleet.
    it('does not require a themes manifest to declare sibling packages', function () {
        BoundaryAssertions::themeMetadataIsConsistent(FixturePackage::theme());
    });

    it('rejects a theme that declares an asset it does not ship', function () {
        BoundaryAssertions::themeShipsDeclaredAssets(FixturePackage::theme([
            'manifest' => ['assets' => ['css' => ['resources/css/missing.css']]],
        ]));
    })->throws(AssertionFailedError::class);

    it('rejects a theme that declares no assets at all', function () {
        BoundaryAssertions::themeShipsDeclaredAssets(FixturePackage::theme([
            'manifest' => ['assets' => ['css' => [], 'js' => []]],
        ]));
    })->throws(AssertionFailedError::class);
});

describe('contract packages', function () {
    it('accepts a plain, framework-free library', function () {
        $root = FixturePackage::contract();

        BoundaryAssertions::contractMetadataIsConsistent($root);
        BoundaryAssertions::contractIsFrameworkFree($root);
    });

    it('rejects a contract package that ships a manifest', function () {
        $root = FixturePackage::contract();
        file_put_contents($root.'/module.json', '{"name":"fixture"}');

        BoundaryAssertions::contractMetadataIsConsistent($root);
    })->throws(AssertionFailedError::class);

    it('rejects a contract package that requires the framework', function () {
        BoundaryAssertions::contractIsFrameworkFree(FixturePackage::contract([
            'composer' => ['require' => ['php' => '^8.5', 'illuminate/support' => '^13.0']],
        ]));
    })->throws(AssertionFailedError::class);

    it('rejects a contract package that imports the framework', function (string $import) {
        BoundaryAssertions::contractIsFrameworkFree(FixturePackage::contract([
            'files' => ['src/Thing.php' => "<?php\n\nuse {$import};\n"],
        ]));
    })->with([
        'Illuminate\\Support\\Collection',
        'Filament\\Panel',
        'Livewire\\Component',
        'Laravel\\Sanctum\\Sanctum',
    ])->throws(AssertionFailedError::class);
});

describe('required files', function () {
    it('accepts a package shipping all of them', function () {
        BoundaryAssertions::shipsRequiredFiles(FixturePackage::module());
    });

    it('rejects a package missing one', function (string $file) {
        BoundaryAssertions::shipsRequiredFiles(FixturePackage::module(['remove' => [$file]]));
    })->with(['README.md', 'LICENSE.md', 'CHANGELOG.md'])->throws(AssertionFailedError::class);
});
