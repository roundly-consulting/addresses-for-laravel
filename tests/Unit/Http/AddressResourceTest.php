<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Http\Resources\AddressResource;
use RoundlyConsulting\Addresses\Tests\TestModel;

it('renders a stable shape', function () {
    $address = Address::factory()->create([
        'addressable_id' => TestModel::create()->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Office->value,
        'name' => 'HQ',
        'street' => 'Somewhere 1',
        'postal_code' => '81101',
        'city' => 'Bratislava',
        'country_iso' => 'SK',
        'is_primary' => true,
    ]);

    $array = (new AddressResource($address))->toArray(Request::create('/'));

    expect($array)
        ->toHaveKeys([
            'id', 'type', 'name', 'city', 'street', 'postal_code',
            'country_iso', 'country_name', 'is_primary', 'formatted', 'meta',
            'created_at', 'updated_at',
        ])
        ->and($array['type'])->toBe('office')
        ->and($array['formatted'])->toBe('HQ, Somewhere 1, 81101 Bratislava, SK')
        ->and($array)->not->toHaveKey('deleted_at');
});
