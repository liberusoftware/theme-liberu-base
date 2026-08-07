<?php

declare(strict_types=1);

namespace Liberu\PackageTestbench;

use JsonException;
use RuntimeException;

/**
 * Locates the package under test and reads its metadata.
 *
 * This is the whole package-loading declaration: a package declares nothing
 * extra, because its manifest already names its provider and its sibling
 * dependencies. The anchor is `composer.json` rather than the manifest, because
 * contract packages carry no `module.json` or `theme.json` at all.
 */
final class PackageRoot
{
    /**
     * Walk up from a file or directory to the nearest package root.
     *
     * Boundary suites pass `getcwd()`: they are executed from inside `vendor/`,
     * so `__DIR__` would find the testbench itself rather than the consumer.
     */
    public static function locate(string $from): string
    {
        $directory = is_dir($from) ? $from : dirname($from);

        while (true) {
            if (is_file($directory.'/composer.json')) {
                return $directory;
            }

            $parent = dirname($directory);

            if ($parent === $directory) {
                throw new RuntimeException("No composer.json found at or above [{$from}].");
            }

            $directory = $parent;
        }
    }

    /**
     * The package's `module.json` or `theme.json`, or null for a package that
     * carries neither — a contract package, or the installer plugin.
     *
     * @return array<string, mixed>|null
     */
    public static function manifest(string $root): ?array
    {
        foreach (['module.json', 'theme.json'] as $candidate) {
            if (is_file($root.'/'.$candidate)) {
                return self::readJson($root.'/'.$candidate);
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public static function composer(string $root): array
    {
        return self::readJson($root.'/composer.json');
    }

    /**
     * The manifest's category, or null for a theme or contract package.
     *
     * A boundary suite skips on this rather than branching inside an assertion,
     * so a rule that does not apply says so instead of passing silently.
     */
    public static function category(string $root): ?string
    {
        $category = self::manifest($root)['category'] ?? null;

        return is_string($category) ? $category : null;
    }

    /** Whether the package's Composer name carries a naming-convention suffix. */
    public static function nameEndsWith(string $root, string $suffix): bool
    {
        $name = self::composer($root)['name'] ?? '';

        return is_string($name) && str_ends_with($name, $suffix);
    }

    /**
     * The Composer vendor the package publishes under, with its trailing slash.
     *
     * Derived, never hardcoded: a constant would make the whole fleet red for
     * the duration of any vendor migration.
     */
    public static function vendor(string $root): string
    {
        $name = self::composer($root)['name'] ?? '';

        if (! is_string($name) || ! str_contains($name, '/')) {
            throw new RuntimeException("Package at [{$root}] declares no usable Composer name.");
        }

        return explode('/', $name, 2)[0].'/';
    }

    /** @return array<string, mixed> */
    private static function readJson(string $file): array
    {
        try {
            $decoded = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("[{$file}] is not valid JSON: {$e->getMessage()}", previous: $e);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException("[{$file}] does not decode to an object.");
        }

        return $decoded;
    }
}
