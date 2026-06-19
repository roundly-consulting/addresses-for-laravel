<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\IncompleteAddressException;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\Tests\TestModel;

it('persists a full fluent chain', function () {
    $entity = TestModel::create();

    $address = Addresses::for($entity)
        ->type(AddressType::Office)
        ->name('HQ')
        ->primary()
        ->in('Bratislava')->at('Somewhere 1')->postalCode('81101')->country('sk')
        ->meta(['floor' => 3])
        ->save();

    expect($address)->toBeInstanceOf(Address::class)
        ->type->toBe(AddressType::Office)
        ->name->toBe('HQ')
        ->is_primary->toBeTrue()
        ->city->toBe('Bratislava')
        ->country_iso->toBe('SK')
        ->and($address->meta?->get('floor'))->toBe(3);
});

it('builds from the model helper', function () {
    $entity = TestModel::create();

    $address = $entity->newAddress()
        ->type(AddressType::Home)
        ->in('Kosice')->at('Main 2')->postalCode('04001')->country('SK')
        ->save();

    expect($address->type)->toBe(AddressType::Home);
});

it('throws when required fields are missing', function () {
    $entity = TestModel::create();

    Addresses::for($entity)->in('Bratislava')->save();
})->throws(IncompleteAddressException::class);
