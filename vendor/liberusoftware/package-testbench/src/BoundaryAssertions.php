<?php

declare(strict_types=1);

namespace Liberu\PackageTestbench;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Assert;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The boundary assertions the fleet shares.
 *
 * These live here once rather than in 48 byte-identical copies, so adding a
 * rule is a testbench release every repository picks up rather than a sweep
 * across every repository.
 */
final class BoundaryAssertions
{
    /** Framework roots a contract package must never reach for. */
    private const FRAMEWORK_NAMESPACES = ['Illuminate\\', 'Filament\\', 'Livewire\\', 'Laravel\\'];

    /**
     * Every package repository ships these, whatever its kind. A repository
     * missing one is not publishable.
     */
    public static function shipsRequiredFiles(string $root): void
    {
        foreach (['composer.json', 'README.md', 'LICENSE.md', 'CHANGELOG.md'] as $file) {
            Assert::assertFileExists($root.'/'.$file, "Package at [{$root}] must ship {$file}.");
        }
    }

    /**
     * A module's `composer.json` and `module.json` must agree, and the sibling
     * packages the manifest declares must be exactly those Composer requires.
     *
     * The vendor prefix is derived from the package's own name, never
     * hardcoded — a constant would fail the whole fleet during a vendor rename.
     */
    public static function moduleMetadataIsConsistent(string $root): void
    {
        $composer = PackageRoot::composer($root);
        $manifest = PackageRoot::manifest($root);

        Assert::assertIsArray($manifest, "Module at [{$root}] must ship a module.json.");

        self::assertSharedMetadata($composer, $manifest, 'liberu-module', $root);

        $vendor = PackageRoot::vendor($root);
        $required = array_filter(
            $composer['require'] ?? [],
            static fn (string $constraint, string $package): bool => str_starts_with($package, $vendor),
            ARRAY_FILTER_USE_BOTH,
        );

        Assert::assertSame(
            $required,
            $manifest['requires']['packages'] ?? [],
            "Module at [{$root}] declares sibling packages in module.json that do not match its Composer requirements.",
        );

        // Enablement is an explicit decision: nothing may boot by Laravel's
        // package discovery just because Composer installed it.
        Assert::assertSame([], $composer['extra']['laravel']['providers'] ?? [], "Module at [{$root}] must not auto-register its provider.");

        self::assertFeaturesAreUsable($manifest, $root);
    }

    /**
     * A module's advertised feature list.
     *
     * `module:features` searches these case-insensitively, so a list that is empty
     * makes the module undiscoverable and one carrying the same feature in two
     * casings reports a duplicate hit. The host's `Manifest::fromFile()` rejects
     * both, but only once a composition installs the module — asserting it here is
     * what lets the owning repository find out first.
     *
     * @param  array<string, mixed>  $manifest
     */
    private static function assertFeaturesAreUsable(array $manifest, string $root): void
    {
        $features = $manifest['features'] ?? [];

        Assert::assertIsArray($features, "Module at [{$root}] must declare features as an array.");
        Assert::assertNotEmpty($features, "Module at [{$root}] must declare at least one feature.");

        foreach ($features as $feature) {
            Assert::assertIsString($feature, "Module at [{$root}] declares a non-string feature.");
            Assert::assertSame(trim($feature), $feature, "Module at [{$root}] declares feature [{$feature}] with surrounding whitespace.");
            Assert::assertNotSame('', $feature, "Module at [{$root}] declares an empty feature.");
        }

        $folded = array_map(mb_strtolower(...), $features);

        Assert::assertSame(
            count(array_unique($folded)),
            count($features),
            "Module at [{$root}] declares the same feature twice, ignoring case.",
        );
    }

