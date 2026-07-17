<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [AddressesServiceProvider::class];
    }

    /**
     * The host-owned entity addresses hang off. It is an ad-hoc `Schema::create()` rather
     * than a migration on purpose: `addressable` is a polymorphic, deliberately
     * unconstrained morph, so the owner's table is the host's business and nothing here is
     * shipped schema with an order to pin.
     */
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        Schema::create('test_models', function (Blueprint $table): void {
            $table->increments('id');
        });
    }
}
