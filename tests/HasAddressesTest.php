<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use RoundlyConsulting\Addresses\Address;
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
        type: 'office',
        meta: collect([
            'custom_data' => 'OK',
        ]),
    );

    $expectedClass = config('addresses.model', Address::class);

    expect($address)->toBeInstanceOf($expectedClass)
        ->type->toBe('office')
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
        'type' => 'home',
        'is_primary' => false,
    ]);

    expect($entity->getAddressOfType('home'))
        ->toBeInstanceOf(Address::class)
        ->is_primary->toBeFalse()
        ->type->toBe('home');

    expect($entity->getPrimaryAddressOfType('work'))
        ->toBeNull();
});

it('returns primary address of type', function () {
    /** @var TestModel $entity */
    $entity = TestModel::create();

    Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => 'work',
        'is_primary' => true,
    ]);

    expect($entity->getPrimaryAddressOfType('work'))
        ->toBeInstanceOf(Address::class)
        ->is_primary->toBeTrue()
        ->type->toBe('work');

    expect($entity->getPrimaryAddressOfType('home'))
        ->toBeNull();
});
