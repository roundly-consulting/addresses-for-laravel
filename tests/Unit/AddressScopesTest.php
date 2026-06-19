<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Tests\TestModel;

beforeEach(function () {
    $this->entity = TestModel::create();
});

it('scopes to primary addresses', function () {
    Address::factory()->create([
        'addressable_id' => $this->entity->id,
        'addressable_type' => TestModel::class,
        'is_primary' => true,
    ]);
    Address::factory()->create([
        'addressable_id' => $this->entity->id,
        'addressable_type' => TestModel::class,
        'is_primary' => false,
    ]);

    expect(Address::query()->primary()->count())->toBe(1);
});

it('scopes by type', function () {
    Address::factory()->create([
        'addressable_id' => $this->entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Billing->value,
    ]);
    Address::factory()->create([
        'addressable_id' => $this->entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Home->value,
    ]);

    expect(Address::query()->ofType(AddressType::Billing)->count())->toBe(1);
});

it('scopes by country', function () {
    Address::factory()->create([
        'addressable_id' => $this->entity->id,
        'addressable_type' => TestModel::class,
        'country_iso' => 'SK',
    ]);
    Address::factory()->create([
        'addressable_id' => $this->entity->id,
        'addressable_type' => TestModel::class,
        'country_iso' => 'CZ',
    ]);

    expect(Address::query()->inCountry(' sk ')->count())->toBe(1);
});
