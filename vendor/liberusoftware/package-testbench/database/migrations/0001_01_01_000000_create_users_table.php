<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The base `users` table, which no package owns.
 *
 * Several packages migrate *onto* it — `profiles` adds `locale`,
 * `theme_preference` and `timezone`, `search` adds its indexes — so their own
 * suites need it to exist first. In an application it comes from Laravel's
 * default migration; under Testbench it comes from here.
 *
 * Deliberately minimal: anything beyond authentication belongs to the package
 * that added it, and adding a column here would let a package's migration pass
 * without shipping the column it depends on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
