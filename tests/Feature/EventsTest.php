<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Events\AddressCreated;
use RoundlyConsulting\Addresses\Events\AddressDeleted;
use RoundlyConsulting\Addresses\Events\AddressUpdated;
use RoundlyConsulting\Addresses\Events\PrimaryAddressChanged;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\Tests\TestModel;

it('dispatches created on create', function () {
    Event::fake([AddressCreated::class]);
    $entity = TestModel::create();

    Addresses::for($entity)->new()->in('A')->at('B')->postalCode('C')->country('SK')->save();

    Event::assertDispatched(AddressCreated::class);
});

it('dispatches deleted on delete', function () {
    Event::fake([AddressDeleted::class]);
    $entity = TestModel::create();
    $address = Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
    ]);

    $address->delete();

    Event::assertDispatched(AddressDeleted::class, fn (AddressDeleted $event): bool => $event->address->is($address));
});

it('dispatches primary changed on promotion', function () {
    Event::fake([PrimaryAddressChanged::class]);
    $entity = TestModel::create();
    $address = Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Home->value,
        'is_primary' => false,
    ]);

    Addresses::for($entity)->setPrimary($address);

    Event::assertDispatched(PrimaryAddressChanged::class, fn (PrimaryAddressChanged $event): bool => $event->address->is($address));
});

it('does not dispatch updated unless updated', function () {
    Event::fake([AddressUpdated::class]);
    $entity = TestModel::create();
    Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
    ]);

    Event::assertNotDispatched(AddressUpdated::class);
});
