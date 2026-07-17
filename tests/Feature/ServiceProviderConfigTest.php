<?php

declare(strict_types=1);

use Illuminate\Foundation\AliasLoader;
use RoundlyConsulting\Addresses\AddressesServiceProvider;
use RoundlyConsulting\Addresses\Contracts\CountryResolver;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\Tests\Support\StaticCountryResolver;

/**
 * The toolkit builds the package declaration in register(), so a hand-built
 * provider must register() before it boots.
 */
function bootAddressesProvider(): void
{
    $provider = new AddressesServiceProvider(app());

    $provider->register();
    $provider->boot();
}

it('binds a configured country resolver', function () {
    config()->set('addresses.country_resolver', StaticCountryResolver::class);

    (new AddressesServiceProvider($this->app))->register();

    expect(app(CountryResolver::class))->toBeInstanceOf(StaticCountryResolver::class);
});

it('skips the facade alias when none is configured', function () {
    config()->set('addresses.facade_alias', null);

    bootAddressesProvider();
})->throwsNoExceptions();

it('registers the configured facade alias', function () {
    config()->set('addresses.facade_alias', 'Addresses');

    bootAddressesProvider();

    expect(AliasLoader::getInstance()->getAliases())
        ->toHaveKey('Addresses', Addresses::class);
});

it('renames the facade alias when the config names one', function () {
    config()->set('addresses.facade_alias', 'Locations');

    bootAddressesProvider();

    expect(AliasLoader::getInstance()->getAliases())
        ->toHaveKey('Locations', Addresses::class);
});

/**
 * The config half of the publish contract. The migration half — that the provider publishes
 * its migration timestamped and never auto-loads it — moved to `MigrationOrderTest.php`,
 * where the `toPublishMigrationsTimestamped` / `toNotAutoLoadMigrations` presets pin the
 * same two facts with a pinned file count. The hand-rolled versions that used to sit here
 * were unpinned in one direction: the auto-load check asserted a `realpath()` was absent
 * from the migrator's paths, which passes just as happily when the directory has been
 * renamed out from under it.
 */
it('publishes the config file under the package tag', function () {
    bootAddressesProvider();

    $config = AddressesServiceProvider::pathsToPublish(AddressesServiceProvider::class, 'addresses-config');

    expect(array_keys($config)[0])->toEndWith('config/addresses.php')
        ->and(array_values($config)[0])->toEndWith('config/addresses.php');
});
