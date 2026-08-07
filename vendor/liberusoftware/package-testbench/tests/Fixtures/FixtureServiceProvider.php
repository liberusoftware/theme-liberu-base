<?php

declare(strict_types=1);

namespace Liberu\PackageTestbench\Tests\Fixtures;

use Illuminate\Support\ServiceProvider;

/** A real provider for the fixture packages to declare. */
final class FixtureServiceProvider extends ServiceProvider
{
    public function register(): void {}
}
