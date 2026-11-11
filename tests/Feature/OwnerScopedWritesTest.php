<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\AddressManager;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Events\AddressDeleted;
use RoundlyConsulting\Addresses\Events\AddressUpdated;
use RoundlyConsulting\Addresses\Events\PrimaryAddressChanged;
use RoundlyConsulting\Addresses\Exceptions\AddressesException;
use RoundlyConsulting\Addresses\Exceptions\AddressOwnershipException;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\Tests\Support\OtherOwner;
use RoundlyConsulting\Addresses\Tests\TestModel;

/**
 * `Addresses::for($owner)->update() / ->delete() / ->setPrimary()` — the owner-checked
 * writes. The bare `Addresses::update()` / `::delete()` take any address; these refuse one
 * that belongs to another owner, compared by morph type AND key, read from the stored row.
 */
function scopedData(string $city = 'Zilina', AddressType $type = AddressType::Default, bool $primary = false): AddressData
{
    return AddressData::make(city: $city, street: 'Main 1', postalCode: '81101', countryIso: 'SK', type: $type, isPrimary: $primary);
}

/**
 * Two owners whose address the first one must not touch: another TestModel (same morph type,
 * different key) and an OtherOwner sharing the first owner's key (different morph type).
 *
 * @return array{0: TestModel, 1: Address}
 */
function foreignAddress(string $kind): array
{
    $owner = TestModel::create();

    $theirs = match ($kind) {
        'same type, different key' => Addresses::for(TestModel::create())->add(scopedData('Kosice')),
        'different type, same key' => Addresses::for(OtherOwner::query()->findOrFail($owner->getKey()))->add(scopedData('Kosice')),
    };

    return [$owner, $theirs];
}

dataset('foreign owners', ['same type, different key', 'different type, same key']);

it('updates the owner\'s own address', function (): void {
    Event::fake([AddressUpdated::class, PrimaryAddressChanged::class]);
    $owner = TestModel::create();
    $address = Addresses::for($owner)->add(scopedData('Bratislava'));

    $updated = Addresses::for($owner)->update($address, scopedData('Zilina', AddressType::Office, primary: true));

    expect($updated->city)->toBe('Zilina')
        ->and($updated->type)->toBe(AddressType::Office)
        ->and($updated->is_primary)->toBeTrue();

    Event::assertDispatched(AddressUpdated::class);
    Event::assertDispatched(PrimaryAddressChanged::class);
});

it('refuses to update another owner\'s address and writes nothing', function (string $kind): void {
    Event::fake([AddressUpdated::class]);
    [$owner, $theirs] = foreignAddress($kind);

    expect(fn () => Addresses::for($owner)->update($theirs, scopedData('Zilina', primary: true)))
        ->toThrow(AddressOwnershipException::class);

    expect($theirs->fresh()?->city)->toBe('Kosice')
        ->and($theirs->fresh()?->is_primary)->toBeFalse()
        ->and($theirs->city)->toBe('Kosice');

    Event::assertNotDispatched(AddressUpdated::class);
})->with('foreign owners');

it('reads the owner from the stored row, not a forged copy', function (): void {
    $owner = TestModel::create();
    $theirs = Addresses::for(TestModel::create())->add(scopedData('Kosice'));

    // The copy claims to be the owner's; the row is not.
    $forged = Address::query()->findOrFail($theirs->getKey());
    $forged->setRawAttributes([...$forged->getAttributes(), 'addressable_id' => $owner->getKey()], true);

    expect(fn () => Addresses::for($owner)->update($forged, scopedData()))->toThrow(AddressOwnershipException::class)
        ->and(fn () => Addresses::for($owner)->delete($forged))->toThrow(AddressOwnershipException::class);

    expect($theirs->fresh()?->city)->toBe('Kosice')
        ->and($theirs->fresh()?->trashed())->toBeFalse();
});

it('refuses an update that would hand the owner\'s address to someone else', function (): void {
    $owner = TestModel::create();
    $other = TestModel::create();
    $address = Addresses::for($owner)->add(scopedData('Bratislava'));

    $address->addressable_id = $other->id;

    expect(fn () => Addresses::for($owner)->update($address, scopedData()))->toThrow(AddressOwnershipException::class);

    expect($address->fresh()?->addressable_id)->toBe($owner->id)
        ->and($address->fresh()?->city)->toBe('Bratislava');
});

it('deletes the owner\'s own address', function (): void {
    Event::fake([AddressDeleted::class]);
    $owner = TestModel::create();
    $address = Addresses::for($owner)->add(scopedData());

    Addresses::for($owner)->delete($address);

    expect($address->fresh()?->trashed())->toBeTrue();

    Event::assertDispatched(AddressDeleted::class);
});

it('refuses to delete another owner\'s address', function (string $kind): void {
    Event::fake([AddressDeleted::class]);
    [$owner, $theirs] = foreignAddress($kind);

    expect(fn () => Addresses::for($owner)->delete($theirs))->toThrow(AddressOwnershipException::class);

    expect($theirs->fresh()?->trashed())->toBeFalse();

    Event::assertNotDispatched(AddressDeleted::class);
})->with('foreign owners');

it('refuses to promote another owner\'s address', function (string $kind): void {
    [$owner, $theirs] = foreignAddress($kind);

    expect(fn () => Addresses::for($owner)->setPrimary($theirs))->toThrow(AddressOwnershipException::class);

    expect($theirs->fresh()?->is_primary)->toBeFalse();
})->with('foreign owners');

it('throws an exception a host can catch as any addresses error', function (): void {
    [$owner, $theirs] = foreignAddress('same type, different key');

    expect(fn () => Addresses::for($owner)->delete($theirs))->toThrow(AddressesException::class);
});

it('runs the same owner checks through an injected manager', function (): void {
    $manager = app(AddressManager::class);
    $owner = TestModel::create();
    $mine = $manager->for($owner)->add(scopedData('Bratislava'));
    [, $theirs] = foreignAddress('different type, same key');

    expect($manager->for($owner)->update($mine, scopedData('Nitra'))->city)->toBe('Nitra')
        ->and(fn () => $manager->for($owner)->update($theirs, scopedData()))->toThrow(AddressOwnershipException::class);

    $manager->for($owner)->delete($mine);

    expect($mine->fresh()?->trashed())->toBeTrue();
});

it('keeps the bare update() and delete() unscoped', function (): void {
    $theirs = Addresses::for(TestModel::create())->add(scopedData('Kosice'));

    Addresses::update($theirs, scopedData('Nitra'));
    Addresses::delete($theirs);

    expect($theirs->fresh()?->city)->toBe('Nitra')
        ->and($theirs->fresh()?->trashed())->toBeTrue();
});
