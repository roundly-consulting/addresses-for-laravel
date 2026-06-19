<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Contracts\CountryResolver;
use RoundlyConsulting\Addresses\Tests\Support\StaticCountryResolver;
use RoundlyConsulting\Addresses\Tests\TestModel;

it('returns null when no resolver is bound', function () {
    $address = Address::factory()->make([
        'addressable_id' => TestModel::create()->id,
        'addressable_type' => TestModel::class,
        'country_iso' => 'SK',
    ]);

    expect($address->country_name)->toBeNull();
});

it('returns null when there is no country code', function () {
    app()->instance(CountryResolver::class, new StaticCountryResolver);

    $address = Address::factory()->make([
        'addressable_id' => TestModel::create()->id,
        'addressable_type' => TestModel::class,
        'country_iso' => null,
    ]);

    expect($address->country_name)->toBeNull();
});

it('resolves the country name through a bound resolver', function () {
    app()->instance(CountryResolver::class, new StaticCountryResolver);

    $address = Address::factory()->make([
        'addressable_id' => TestModel::create()->id,
        'addressable_type' => TestModel::class,
        'country_iso' => 'SK',
    ]);

    expect($address->country_name)->toBe('Slovakia');
});
