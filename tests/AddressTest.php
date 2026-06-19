<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Tests\TestModel;

it('marks all addresses as not primary', function () {
    $addressable = [
        'addressable_id' => TestModel::create()->id,
        'addressable_type' => TestModel::class,
        'type' => 'default',
    ];

    $addresses = Address::factory(5)->create($addressable);

    $addresses->first()->markAsPrimary(isPrimary: false);

    expect(Address::query()->where($addressable + ['is_primary' => false])->count())
        ->toBe(5);
});

it('marks address as primary and others as not primary', function () {
    $addressable = [
        'addressable_id' => TestModel::create()->id,
        'addressable_type' => TestModel::class,
        'type' => 'default',
    ];

    $addresses = Address::factory(4)->create($addressable);
    $address = Address::factory()->create($addressable + ['is_primary' => false]);

    $address->markAsPrimary();

    expect($address->refresh())
        ->is_primary->toBe(true);

    expect(Address::query()->where($addressable + ['is_primary' => false])->count())
        ->toBe(4);
});

it('keeps an already primary address primary without demoting it', function () {
    $addressable = [
        'addressable_id' => TestModel::create()->id,
        'addressable_type' => TestModel::class,
        'type' => 'default',
    ];

    $address = Address::factory()->create($addressable + ['is_primary' => true]);

    $address->markAsPrimary();

    expect($address->refresh())->is_primary->toBeTrue();
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
