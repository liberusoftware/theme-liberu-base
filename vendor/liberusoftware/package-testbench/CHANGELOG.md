# Changelog

All notable changes to this package are documented here. This project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.7.0

### Added

`PackageTestCase` sets an application key. Testbench boots without one, so any package that
renders a view — a Livewire component, a mailable — died on "No application encryption key
has been specified" rather than on anything the package did.

A package overriding `defineEnvironment()` must now call `parent::defineEnvironment($app)`.

## 1.6.0

### Added

**A shared test actor** — `TestUser`, its factory, the base `users` migration, and a
`UsesTestUser` trait that loads the migration and brings `RefreshDatabase` with it. This is
what lets a package's own suite test anything behind authentication, so host tests that only
used `App\Models\User` to have *a* user can move to the package that owns their subject.

The base `users` table is exactly what no package owns: `profiles` adds `locale`,
`theme_preference` and `timezone` to it, `search` adds its indexes, but the table itself
belongs to the application, and a package under test has none.

It implements **none** of the fleet's actor contracts, against `CONFORMANCE.md` §3.7's
description of this release. Implementing `PrivilegedActor`, `ObservabilityActor`,
`OrganizationActor` and `ConnectedAccountOwner` would make this package require Horizon,
Pulse, Telescope, Jetstream, Socialite and Socialstream — in all 44 dev trees — for four
one-method interfaces. Only three host tests use them, and all three are about the host's own
`User` model, so they are composition tests that stay where they are. A package whose tests
genuinely need a contract it already depends on subclasses `TestUser` and implements that one
interface, in its own tests.

## 1.5.0

### Added

**`PackageTestCase` now boots the providers a package's dependencies declare**, not only its
own. Testbench runs no package discovery, so a module whose provider reaches for a framework
binding could not boot standalone at all: both `*-livewire` modules in the fleet failed every
boundary test with `Target class [livewire.finder] does not exist`.

Two sources supply them, neither a new convention:

- **`extra.laravel.providers` of a direct dependency** — Laravel's own discovery, scoped to
  what the package requires. Sibling Liberu modules are excluded by their own manifests,
  which declare that array empty precisely so installation never implies boot.
- **the manifest provider of a sibling in `require-dev`** — a dev requirement on a sibling
  states what this package is tested against. It is how an adapter boots against a real
  implementation of the contract it depends on without requiring it at runtime.

### Changed

`declaredProviderRegisters()` asserts the provider **is** registered rather than forcing a
second registration. A provider that contributes to a registry threw on the duplicate. Boot
is where the proof was in any case — a provider that cannot boot fails before the assertion
runs.

## 1.4.1

### Fixed

**1.3.0 and 1.4.0 ship a module boundary suite that cannot run.** Its three `skip()`
conditions were static closures, which Pest binds to the test case: every module in the
fleet failed those three tests with `Cannot bind an instance to a static closure`. Use
1.4.1 or later.

The bug reached two releases because the Boundary suites — the one thing this package
exists to distribute — were excluded from its own `phpunit.xml`, on the grounds that this
package is not a module. It is not, but a throwaway fixture is: `tests/Pest.php` now stands
each shipped suite inside a fixture package of the kind it is written for, which is how a
consumer runs it. A suite that does not load now fails here rather than in a 44-package
sweep.

## 1.4.0

### Added

A package's declared version must be `MAJOR.MINOR.PATCH`, in both `composer.json` and the
manifest. The host's `Manifest` parser requires the key but never checks its shape, so a
version Composer cannot order was only discovered by trying to release against it.

This is the one assertion the host's package metadata rule carried that nothing here
replaced. It is released separately from 1.3.0 rather than folded into it: that tag is
already published, and a moved tag resolves differently depending on when a consumer last
installed.

## 1.3.0

### Changed

The three module rules that apply to one kind of package only — Filament outside a domain
module, domain models in an `-api` adapter, panel plugins in a `-filament` module — now
decide that in a `skip()` on the shipped suite rather than a guard clause returning early
inside the assertion.

A guard left the test passing having performed no assertion, which Pest reports as risky
and a reader reports as coverage. Every domain module in the fleet ran two such tests. The
condition now reads as a skip with its reason, and lives in `PackageRoot::category()` and
`PackageRoot::nameEndsWith()` where it is directly asserted.

## 1.2.0

### Added

Four boundary rules the host was enforcing on the fleet's behalf. Each was a rule the
owning repository could not run, so a package was only ever as correct as the last time
someone composed it:

- **No `App\` dependency.** `App\` is the consuming application's namespace; a module
  importing it is installable in exactly one application.
- **No Filament in a domain module.** A non-`presentation` module importing Filament
  cannot be consumed headlessly, and drags a UI framework into compositions that wanted
  only its domain.
- **No domain models in an `-api` adapter.** Importing a `Models\` class couples the
  transport to the storage the adapter exists to avoid.
- **A `-filament` module declares panel plugins that exist.** A presentation package
  declaring none is installed, booted and invisible — the failure mode is silence.

With these the host's architecture suite can drop the seven rules `CONFORMANCE.md` §3.8
schedules for the move; three were already here, and these are the remaining four.

## 1.1.0

### Added

- The module boundary suite now asserts a usable `features` list: non-empty, strings,
  untrimmed entries rejected, and no duplicate ignoring case. `module:features` folds
  case when searching, so an empty list makes a module undiscoverable and two casings
  of one feature report a duplicate hit. The host's `Manifest::fromFile()` already
  rejected both, but only once a composition installed the module — this is what lets
  the owning repository find out first.

  Adopting 1.1.0 is what allows a module repository to delete its hand-written
  `PackageMetadataTest`, which carried these assertions locally.

## 1.0.0

First release.

### Added

- `PackageRoot` — locates the package under test and reads its `composer.json` and
  manifest. It anchors on `composer.json` rather than the manifest, so contract
  packages, which carry neither `module.json` nor `theme.json`, are found too.
  `PackageRoot::vendor()` derives the Composer vendor from the package's own name
  rather than hardcoding it; a constant would fail the whole fleet for the duration
  of any vendor migration.
- `PackageTestCase` — a Testbench base case that registers the provider the manifest
  declares, so no package writes a `getPackageProviders()` override or a bootstrap.
- `BoundaryAssertions` — the shared assertions, as plain static calls.
- Three shipped boundary suites — `tests/Boundary/Module`, `tests/Boundary/Theme` and
  `tests/Boundary/Contract` — that a consuming package points its own test run at.
  Three rather than one because the manifests genuinely differ: `theme.json` has no
  `requires` key, and contract packages have no manifest and no provider at all. One
  suite branching at runtime would let a package skip half its assertions and still
  report green.

### Known constraint

Pest requires a `tests/` directory at the package root even when every test it runs
lives in `vendor/`. A package with no tests of its own must name the suite directly
with `--test-directory` instead of relying on a `phpunit.xml` testsuite. See the
README.