    /**
     * A theme's manifest carries no `requires` key by design — themes declare a
     * parent and their assets, and let Composer own dependencies. That is why
     * this is a separate assertion rather than a branch inside the module one:
     * a shared assertion would let a theme skip half its checks and still pass.
     */
    public static function themeMetadataIsConsistent(string $root): void
    {
        $composer = PackageRoot::composer($root);
        $manifest = PackageRoot::manifest($root);

        Assert::assertIsArray($manifest, "Theme at [{$root}] must ship a theme.json.");

        self::assertSharedMetadata($composer, $manifest, 'liberu-theme', $root);
    }

    /** Every file a theme's manifest advertises must actually be in the package. */
    public static function themeShipsDeclaredAssets(string $root): void
    {
        $manifest = PackageRoot::manifest($root) ?? [];
        $assets = array_merge($manifest['assets']['css'] ?? [], $manifest['assets']['js'] ?? []);

        Assert::assertNotEmpty($assets, "Theme at [{$root}] declares no assets.");

        foreach ($assets as $asset) {
            Assert::assertFileExists($root.'/'.$asset, "Theme at [{$root}] declares [{$asset}] but does not ship it.");
        }
    }

    /**
     * A contract package is a plain library: no manifest, no provider, and no
     * framework. Its whole purpose is letting an adapter and its consumer agree
     * without either depending on the other's implementation.
     */
    public static function contractMetadataIsConsistent(string $root): void
    {
        $composer = PackageRoot::composer($root);

        Assert::assertSame('library', $composer['type'] ?? null, "Contract package at [{$root}] must be type library.");
        Assert::assertNull(PackageRoot::manifest($root), "Contract package at [{$root}] must not ship a module.json or theme.json.");
    }

    /** No framework may leak into a contract package, by requirement or by import. */
    public static function contractIsFrameworkFree(string $root): void
    {
        foreach (array_keys(PackageRoot::composer($root)['require'] ?? []) as $package) {
            Assert::assertNotEmpty($package);
            Assert::assertFalse(
                (bool) preg_match('#^(illuminate|laravel|filament|livewire)/#', (string) $package),
                "Contract package at [{$root}] must not require [{$package}].",
            );
        }

        foreach (self::phpFiles($root.'/src') as $file) {
            $source = (string) file_get_contents($file->getPathname());

            foreach (self::FRAMEWORK_NAMESPACES as $namespace) {
                Assert::assertFalse(
                    str_contains($source, 'use '.$namespace),
                    "Contract package at [{$root}] imports {$namespace} in [{$file->getFilename()}].",
                );
            }
        }
    }

    /**
     * The provider the manifest names must register with a real application —
     * the one assertion that proves the package is more than well-formed JSON.
     */
    public static function declaredProviderRegisters(Application $app, string $root): void
    {
        $provider = PackageRoot::manifest($root)['provider'] ?? null;

        Assert::assertIsString($provider, "Package at [{$root}] declares no provider.");

        // Assert it is registered rather than forcing a second registration: the
        // test case has already booted it, and a provider that contributes to a
        // registry throws on the duplicate. Booting is where the proof is — a
        // provider that cannot boot fails before any assertion here runs.
        $instance = $app->getProvider($provider);

        Assert::assertInstanceOf(
            ServiceProvider::class,
            $instance,
            "Package at [{$root}] declares provider [{$provider}], which the application did not register.",
        );
    }

    /**
     * A module may not reach into the application composing it.
     *
     * `App\` is the host's namespace. A module importing it is installable in
     * exactly one application, which is the opposite of being a package.
     */
    public static function doesNotDependOnHostApplication(string $root): void
    {
        foreach (self::phpFiles($root.'/src') as $file) {
            Assert::assertDoesNotMatchRegularExpression(
                '/(?:use|new|extends|implements)\s+App\\\\/',
                (string) file_get_contents($file->getPathname()),
                "Module at [{$root}] depends on the host application in [{$file->getFilename()}].",
            );
        }
    }

