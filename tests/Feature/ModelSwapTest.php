<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\AddressManager;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\Tests\Support\CustomAddress;
use RoundlyConsulting\Addresses\Tests\TestModel;

beforeEach(function () {
    config()->set('addresses.model', CustomAddress::class);
});

it('uses the configured model when adding via the trait', function () {
    $entity = TestModel::create();

    $address = $entity->addAddress(AddressData::make(
        city: 'A',
        street: 'B',
        postalCode: 'C',
        countryIso: 'SK',
        type: AddressType::Home,
    ));

    expect($address)->toBeInstanceOf(CustomAddress::class);
});

it('uses the configured model through the facade', function () {
    $entity = TestModel::create();

    $address = Addresses::for($entity)
        ->in('A')->at('B')->postalCode('C')->country('SK')->save();

    expect($address)->toBeInstanceOf(CustomAddress::class);
});

it('uses the configured model in manager reads', function () {
    $entity = TestModel::create();
    $entity->addAddress(AddressData::make(
        city: 'A',
        street: 'B',
        postalCode: 'C',
        countryIso: 'SK',
        type: AddressType::Work,
        isPrimary: true,
    ));

    $primary = app(AddressManager::class)->primaryFor($entity, AddressType::Work);

    expect($primary)->toBeInstanceOf(CustomAddress::class);
    expect(Address::class)->not->toBe(CustomAddress::class);
});
