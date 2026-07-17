<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RoundlyConsulting\Addresses\AddressesServiceProvider;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Tests\TestModel;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * Addresses ships exactly one CREATE and zero foreign keys — the address's owner is a
 * polymorphic `morphs('addressable')`, deliberately unconstrained because a host's
 * addressable entity can live in any table.
 *
 * That shape decides what is worth pinning here, and it is worth being explicit about why:
 *
 *  - **M (`toHaveRunnableMigrationOrder`) is not adopted.** With one migration and no FK
 *    edges there is no order to get wrong. `foreignKeys: 0` would pin a number that cannot
 *    change without a schema change, over a directory holding a single file.
 *  - **The R negative control (`toRejectBrokenOrderOnConnection`) is not adoptable.** It
 *    asserts the engine *refuses* a reordered set — but reversing a one-file list is the
 *    same list, and with no foreign keys Postgres has nothing to refuse. It fails loudly by
 *    design ("the engine accepted the broken order"): the assertion working correctly
 *    against a shape it does not fit, not a red to chase.
 */
/**
 * P — the publish-only guards, and this package's own history.
 *
 * Addresses was the migration escape-hatch row of the toolkit retrofit: it hand-wrote
 * `loadMigrationsFrom()` **and** published the same file under an `addresses-migrations`
 * tag, so a host that followed the README's publish step got the table created twice — the
 * duplicate-table footgun (bug #5, on three packages) that the publish-only policy exists
 * to close. The hatch is gone (the provider declares `->hasMigrations()` and nothing else),
 * and these two pins are what keep it gone rather than a note in a changelog.
 *
 * `count: 1` pins the file count so neither check can pass over an empty or relocated
 * directory.
 */
it('never auto-loads its migration — the host publishes it', function (): void {
    expect(AddressesServiceProvider::class)->toNotAutoLoadMigrations();
});

it('publishes its migration timestamp-injected into the host', function (): void {
    expect(AddressesServiceProvider::class)->toPublishMigrationsTimestamped('addresses-migrations', 1);
});

/*
 * R (`toApplyOnConnection('pgsql', migrations: 1)`) is DEFERRED, not rejected — it belongs
 * here and should land once the testing package is fixed.
 *
 * It cannot be adopted today because it is **not safe to run alongside its own suite**. On
 * the pgsql leg, `DriverMatrix::configure()` builds `connections.testing` and
 * `connections.pgsql` from the same `connectionConfig('pgsql')` — identical host, port and
 * database. They are one physical database reached through two PDO sessions.
 * `MigrationRunner::runFiles()` calls `dropAllTables()` on entry and again in its `finally`,
 * so the assertion drops the live suite's tables mid-run, and `executionOrder="random"`
 * decides whether anything was still using them.
 *
 * Measured here, on a database isolated from every other suite, with R present: 6 runs gave
 * 5 × 94 passed and 1 × 13 failed. It is ~1-in-6, seed-dependent, and it fails *elsewhere* —
 * innocent tests die, not this one. A green pgsql leg with R in it is therefore evidence of
 * a lucky seed and nothing else, which is the precise failure this whole plan exists to
 * kill: an assertion that cannot be trusted when it passes.
 *
 * Everything else on this row is unaffected and stays: P below is a structural check on the
 * provider and needs no engine at all, and the round-trip is an ordinary test on the default
 * connection.
 */

/**
 * The `json` meta column, the enum-backed `type` string and the soft-delete timestamp are
 * what the drivers render differently, so a round-trip on whatever engine the leg
 * configured is what proves the columns are usable rather than merely creatable.
 *
 * The last assertion is the driver-truth pin: it compares the **env-declared** driver
 * against what the **connection itself answers**, so a leg that quietly stayed on SQLite
 * fails here instead of passing as a "postgres" run. It fires automatically, rather than
 * needing someone to read a skip count.
 */
it('round-trips the address columns on the configured engine', function (): void {
    $entity = TestModel::create();

    $address = $entity->addAddress(AddressData::make(
        city: 'Bratislava',
        street: 'Hlavná 1',
        postalCode: '81101',
        countryIso: 'SK',
        type: AddressType::Home,
        isPrimary: true,
        meta: collect(['floor' => 3, 'notes' => 'ring twice']),
    ));

    $fresh = $address->fresh();

    expect($fresh->meta?->all())->toBe(['floor' => 3, 'notes' => 'ring twice'])
        ->and($fresh->type)->toBe(AddressType::Home)
        ->and($fresh->is_primary)->toBeTrue()
        ->and($fresh->country_iso)->toBe('SK')
        ->and($fresh->deleted_at)->toBeNull()
        // The driver actually under test, so a leg that quietly stayed on sqlite is visible
        // in the failure rather than passing as a "postgres" run.
        ->and(DB::connection()->getDriverName())->toBe(DriverMatrix::driver());
});
