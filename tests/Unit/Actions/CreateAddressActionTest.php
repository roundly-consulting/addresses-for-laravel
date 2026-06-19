<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Addresses\Actions\CreateAddressAction;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Events\AddressCreated;
use RoundlyConsulting\Addresses\Tests\TestModel;

it('persists an address for the addressable', function () {
    $entity = TestModel::create();

    $address = app(CreateAddressAction::class)->execute($entity, AddressData::make(
        city: 'Bratislava',
        street: 'Somewhere 1',
        postalCode: '81101',
        countryIso: 'SK',
        type: AddressType::Office,
    ));

    expect($address)->toBeInstanceOf(Address::class)
        ->type->toBe(AddressType::Office);

    $this->assertDatabaseHas('addresses', [
        'id' => $address->id,
        'addressable_id' => $entity->id,
        'type' => 'office',
    ]);
});

it('demotes prior primaries of the same type', function () {
    $entity = TestModel::create();

    $existing = Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Home->value,
        'is_primary' => true,
    ]);

    $new = app(CreateAddressAction::class)->execute($entity, AddressData::make(
        city: 'Kosice',
        street: 'Main 2',
        postalCode: '04001',
        countryIso: 'SK',
        type: AddressType::Home,
        isPrimary: true,
    ));

    expect($new->refresh()->is_primary)->toBeTrue();
    expect($existing->refresh()->is_primary)->toBeFalse();
});

it('dispatches the created event', function () {
    Event::fake();

    $entity = TestModel::create();

    $address = app(CreateAddressAction::class)->execute($entity, AddressData::make(
        city: 'A',
        street: 'B',
        postalCode: 'C',
        countryIso: 'SK',
    ));

    Event::assertDispatched(AddressCreated::class, fn (AddressCreated $event): bool => $event->address->is($address));
});
