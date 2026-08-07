<?php

declare(strict_types=1);

namespace Liberu\PackageTestbench\Tests\Fixtures;

/**
 * Writes throwaway package directories to disk so the boundary assertions can
 * be exercised against real files rather than mocks — these assertions exist to
 * read a package off disk, so faking the disk would test nothing.
 */
final class FixturePackage
{
    private static ?string $base = null;

    /** @param array<string, mixed> $overrides */
    public static function module(array $overrides = [], string $vendor = 'liberusoftware'): string
    {
        return self::write('module.json', [
            'composer' => [
                'name' => $vendor.'/fixture',
                'type' => 'liberu-module',
                'version' => '1.0.0',
                'require' => ['php' => '^8.5', $vendor.'/other' => '^1.0'],
                'extra' => ['liberu' => ['name' => 'fixture'], 'laravel' => ['providers' => []]],
            ],
            'manifest' => [
                'name' => 'fixture',
                'version' => '1.0.0',
                'provider' => FixtureServiceProvider::class,
                'requires' => ['packages' => [$vendor.'/other' => '^1.0']],
                'features' => ['fixture feature'],
            ],
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    public static function theme(array $overrides = []): string
    {
        return self::write('theme.json', [
            'composer' => [
                'name' => 'liberusoftware/theme-fixture',
                'type' => 'liberu-theme',
                'version' => '1.0.0',
                'extra' => ['liberu' => ['name' => 'fixture']],
            ],
            'manifest' => [
                'name' => 'fixture',
                'version' => '1.0.0',
                'provider' => FixtureServiceProvider::class,
                'assets' => ['css' => ['resources/css/app.css'], 'js' => []],
            ],
            'files' => ['resources/css/app.css' => '/* fixture */'],
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides */
    public static function contract(array $overrides = []): string
    {
        return self::write(null, [
            'composer' => [
                'name' => 'liberusoftware/fixture-contracts',
                'type' => 'library',
                'version' => '1.0.0',
                'require' => ['php' => '^8.5'],
            ],
            'files' => ['src/Thing.php' => "<?php\n\nnamespace Liberu\\Fixture\\Contracts;\n\ninterface Thing {}\n"],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $overrides
     */
    private static function write(?string $manifestFile, array $defaults, array $overrides): string
    {
        $spec = self::merge($defaults, $overrides);
        $root = self::base().'/'.bin2hex(random_bytes(8));

        mkdir($root, 0777, true);

        foreach (['composer.json', 'README.md', 'LICENSE.md', 'CHANGELOG.md'] as $required) {
            file_put_contents($root.'/'.$required, $required === 'composer.json' ? self::json($spec['composer']) : '# fixture');
        }

        if ($manifestFile !== null && ($spec['manifest'] ?? null) !== null) {
            file_put_contents($root.'/'.$manifestFile, self::json($spec['manifest']));
        }

        foreach ($spec['files'] ?? [] as $path => $contents) {
            // Two files may share a directory, and PHPUnit's error handler reports a
            // suppressed mkdir() warning anyway.
            $directory = dirname($root.'/'.$path);

            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }

            file_put_contents($root.'/'.$path, $contents);
        }

        foreach ($spec['remove'] ?? [] as $path) {
            @unlink($root.'/'.$path);
        }

        return $root;
    }

    /**
     * Merge maps key by key, but replace list values outright — an override of
     * `assets.css => []` must mean "no assets", not "keep the defaults".
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $existing = $base[$key] ?? null;

            $base[$key] = is_array($value) && is_array($existing) && ! array_is_list($value) && ! array_is_list($existing)
                ? self::merge($existing, $value)
                : $value;
        }

        return $base;
    }

    /** @param array<string, mixed> $data */
    private static function json(array $data): string
    {
        return (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private static function base(): string
    {
        if (self::$base === null) {
            self::$base = sys_get_temp_dir().'/liberu-testbench-fixtures-'.bin2hex(random_bytes(6));
            mkdir(self::$base, 0777, true);
        }

        return self::$base;
    }
}
