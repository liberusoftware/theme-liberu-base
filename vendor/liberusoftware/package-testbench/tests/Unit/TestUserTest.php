<?php

declare(strict_types=1);

use Liberu\PackageTestbench\PackageTestCase;
use Liberu\PackageTestbench\TestUser;
use Liberu\PackageTestbench\UsesTestUser;

/*
 * The actor a package's own tests act as. Exercised here rather than only in the
 * fleet, because a broken shared fixture is a broken fixture in 44 repositories.
 */

uses(PackageTestCase::class, UsesTestUser::class);

it('migrates a users table and creates a user in it', function () {
    $user = TestUser::factory()->create(['name' => 'Ada Lovelace']);

    expect($user->exists)->toBeTrue()
        ->and(TestUser::query()->count())->toBe(1)
        ->and($user->getTable())->toBe('users');
});

it('acts as the user it creates', function () {
    $this->actingAs(TestUser::factory()->create(['name' => 'Ada Lovelace']));

    expect(auth()->check())->toBeTrue()
        ->and(auth()->user()?->name)->toBe('Ada Lovelace');
});

// Packages assert on verification state — search filters on it — so the state
// has to produce a genuinely unverified row rather than a default one.
it('creates verified users by default and unverified on request', function () {
    expect(TestUser::factory()->create()->email_verified_at)->not->toBeNull()
        ->and(TestUser::factory()->unverified()->create()->email_verified_at)->toBeNull();
});

it('gives every user a distinct email, so a package can create several', function () {
    $emails = TestUser::factory()->count(5)->create()->pluck('email');

    expect($emails->unique())->toHaveCount(5);
});
