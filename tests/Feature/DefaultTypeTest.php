<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\InvalidAddressTypeException;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\Tests\TestModel;

/**
 * `addresses.default_type` is the type every creation path falls back to when the caller
 * names none. It used to be read by `php artisan about` and nothing else.
 */
it('applies the configured default type on every path that takes no type', function (Closure $create): void {
    config()->set('addresses.default_type', 'billing');

    expect($create(TestModel::create())->type)->toBe(AddressType::Billing);
})->with([
    'builder' => [fn (TestModel $owner) => Addresses::for($owner)->new()
        ->in('Bratislava')->at('Main 1')->postalCode('81101')->country('SK')->save()],
    'trait newAddress()' => [fn (TestModel $owner) => $owner->newAddress()
        ->in('Bratislava')->at('Main 1')->postalCode('81101')->country('SK')->save()],
    'trait createAddress()' => [fn (TestModel $owner) => $owner->createAddress(
        city: 'Bratislava', street: 'Main 1', postalCode: '81101', countryIsoCode: 'SK',
    )],
    'AddressData::make()' => [fn (TestModel $owner) => Addresses::for($owner)->add(AddressData::make(
        city: 'Bratislava', street: 'Main 1', postalCode: '81101', countryIso: 'SK',
    ))],
    'new AddressData()' => [fn (TestModel $owner) => Addresses::for($owner)->add(new AddressData(
        city: 'Bratislava', street: 'Main 1', postalCode: '81101', countryIso: 'SK',
    ))],
]);

it('lets an explicit type win over the configured default', function (): void {
    config()->set('addresses.default_type', 'billing');

    $address = Addresses::for(TestModel::create())->new()
        ->type(AddressType::Home)
        ->in('Bratislava')->at('Main 1')->postalCode('81101')->country('SK')
        ->save();

    expect($address->type)->toBe(AddressType::Home);
});

it('accepts the default type as an enum case or a padded, upper-case value', function (mixed $configured): void {
    config()->set('addresses.default_type', $configured);

    expect(AddressData::make(city: 'A', street: 'B', postalCode: 'C', countryIso: 'SK')->type)
        ->toBe(AddressType::Shipping);
})->with([
    'enum case' => [AddressType::Shipping],
    'padded upper-case string' => [' SHIPPING '],
]);

it('uses the Default type when the key is not set', function (?string $configured): void {
    config()->set('addresses.default_type', $configured);

    expect(AddressData::make(city: 'A', street: 'B', postalCode: 'C', countryIso: 'SK')->type)
        ->toBe(AddressType::Default);
})->with(['absent' => null, 'empty string' => '', 'blank string' => '  ']);

it('refuses a default type that is not an AddressType (strict config)', function (mixed $configured): void {
    config()->set('addresses.default_type', $configured);

    AddressData::make(city: 'A', street: 'B', postalCode: 'C', countryIso: 'SK');
})->with([
    'unknown value' => ['warehouse'],
    'not a string' => [42],
    'typo' => ['shiping'],
])->throws(InvalidAddressTypeException::class);
