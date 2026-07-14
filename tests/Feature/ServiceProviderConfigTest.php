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

it('publishes the config and a timestamped migration under the package tags', function () {
    bootAddressesProvider();

    $config = AddressesServiceProvider::pathsToPublish(AddressesServiceProvider::class, 'addresses-config');
    $migrations = AddressesServiceProvider::pathsToPublish(AddressesServiceProvider::class, 'addresses-migrations');

    expect(array_keys($config)[0])->toEndWith('config/addresses.php')
        ->and(array_values($config)[0])->toEndWith('config/addresses.php')
        ->and(array_keys($migrations)[0])->toEndWith('database/migrations/create_addresses_table.php')
        ->and(basename((string) array_values($migrations)[0]))
        ->toMatch('/^\d{4}_\d{2}_\d{2}_\d{6}_create_addresses_table\.php$/');
});
