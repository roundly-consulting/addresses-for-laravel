<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Support\AddressModel;
use RoundlyConsulting\Addresses\Tests\Support\CustomAddress;
use RoundlyConsulting\Addresses\Tests\TestModel;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

it('resolves the packaged model by default', function (): void {
    expect(AddressModel::class())->toBe(Address::class);
});

it('resolves a configured host subclass', function (): void {
    config()->set('addresses.model', CustomAddress::class);

    expect(AddressModel::class())->toBe(CustomAddress::class);
});

it('falls back to the packaged model for a model that is not an address', function (): void {
    config()->set('addresses.model', TestModel::class);

    expect(AddressModel::class())->toBe(Address::class);
});

it('rejects a configured class that is not an eloquent model', function (): void {
    config()->set('addresses.model', 'not-a-class');

    AddressModel::class();
})->throws(InvalidConfigurationException::class);
