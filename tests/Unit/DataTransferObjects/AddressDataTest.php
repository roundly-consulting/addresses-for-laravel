<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;

it('normalises the country and trims string fields', function () {
    $data = AddressData::make(
        city: '  Bratislava ',
        street: ' Somewhere 1 ',
        postalCode: ' 81101 ',
        countryIso: ' sk ',
        name: '  HQ  ',
    );

    expect($data)
        ->city->toBe('Bratislava')
        ->street->toBe('Somewhere 1')
        ->postalCode->toBe('81101')
        ->countryIso->toBe('SK')
        ->name->toBe('HQ');
});

it('defaults type and primary flag', function () {
    $data = AddressData::make(
        city: 'A',
        street: 'B',
        postalCode: 'C',
        countryIso: 'SK',
    );

    expect($data)
        ->type->toBe(AddressType::Default)
        ->isPrimary->toBeFalse()
        ->name->toBeNull();
});

it('nullifies a blank name', function () {
    $data = AddressData::make(
        city: 'A',
        street: 'B',
        postalCode: 'C',
        countryIso: 'SK',
        name: '   ',
    );

    expect($data->name)->toBeNull();
});

it('skips normalisation when disabled', function () {
    config()->set('addresses.normalise_country', false);

    $data = AddressData::make(
        city: 'A',
        street: 'B',
        postalCode: 'C',
        countryIso: ' sk ',
    );

    expect($data->countryIso)->toBe('sk');
});

it('maps to write attributes', function () {
    $data = AddressData::make(
        city: 'Bratislava',
        street: 'Somewhere 1',
        postalCode: '81101',
        countryIso: 'SK',
        name: 'HQ',
        type: AddressType::Office,
        isPrimary: true,
        meta: collect(['floor' => 3]),
    );

    $attributes = $data->toAttributes();

    expect($attributes)
        ->toHaveKeys(['type', 'is_primary', 'name', 'city', 'street', 'postal_code', 'country_iso', 'meta'])
        ->and($attributes['type'])->toBe('office')
        ->and($attributes['is_primary'])->toBeTrue()
        ->and($attributes['postal_code'])->toBe('81101');
});
