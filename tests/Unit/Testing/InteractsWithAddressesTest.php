<?php

declare(strict_types=1);

use PHPUnit\Framework\AssertionFailedError;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Testing\InteractsWithAddresses;
use RoundlyConsulting\Addresses\Tests\TestModel;

uses(InteractsWithAddresses::class);

it('passes when a matching address exists', function () {
    $entity = TestModel::create();
    Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'city' => 'Bratislava',
    ]);

    $this->assertHasAddress($entity, ['city' => 'Bratislava']);
});

it('fails when no matching address exists', function () {
    $entity = TestModel::create();

    $this->assertHasAddress($entity, ['city' => 'Nowhere']);
})->throws(AssertionFailedError::class);

it('passes when the primary address of a type exists', function () {
    $entity = TestModel::create();
    Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Home->value,
        'is_primary' => true,
    ]);

    $this->assertPrimaryAddress($entity, AddressType::Home);
});

it('fails when there is no primary address of a type', function () {
    $entity = TestModel::create();

    $this->assertPrimaryAddress($entity, AddressType::Home);
})->throws(AssertionFailedError::class);
