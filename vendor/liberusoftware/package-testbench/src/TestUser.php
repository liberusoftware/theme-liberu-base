<?php

declare(strict_types=1);

namespace Liberu\PackageTestbench;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * The authenticated actor a package's own tests act as.
 *
 * Packages that touch a user do it through a configured class — search reads
 * `config('search.models.user')`, localization reads `users.locale` — so the
 * only thing they need is *a* user model with a factory and a table. That is
 * what no package owns: `profiles` adds `locale`, `theme_preference` and
 * `timezone` to `users`, `search` adds its indexes, but the base table itself
 * belongs to the application, and a package under test has none.
 *
 * It deliberately implements none of the fleet's actor contracts. Doing so would
 * make this package require Horizon, Pulse, Telescope, Jetstream and
 * Socialstream — in all 44 dev trees — for four one-method interfaces that only
 * three host tests use, and those three are about the host's own `User`. A
 * package whose tests genuinely need a contract it already depends on subclasses
 * this and implements that one interface, in its own tests.
 *
 * @property string $name
 * @property string $email
 */
class TestUser extends Authenticatable
{
    /** @use HasFactory<TestUserFactory> */
    use HasFactory;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    /** @return TestUserFactory */
    protected static function newFactory(): Factory
    {
        return TestUserFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
