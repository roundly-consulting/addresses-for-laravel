<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\AddressesServiceProvider;
use RoundlyConsulting\Addresses\Contracts\CountryResolver;
use RoundlyConsulting\Addresses\Tests\Support\StaticCountryResolver;

it('binds a configured country resolver', function () {
    config()->set('addresses.country_resolver', StaticCountryResolver::class);

    (new AddressesServiceProvider($this->app))->register();

    expect(app(CountryResolver::class))->toBeInstanceOf(StaticCountryResolver::class);
});

it('skips the facade alias when none is configured', function () {
    config()->set('addresses.facade_alias', null);

    (new AddressesServiceProvider($this->app))->boot();
})->throwsNoExceptions();
