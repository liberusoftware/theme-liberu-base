<?php

declare(strict_types=1);

namespace Liberu\PackageTestbench;

use Orchestra\Testbench\TestCase;

/**
 * The one base case every Liberu module and theme package extends.
 *
 * It boots Testbench and registers the provider the manifest declares, so no
 * package writes a `getPackageProviders()` override and no package ships a
 * bootstrap of its own.
 *
 * Contract packages must not use this: they have no provider and are
 * deliberately framework-free, so their boundary suite boots nothing.
 */
abstract class PackageTestCase extends TestCase
{
    protected function packageRoot(): string
    {
        return PackageRoot::locate((string) getcwd());
    }

    /**
     * Testbench boots without an application key, so anything that renders a view
     * — a Livewire component, a mailable — dies on "No application encryption key
     * has been specified" rather than on anything the package did.
     *
     * A package overriding this **must call `parent::defineEnvironment($app)`**,
     * or it loses the key. The value is fixed rather than random: a test that
     * depends on the key being any particular thing is a test asserting on its
     * own fixture.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('liberu-testbench', 2)));
    }

    /**
     * The providers this package needs booted, its own last.
     *
     * Testbench runs no package discovery, so a package whose provider reaches
     * for a framework binding — Livewire's component finder, say — cannot boot
     * at all. Two sources supply the rest, and neither is a new convention:
     *
     * - `extra.laravel.providers` of any direct dependency. This is Laravel's
     *   own discovery, scoped to what the package actually requires. Sibling
     *   Liberu modules are excluded by their own manifests, which declare that
     *   array empty precisely so installation never implies boot.
     * - the manifest provider of a sibling declared in `require-dev`. A dev
     *   requirement on a sibling module is a statement about what this package
     *   is tested against; a runtime `require` is not, and booting one would
     *   contradict the enablement rule above.
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        $root = $this->packageRoot();
        $composer = PackageRoot::composer($root);
        $providers = [];

        foreach (['require', 'require-dev'] as $section) {
            foreach (array_keys($composer[$section] ?? []) as $package) {
                $dependency = $root.'/vendor/'.$package;

                if (! is_dir($dependency)) {
                    continue;
                }

                foreach ((array) (PackageRoot::composer($dependency)['extra']['laravel']['providers'] ?? []) as $provider) {
                    $providers[] = $provider;
                }

                if ($section === 'require-dev') {
                    $providers[] = PackageRoot::manifest($dependency)['provider'] ?? null;
                }
            }
        }

        $providers[] = PackageRoot::manifest($root)['provider'] ?? null;

        return array_values(array_unique(array_filter($providers, is_string(...))));
    }
}
