<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Tests;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Addresses\AddressesServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider addresses needs, in registration order. A host auto-discovers these;
     * the suite must list them or the test environment is a fiction. The toolkit ships no
     * provider of its own — it ships the base this one extends — so addresses' is the one.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [AddressesServiceProvider::class];
    }

    /**
     * The addresses migration, named by **provider class** rather than by the directory it
     * happens to sit in: the base case reflects on the provider to find its
     * `database/migrations`, so a relocated directory can never silently stop being loaded.
     * Then the fixture owner table (`test_models`) — a migration source rather than a
     * `Schema::create()` after the migrate, so the kept schema holds it too.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [
            AddressesServiceProvider::class,
            __DIR__.'/database/migrations',
        ];
    }

    /**
     * Migrate once per worker and schema signature; each test still starts on empty tables
     * with the key counters where a fresh migrate leaves them.
     */
    protected function keepsMigratedSchema(): bool
    {
        return true;
    }
}
