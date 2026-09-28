<?php

declare(strict_types=1);

use PHPUnit\Framework\ExpectationFailedException;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\AddressManager;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\AddressOwnershipException;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\Testing\AddressesFake;
use RoundlyConsulting\Addresses\Tests\TestModel;

function fakeAddress(AddressType $type = AddressType::Default, bool $primary = false, string $city = 'Bratislava'): AddressData
{
    return AddressData::make(city: $city, street: 'Main 1', postalCode: '81101', countryIso: 'SK', type: $type, isPrimary: $primary);
}

function storedAddress(TestModel $owner, bool $primary = false): Address
{
    return Address::factory()->create([
        'addressable_id' => $owner->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Default->value,
        'is_primary' => $primary,
    ]);
}

it('swaps the facade and the container binding for a subtype of the manager', function (): void {
    $fake = Addresses::fake();

    expect($fake)->toBeInstanceOf(AddressesFake::class)
        ->toBeInstanceOf(AddressManager::class)
        ->and(Addresses::getFacadeRoot())->toBe($fake)
        ->and(app(AddressManager::class))->toBe($fake);
});

it('records adds from the book, the builder and every trait shortcut without writing', function (): void {
    $fake = Addresses::fake();
    $owner = TestModel::create();

    $viaBook = Addresses::for($owner)->add(fakeAddress(AddressType::Billing, primary: true));
    Addresses::for($owner)->new()->at('Main 2')->in('Kosice')->postalCode('04001')->country('SK')->save();
    $owner->addAddress(fakeAddress(AddressType::Home));
    $owner->createAddress('Zilina', 'Main 3', '01001', 'SK');
    $owner->newAddress()->at('Main 4')->in('Nitra')->postalCode('94901')->country('SK')->save();

    expect($viaBook->exists)->toBeFalse()
        ->and($viaBook->isOwnedBy($owner))->toBeTrue()
        ->and(Address::query()->count())->toBe(0);

    $fake->assertAdded($owner);
    $fake->assertAdded($owner, fn (AddressData $data): bool => $data->city === 'Kosice');
    $fake->assertAdded(where: fn (AddressData $data): bool => $data->city === 'Nitra');
    $fake->assertAdded($owner, fn (AddressData $data): bool => $data->type === AddressType::Home);
});

it('answers reads from stored rows plus the addresses added through the fake', function (): void {
    $owner = TestModel::create();
    $stored = storedAddress($owner, primary: true);
    Addresses::fake();

    expect(Addresses::for($owner)->primary()?->is($stored))->toBeTrue();

    $first = Addresses::for($owner)->add(fakeAddress(primary: true));
    $second = Addresses::for($owner)->add(fakeAddress(primary: true, city: 'Kosice'));
    Addresses::for($owner)->add(fakeAddress(AddressType::Work));

    expect(Addresses::for($owner)->all())->toHaveCount(4)
        ->and(Addresses::for($owner)->ofType(AddressType::Work))->toHaveCount(1)
        ->and(Addresses::for($owner)->primary())->toBe($second)
        ->and($first->is_primary)->toBeFalse()
        ->and(Addresses::for($owner)->primary(AddressType::Work))->toBeNull()
        ->and($owner->hasAddresses())->toBeTrue()
        ->and(Addresses::for(TestModel::create())->all())->toHaveCount(0);
});

it('records updates and deletes without writing', function (): void {
    $owner = TestModel::create();
    $address = storedAddress($owner);
    $fake = Addresses::fake();

    $updated = Addresses::update($address, fakeAddress(city: 'Zilina'));
    Addresses::delete($address);

    expect($updated->city)->toBe('Zilina')
        ->and($address->fresh()?->city)->not->toBe('Zilina')
        ->and($address->fresh()?->trashed())->toBeFalse();

    $fake->assertUpdated($address);
    $fake->assertUpdated($address, fn (AddressData $data): bool => $data->city === 'Zilina');
    $fake->assertUpdated();
    $fake->assertDeleted($address);
    $fake->assertDeleted();
});

