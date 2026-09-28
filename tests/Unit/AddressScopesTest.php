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

it('matches the verbatim codes stored when normalisation is off, whatever their case', function () {
    config()->set('addresses.normalise_country', false);
    $this->entity->createAddress(city: 'c', street: 's', postalCode: '1', countryIsoCode: 'sk');
    $this->entity->createAddress(city: 'c', street: 's', postalCode: '1', countryIsoCode: 'Sk ');
    $this->entity->createAddress(city: 'c', street: 's', postalCode: '1', countryIsoCode: 'cz');

    expect(Address::query()->inCountry('sk')->count())->toBe(2)
        ->and(Address::query()->inCountry(' SK ')->count())->toBe(2)
        ->and(Address::query()->inCountry('svk')->count())->toBe(0);
});

it('reads an env-string normalisation switch as a boolean', function (string $off) {
    config()->set('addresses.normalise_country', $off);
    $this->entity->createAddress(city: 'c', street: 's', postalCode: '1', countryIsoCode: ' sk ');

    expect(Address::query()->value('country_iso'))->toBe('sk')
        ->and(Address::query()->inCountry('SK')->count())->toBe(1);
})->with(['false', '0', 'off', 'no']);