    /**
     * Filament belongs to `presentation` modules only.
     *
     * A domain module that imports Filament cannot be consumed headlessly, and
     * drags a UI framework into every composition that wanted only its domain.
     *
     * The caller decides whether the module is a domain one — the boundary suite
     * skips a presentation module rather than calling this and asserting nothing.
     */
    public static function keepsFilamentOutOfDomain(string $root): void
    {
        foreach (self::phpFiles($root.'/src') as $file) {
            Assert::assertStringNotContainsString(
                'Filament\\',
                (string) file_get_contents($file->getPathname()),
                "Non-presentation module at [{$root}] imports Filament in [{$file->getFilename()}].",
            );
        }
    }

    /**
     * An `-api` adapter speaks over the wire; it must not reach for persistence.
     *
     * Importing a domain `Models\` class couples the transport to the storage of
     * whichever package owns it, which is what the adapter exists to avoid.
     *
     * Only meaningful for an `-api` package; the boundary suite skips the rest.
     */
    public static function apiAdapterAvoidsDomainModels(string $root): void
    {
        foreach (self::phpFiles($root.'/src') as $file) {
            Assert::assertDoesNotMatchRegularExpression(
                '/use Liberu\\\\.+\\\\Models\\\\/',
                (string) file_get_contents($file->getPathname()),
                "API adapter at [{$root}] imports a domain model in [{$file->getFilename()}].",
            );
        }
    }

    /**
     * A `-filament` module exists to contribute panel UI, so it must say which.
     *
     * A presentation package declaring no plugin is installed, booted and
     * invisible — the failure mode is silence, which is why it is asserted.
     *
     * Only meaningful for a `-filament` package; the boundary suite skips the rest.
     */
    public static function presentationDeclaresPanelPlugins(string $root): void
    {
        $manifest = PackageRoot::manifest($root) ?? [];

        Assert::assertSame('presentation', $manifest['category'] ?? null, "Filament module at [{$root}] must be category presentation.");
        Assert::assertNotEmpty($manifest['presentation']['filament'] ?? [], "Filament module at [{$root}] declares no panel plugins.");

        foreach ($manifest['presentation']['filament'] as $panel => $plugins) {
            Assert::assertNotEmpty($plugins, "Filament module at [{$root}] declares the {$panel} panel with no plugins.");

            foreach ((array) $plugins as $plugin) {
                Assert::assertTrue(class_exists($plugin), "Filament module at [{$root}] declares [{$plugin}] for the {$panel} panel, which does not exist.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $composer
     * @param  array<string, mixed>  $manifest
     */
    private static function assertSharedMetadata(array $composer, array $manifest, string $type, string $root): void
    {
        Assert::assertSame($type, $composer['type'] ?? null, "Package at [{$root}] must be type {$type}.");
        Assert::assertSame($manifest['version'] ?? null, $composer['version'] ?? null, "Package at [{$root}] has a composer.json version disagreeing with its manifest.");

        // The host's manifest parser requires the key but never checks its shape, and a
        // version Composer cannot order is only discovered when someone tries to release
        // against it. Both halves are asserted: agreeing on a malformed version is not
        // consistency.
        Assert::assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+$/',
            (string) ($manifest['version'] ?? ''),
            "Package at [{$root}] declares a version that is not MAJOR.MINOR.PATCH.",
        );
        Assert::assertSame($manifest['name'] ?? null, $composer['extra']['liberu']['name'] ?? null, "Package at [{$root}] has an installer name disagreeing with its manifest.");

        $provider = $manifest['provider'] ?? null;

        Assert::assertIsString($provider, "Package at [{$root}] declares no provider.");
        Assert::assertTrue(class_exists($provider), "Package at [{$root}] declares provider [{$provider}], which does not exist.");
        Assert::assertTrue(is_subclass_of($provider, ServiceProvider::class), "Package at [{$root}] declares provider [{$provider}], which is not a service provider.");
    }

    /** @return list<SplFileInfo> */
    private static function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)));

        return array_values(array_filter($files, static fn (SplFileInfo $file): bool => $file->isFile() && $file->getExtension() === 'php'));
    }
}
