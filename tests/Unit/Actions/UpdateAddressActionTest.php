<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Addresses\Actions\UpdateAddressAction;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Events\AddressUpdated;
use RoundlyConsulting\Addresses\Tests\TestModel;

it('updates the address fields', function () {
    $entity = TestModel::create();

    $address = Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'city' => 'Old',
    ]);

    $updated = app(UpdateAddressAction::class)->execute($address, AddressData::make(
        city: 'New',
        street: 'Street',
        postalCode: '00000',
        countryIso: 'SK',
        type: AddressType::Billing,
    ));

    expect($updated)
        ->city->toBe('New')
        ->type->toBe(AddressType::Billing);
});

it('promotes to primary when requested', function () {
    $entity = TestModel::create();

    $address = Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Home->value,
        'is_primary' => false,
    ]);

    app(UpdateAddressAction::class)->execute($address, AddressData::make(
        city: 'New',
        street: 'Street',
        postalCode: '00000',
        countryIso: 'SK',
        type: AddressType::Home,
        isPrimary: true,
    ));

    expect($address->refresh()->is_primary)->toBeTrue();
});

it('dispatches the updated event', function () {
    Event::fake();

    $entity = TestModel::create();
    $address = Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
    ]);

    app(UpdateAddressAction::class)->execute($address, AddressData::make(
        city: 'New',
        street: 'Street',
        postalCode: '00000',
        countryIso: 'SK',
    ));

    Event::assertDispatched(AddressUpdated::class);
});
