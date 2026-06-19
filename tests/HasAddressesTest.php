<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\AddressOwnershipException;
use RoundlyConsulting\Addresses\PendingAddress;
use RoundlyConsulting\Addresses\Tests\TestModel;

it('creates address for entity', function () {
    /** @var TestModel $entity */
    $entity = TestModel::create();

    $address = $entity->createAddress(
        city: 'Pretty',
        street: 'Somewhere 1',
        postalCode: '123456',
        countryIsoCode: 'SK',
        name: 'My Office',
        isPrimary: true,
        type: AddressType::Office,
        meta: collect([
            'custom_data' => 'OK',
        ]),
    );

    $expectedClass = config('addresses.model', Address::class);

    expect($address)->toBeInstanceOf($expectedClass)
        ->type->toBe(AddressType::Office)
        ->city->toBe('Pretty')
        ->street->toBe('Somewhere 1')
        ->postal_code->toBe('123456')
        ->country_iso->toBe('SK')
        ->name->toBe('My Office')
        ->meta->toBeInstanceOf(Collection::class)
        ->meta->get('custom_data')->toBe('OK');

    $this->assertDatabaseHas('addresses', [
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'is_primary' => true,
        'type' => 'office',
        'name' => 'My Office',
        'city' => 'Pretty',
        'street' => 'Somewhere 1',
        'postal_code' => '123456',
        'country_iso' => 'SK',
        'meta' => json_encode([
            'custom_data' => 'OK',
        ]),
    ]);
});

it('has relationship to addresses', function () {
    /** @var TestModel $entity */
    $entity = TestModel::create();

    Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
    ]);

    expect($entity->addresses())->toBeInstanceOf(MorphMany::class)
        ->and($entity->addresses)
        ->toBeInstanceOf(EloquentCollection::class)
        ->toHaveLength(1);
});

it('returns address of type', function () {
    /** @var TestModel $entity */
    $entity = TestModel::create();

    Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Home->value,
        'is_primary' => false,
    ]);

    expect($entity->getAddressOfType(AddressType::Home))
        ->toBeInstanceOf(Address::class)
        ->is_primary->toBeFalse()
        ->type->toBe(AddressType::Home);

    expect($entity->getPrimaryAddressOfType(AddressType::Work))
        ->toBeNull();
});

it('returns primary address of type', function () {
    /** @var TestModel $entity */
    $entity = TestModel::create();

    Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Work->value,
        'is_primary' => true,
    ]);

    expect($entity->getPrimaryAddressOfType(AddressType::Work))
        ->toBeInstanceOf(Address::class)
        ->is_primary->toBeTrue()
        ->type->toBe(AddressType::Work);

    expect($entity->getPrimaryAddressOfType(AddressType::Home))
        ->toBeNull();
});

it('adds an address from a data object', function () {
    /** @var TestModel $entity */
    $entity = TestModel::create();

    $address = $entity->addAddress(AddressData::make(
        city: 'Bratislava',
        street: 'Somewhere 1',
        postalCode: '81101',
        countryIso: 'sk',
        type: AddressType::Billing,
    ));

    expect($address)
        ->type->toBe(AddressType::Billing)
        ->country_iso->toBe('SK');
});

it('returns a pending address builder', function () {
    /** @var TestModel $entity */
    $entity = TestModel::create();

    expect($entity->newAddress())->toBeInstanceOf(PendingAddress::class);
});

it('returns the primary address optionally scoped to a type', function () {
    /** @var TestModel $entity */
    $entity = TestModel::create();

    Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Home->value,
        'is_primary' => true,
    ]);
    Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Work->value,
        'is_primary' => true,
    ]);

    expect($entity->primaryAddress())->not->toBeNull();
    expect($entity->primaryAddress(AddressType::Work))
        ->type->toBe(AddressType::Work);
});

it('returns addresses of a type', function () {
    /** @var TestModel $entity */
    $entity = TestModel::create();

    Address::factory(2)->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Shipping->value,
    ]);
    Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Home->value,
    ]);

    expect($entity->addressesOfType(AddressType::Shipping))->toHaveCount(2);
});

it('reports whether it has addresses', function () {
    /** @var TestModel $entity */
    $entity = TestModel::create();

    expect($entity->hasAddresses())->toBeFalse();

    Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
    ]);

    expect($entity->fresh()->hasAddresses())->toBeTrue();
});

it('sets one of its own addresses as primary', function () {
    /** @var TestModel $entity */
    $entity = TestModel::create();

    $first = Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Home->value,
        'is_primary' => true,
    ]);
    $second = Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Home->value,
        'is_primary' => false,
    ]);

    $entity->setPrimaryAddress($second);

    expect($second->refresh()->is_primary)->toBeTrue();
    expect($first->refresh()->is_primary)->toBeFalse();
});

it('rejects setting a foreign address as primary', function () {
    /** @var TestModel $owner */
    $owner = TestModel::create();
    /** @var TestModel $other */
    $other = TestModel::create();

    $foreign = Address::factory()->create([
        'addressable_id' => $other->id,
        'addressable_type' => TestModel::class,
    ]);

    $owner->setPrimaryAddress($foreign);
})->throws(AddressOwnershipException::class);