it('records primary changes from the book and the trait, and still refuses another owner', function (): void {
    $owner = TestModel::create();
    $stored = storedAddress($owner);
    $fake = Addresses::fake();

    $added = Addresses::for($owner)->add(fakeAddress(primary: true));
    $other = Addresses::for($owner)->add(fakeAddress(city: 'Kosice'));

    Addresses::for($owner)->setPrimary($other);
    $owner->setPrimaryAddress($stored);

    expect($added->is_primary)->toBeFalse()
        ->and($stored->fresh()?->is_primary)->toBeFalse();

    $fake->assertPrimarySet($other);
    $fake->assertPrimarySet($stored);

    Addresses::for(TestModel::create())->setPrimary($stored);
})->throws(AddressOwnershipException::class);

it('passes every assertNothing* on a fresh fake', function (): void {
    $fake = Addresses::fake();

    $fake->assertNothingAdded();
    $fake->assertNothingUpdated();
    $fake->assertNothingDeleted();
    $fake->assertNothingPrimarySet();

    expect(true)->toBeTrue();
});

it('fails a positive assertion when nothing matching was recorded', function (Closure $assert): void {
    $fake = Addresses::fake();
    $owner = TestModel::create();
    $address = storedAddress($owner);

    // Record one of each against a different owner/address, so the scoped checks have a
    // near-miss to reject rather than an empty list.
    $other = TestModel::create();
    $otherAddress = storedAddress($other);
    $other->addAddress(fakeAddress());
    Addresses::update($otherAddress, fakeAddress());
    Addresses::delete($otherAddress);
    Addresses::for($other)->setPrimary($otherAddress);

    $assert($fake, $owner, $address);
})->with([
    'assertAdded (owner via the trait)' => [fn (AddressesFake $fake, TestModel $owner) => $fake->assertAdded($owner)],
    'assertAdded (callback)' => [fn (AddressesFake $fake) => $fake->assertAdded(where: fn (AddressData $data): bool => $data->city === 'Nowhere')],
    'assertUpdated' => [fn (AddressesFake $fake, TestModel $owner, Address $address) => $fake->assertUpdated($address)],
    'assertUpdated (callback)' => [fn (AddressesFake $fake) => $fake->assertUpdated(where: fn (AddressData $data): bool => $data->city === 'Nowhere')],
    'assertDeleted' => [fn (AddressesFake $fake, TestModel $owner, Address $address) => $fake->assertDeleted($address)],
    'assertPrimarySet' => [fn (AddressesFake $fake, TestModel $owner, Address $address) => $fake->assertPrimarySet($address)],
])->throws(ExpectationFailedException::class);

it('fails a positive assertion on an empty fake', function (Closure $assert): void {
    $assert(Addresses::fake());
})->with([
    'assertAdded' => [fn (AddressesFake $fake) => $fake->assertAdded()],
    'assertUpdated' => [fn (AddressesFake $fake) => $fake->assertUpdated()],
    'assertDeleted' => [fn (AddressesFake $fake) => $fake->assertDeleted()],
    'assertPrimarySet' => [fn (AddressesFake $fake) => $fake->assertPrimarySet()],
])->throws(ExpectationFailedException::class);

it('fails an assertNothing* once the matching call was recorded', function (Closure $act, Closure $assert): void {
    $owner = TestModel::create();
    $address = storedAddress($owner);
    $fake = Addresses::fake();

    $act($owner, $address);

    $assert($fake);
})->with([
    'assertNothingAdded via the trait' => [
        fn (TestModel $owner) => $owner->createAddress('Zilina', 'Main 3', '01001', 'SK'),
        fn (AddressesFake $fake) => $fake->assertNothingAdded(),
    ],
    'assertNothingUpdated' => [
        fn (TestModel $owner, Address $address) => Addresses::update($address, fakeAddress()),
        fn (AddressesFake $fake) => $fake->assertNothingUpdated(),
    ],
    'assertNothingDeleted' => [
        fn (TestModel $owner, Address $address) => Addresses::delete($address),
        fn (AddressesFake $fake) => $fake->assertNothingDeleted(),
    ],
    'assertNothingPrimarySet via the trait' => [
        fn (TestModel $owner, Address $address) => $owner->setPrimaryAddress($address),
        fn (AddressesFake $fake) => $fake->assertNothingPrimarySet(),
    ],
])->throws(ExpectationFailedException::class);
