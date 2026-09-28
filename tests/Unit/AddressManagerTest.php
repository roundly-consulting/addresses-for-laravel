<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\AddressBook;
use RoundlyConsulting\Addresses\AddressManager;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Tests\TestModel;

it('returns an address book for a model', function () {
    $entity = TestModel::create();

    expect(app(AddressManager::class)->for($entity))->toBeInstanceOf(AddressBook::class);
});

it('is bound as a singleton', function () {
    expect(app(AddressManager::class))->toBe(app(AddressManager::class));
});

it('finds the primary address for a model', function () {
    $entity = TestModel::create();

    Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Work->value,
        'is_primary' => true,
    ]);

    expect(app(AddressManager::class)->for($entity)->primary())->not->toBeNull();
    expect(app(AddressManager::class)->for($entity)->primary(AddressType::Work))
        ->type->toBe(AddressType::Work);
});

it('returns addresses of a type for a model', function () {
    $entity = TestModel::create();

    Address::factory(3)->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Billing->value,
    ]);

    expect(app(AddressManager::class)->for($entity)->ofType(AddressType::Billing))->toHaveCount(3);
});
