<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Testing\Database\DriverMatrix;

/**
 * The outbound `addressable` morph column follows `addresses.key_type` (default `bigint`)
 * through the toolkit's `morphKey` macro. Two things must hold and are proven here:
 *
 *  - the default (`bigint`) emitted schema is BYTE-IDENTICAL to the pre-macro `morphs()`
 *    output — `morphKey($n, BigInt)` *is* `morphs($n)` — so a default host sees zero change;
 *  - a `uuid` / `ulid` host actually gets a uuid / char morph id column, checked on the only
 *    engine (Postgres) whose catalog can tell the three key types apart.
 */
function runAddressesMigration(): void
{
    (require __DIR__.'/../../database/migrations/create_addresses_table.php')->up();
}

function emittedAddressesTable(string $table): string
{
    /** @var list<object{sql: string|null}> $rows */
    $rows = DB::select('select sql from sqlite_master where type = ? and name = ?', ['table', $table]);

    return (string) ($rows[0]->sql ?? '');
}

function pgsqlAddressesColumnType(string $table, string $column): string
{
    /** @var list<object{data_type: string, character_maximum_length: int|null}> $rows */
    $rows = DB::select(
        'select data_type, character_maximum_length from information_schema.columns where table_name = ? and column_name = ?',
        [$table, $column],
    );

    $row = $rows[0] ?? null;

    if ($row === null) {
        return 'MISSING';
    }

    return $row->character_maximum_length === null
        ? $row->data_type
        : $row->data_type.'('.$row->character_maximum_length.')';
}

$sqliteOnly = fn (): bool => DriverMatrix::driver() !== 'sqlite';
$pgsqlOnly = fn (): bool => DriverMatrix::driver() !== 'pgsql';

it('emits the frozen bigint morph schema byte-for-byte', function (): void {
    // The harness has already migrated on the default (bigint) config. This is the shipped
    // schema — the sweep's core safety property is that it must never drift.
    expect(emittedAddressesTable('addresses'))->toBe(
        'CREATE TABLE "addresses" ("id" integer primary key autoincrement not null, '
        .'"addressable_type" varchar not null, "addressable_id" integer not null, '
        .'"is_primary" tinyint(1) not null default \'0\', "type" varchar not null default \'default\', '
        .'"name" varchar, "city" varchar, "street" varchar, "postal_code" varchar, '
        .'"country_iso" varchar, "meta" text, '
        .'"created_at" datetime, "updated_at" datetime, "deleted_at" datetime)'
    );
})->skip($sqliteOnly, 'sqlite_master is the sqlite catalog');

it('renders each configured key type as a distinct real morph column type', function (string $keyType, string $expected): void {
    config()->set('addresses.key_type', $keyType);

    Schema::dropIfExists('addresses');
    runAddressesMigration();

    expect(pgsqlAddressesColumnType('addresses', 'addressable_id'))->toBe($expected)
        // The morph *type* column names a class — a string on every key type.
        ->and(pgsqlAddressesColumnType('addresses', 'addressable_type'))->toBe('character varying(255)');
})->with([
    'bigint' => ['bigint', 'bigint'],
    'uuid' => ['uuid', 'uuid'],
    'ulid' => ['ulid', 'character(26)'],
])->skip($pgsqlOnly, 'needs the postgres catalog to tell the key types apart');

it('refuses to migrate on an unrecognized key type instead of falling back to bigint', function (): void {
    config()->set('addresses.key_type', 'nonsense');

    Schema::dropIfExists('addresses');

    // A typo in a host's config must stop the migration, never silently build bigint
    // columns for a uuid/ulid-keyed host.
    expect(function (): void {
        runAddressesMigration();
    })->toThrow(InvalidConfigurationException::class, 'Configuration value [addresses.key_type] must be one of [bigint, uuid, ulid] (case-insensitive), [nonsense] given.');
});
