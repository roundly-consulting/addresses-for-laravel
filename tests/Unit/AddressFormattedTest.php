<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Tests\TestModel;

it('formats a full address into a single line', function () {
    $address = Address::factory()->make([
        'addressable_id' => TestModel::create()->id,
        'addressable_type' => TestModel::class,
        'name' => 'HQ',
        'street' => 'Somewhere 1',
        'postal_code' => '81101',
        'city' => 'Bratislava',
        'country_iso' => 'SK',
    ]);

    expect($address->formatted())->toBe('HQ, Somewhere 1, 81101 Bratislava, SK');
});

it('omits empty parts', function () {
    $address = Address::factory()->make([
        'addressable_id' => TestModel::create()->id,
        'addressable_type' => TestModel::class,
        'name' => null,
        'street' => 'Main 2',
        'postal_code' => null,
        'city' => 'Kosice',
        'country_iso' => 'SK',
    ]);

    expect($address->formatted())->toBe('Main 2, Kosice, SK');
});

it('honours a custom separator', function () {
    $address = Address::factory()->make([
        'addressable_id' => TestModel::create()->id,
        'addressable_type' => TestModel::class,
        'name' => 'HQ',
        'street' => 'Main 2',
        'postal_code' => '04001',
        'city' => 'Kosice',
        'country_iso' => 'SK',
    ]);

    expect($address->formatted(' | '))->toBe('HQ | Main 2 | 04001 Kosice | SK');
});
