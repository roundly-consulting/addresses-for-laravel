<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Tests\TestModel;

it('builds plain addresses by default and a primary through the factory state', function () {
    $addressable = [
        'addressable_id' => TestModel::create()->id,
        'addressable_type' => TestModel::class,
        'type' => 'default',
    ];

    Address::factory(5)->create($addressable);
    Address::factory()->primary()->create($addressable);

    expect(Address::query()->where($addressable + ['is_primary' => false])->count())->toBe(5)
        ->and(Address::query()->where($addressable + ['is_primary' => true])->count())->toBe(1);
});

it('casts meta to a collection', function () {
    $address = Address::factory()->create([
        'addressable_id' => TestModel::create()->id,
        'addressable_type' => TestModel::class,
        'meta' => collect(['floor' => 3]),
    ]);

    expect($address->refresh()->meta)
        ->toBeInstanceOf(Collection::class)
        ->get('floor')->toBe(3);
});

it('returns owner of address', function () {
    $testModel = TestModel::create();

    $address = Address::factory()->create([
        'addressable_id' => $testModel->id,
        'addressable_type' => TestModel::class,
    ]);

    expect($address->addressable)
        ->toBeInstanceOf(TestModel::class)
        ->id->toBe($testModel->id);
});
