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

it('refuses a foreign model instead of falling back to the packaged one', function (): void {
    // The toolkit refuses any class that is not the packaged model or a subclass of it.
    config()->set('addresses.model', TestModel::class);

    expect(fn (): string => AddressModel::class())->toThrow(
        InvalidConfigurationException::class,
        'Configuration value [addresses.model] must be a class-string of ['.Address::class.'], ['.TestModel::class.'] given.',
    );
});

it('rejects a configured class that is not an eloquent model', function (): void {
    config()->set('addresses.model', 'not-a-class');

    AddressModel::class();
})->throws(InvalidConfigurationException::class);
