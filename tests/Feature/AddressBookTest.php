<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\AddressBook;
use RoundlyConsulting\Addresses\AddressManager;
use RoundlyConsulting\Addresses\Contracts\CountryResolver;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Events\AddressDeleted;
use RoundlyConsulting\Addresses\Events\AddressUpdated;
use RoundlyConsulting\Addresses\Events\PrimaryAddressChanged;
use RoundlyConsulting\Addresses\Exceptions\AddressOwnershipException;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\PendingAddress;
use RoundlyConsulting\Addresses\Tests\Support\StaticCountryResolver;
use RoundlyConsulting\Addresses\Tests\TestModel;

function bookAddress(AddressType $type = AddressType::Default, bool $primary = false, string $city = 'Bratislava'): AddressData
{
    return AddressData::make(
        city: $city,
        street: 'Main 1',
        postalCode: '81101',
        countryIso: 'sk',
        type: $type,
        isPrimary: $primary,
    );
}

it('resolves the manager from the container and runs the same API', function (): void {
    $manager = app(AddressManager::class);
    $owner = TestModel::create();

    $address = $manager->for($owner)->add(bookAddress(primary: true));

    expect($manager)->toBe(Addresses::getFacadeRoot())
        ->and($manager->for($owner))->toBeInstanceOf(AddressBook::class)
        ->and($manager->for($owner)->primary()?->is($address))->toBeTrue()
        ->and($manager->countryName('sk'))->toBe('SK');
});

it('builds a fluent address from new()', function (): void {
    $owner = TestModel::create();

    $pending = Addresses::for($owner)->new();

    expect($pending)->toBeInstanceOf(PendingAddress::class);

    $address = $pending->at('Main 1')->in('Kosice')->postalCode('04001')->country('sk')->save();

    expect($address->exists)->toBeTrue()
        ->and($address->isOwnedBy($owner))->toBeTrue()
        ->and($address->country_iso)->toBe('SK');
});

it('adds from a DTO and reads the book', function (): void {
    $owner = TestModel::create();
    $other = TestModel::create();

    $billing = Addresses::for($owner)->add(bookAddress(AddressType::Billing, primary: true));
    Addresses::for($owner)->add(bookAddress(AddressType::Billing, city: 'Kosice'));
    $home = Addresses::for($owner)->add(bookAddress(AddressType::Home, primary: true));
    Addresses::for($other)->add(bookAddress(AddressType::Billing, primary: true));

    expect(Addresses::for($owner)->all())->toHaveCount(3)
        ->and(Addresses::for($owner)->ofType(AddressType::Billing))->toHaveCount(2)
        ->and(Addresses::for($owner)->primary()?->is($billing))->toBeTrue()
        ->and(Addresses::for($owner)->primary(AddressType::Home)?->is($home))->toBeTrue()
        ->and(Addresses::for($owner)->primary(AddressType::Work))->toBeNull()
        ->and(Addresses::for(TestModel::create())->all())->toHaveCount(0);
});

it('promotes one of the owner\'s addresses with setPrimary()', function (): void {
    Event::fake([PrimaryAddressChanged::class]);
    $owner = TestModel::create();
    $first = Addresses::for($owner)->add(bookAddress(primary: true));
    $second = Addresses::for($owner)->add(bookAddress(city: 'Kosice'));

    $promoted = Addresses::for($owner)->setPrimary($second);

    expect($promoted->is_primary)->toBeTrue()
        ->and($first->fresh()?->is_primary)->toBeFalse()
        ->and(Addresses::for($owner)->primary()?->is($second))->toBeTrue();

    Event::assertDispatched(PrimaryAddressChanged::class);
});

it('refuses to promote another owner\'s address', function (): void {
    $theirs = Addresses::for(TestModel::create())->add(bookAddress());

    try {
        Addresses::for(TestModel::create())->setPrimary($theirs);
    } finally {
        expect($theirs->fresh()?->is_primary)->toBeFalse();
    }
})->throws(AddressOwnershipException::class);

it('updates an address through the facade', function (): void {
    Event::fake([AddressUpdated::class]);
    $owner = TestModel::create();
    $address = Addresses::for($owner)->add(bookAddress());

    $updated = Addresses::update($address, bookAddress(AddressType::Office, primary: true, city: 'Zilina'));

    expect($updated->city)->toBe('Zilina')
        ->and($updated->type)->toBe(AddressType::Office)
        ->and($updated->is_primary)->toBeTrue();

    Event::assertDispatched(AddressUpdated::class);
});

it('deletes an address through the facade', function (): void {
    Event::fake([AddressDeleted::class]);
    $address = Addresses::for(TestModel::create())->add(bookAddress());

    Addresses::delete($address);

    expect(Address::query()->count())->toBe(0)
        ->and(Address::withTrashed()->count())->toBe(1);

    Event::assertDispatched(AddressDeleted::class);
});

it('names a country through the bound resolver, falling back to the ISO code', function (): void {
    expect(Addresses::countryName(' sk '))->toBe('SK');

    app()->instance(CountryResolver::class, new StaticCountryResolver);

    expect(Addresses::countryName('sk'))->toBe('Slovakia')
        ->and(Addresses::countryName('xx'))->toBe('XX');
});

it('routes every trait shortcut through the address book', function (): void {
    $owner = TestModel::create();
    $other = TestModel::create();

    $address = $owner->createAddress('Bratislava', 'Main 1', '81101', 'SK', type: AddressType::Work);

    expect($owner->addressBook())->toBeInstanceOf(AddressBook::class)
        ->and($owner->hasAddresses())->toBeTrue()
        ->and($other->hasAddresses())->toBeFalse()
        ->and($owner->getAddressOfType(AddressType::Work)?->is($address))->toBeTrue();

    $owner->setPrimaryAddress($address);

    expect($owner->getPrimaryAddressOfType(AddressType::Work)?->is($address))->toBeTrue()
        ->and($owner->getAddressOfType(AddressType::Work, onlyPrimary: true)?->is($address))->toBeTrue();

    $other->setPrimaryAddress($address);
})->throws(AddressOwnershipException::class);
