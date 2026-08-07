<?php

declare(strict_types=1);

namespace Liberu\PackageTestbench;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Gives a package's test case a `users` table and a user to act as.
 *
 * Opt-in rather than always-on: most packages never touch a user, and a table
 * created for all of them would be one more thing a reader has to know is not
 * the package's own. A package that needs one adds this trait and, where it
 * resolves the model from configuration, points that configuration at
 * {@see TestUser}.
 *
 * `RefreshDatabase` comes with it — the migration is worthless without one, and
 * every package that has needed this has needed both.
 */
trait UsesTestUser
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');
    }
}
