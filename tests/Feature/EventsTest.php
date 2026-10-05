<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Events\AddressCreated;
use RoundlyConsulting\Addresses\Events\AddressDeleted;
use RoundlyConsulting\Addresses\Events\AddressUpdated;
use RoundlyConsulting\Addresses\Events\PrimaryAddressChanged;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\Tests\TestModel;

it('dispatches created on create', function () {
    Event::fake([AddressCreated::class]);
    $entity = TestModel::create();

    Addresses::for($entity)->new()->in('A')->at('B')->postalCode('C')->country('SK')->save();

    Event::assertDispatched(AddressCreated::class);
});

it('dispatches deleted on delete', function () {
    Event::fake([AddressDeleted::class]);
    $entity = TestModel::create();
    $address = Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
    ]);

    $address->delete();

    Event::assertDispatched(AddressDeleted::class, fn (AddressDeleted $event): bool => $event->address->is($address));
});

it('dispatches primary changed on promotion', function () {
    Event::fake([PrimaryAddressChanged::class]);
    $entity = TestModel::create();
    $address = Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Home->value,
        'is_primary' => false,
    ]);

    Addresses::for($entity)->setPrimary($address);

    Event::assertDispatched(PrimaryAddressChanged::class, fn (PrimaryAddressChanged $event): bool => $event->address->is($address));
});

it('does not dispatch updated unless updated', function () {
    Event::fake([AddressUpdated::class]);
    $entity = TestModel::create();
    Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
    ]);

    Event::assertNotDispatched(AddressUpdated::class);
});

/**
 * Real listeners, not `Event::fake()`: the fake records a dispatch the moment it happens, so
 * it cannot tell an event deferred to the host's commit from one dispatched inside it.
 *
 * @return ArrayObject<int, string> the base name of every package event, in dispatch order
 */
function listenToAddressEvents(): ArrayObject
{
    /** @var ArrayObject<int, string> $log */
    $log = new ArrayObject;

    foreach ([AddressCreated::class, AddressUpdated::class, PrimaryAddressChanged::class, AddressDeleted::class] as $event) {
        Event::listen($event, function (object $fired) use ($log): void {
            $log[] = class_basename($fired);
        });
    }

    return $log;
}

/**
 * The four writes that dispatch a package event, in a fixed order: add a Billing primary,
 * update a stored plain address, promote it, delete the added one.
 */
function runEveryAddressEvent(TestModel $entity, Address $stored): void
{
    $added = Addresses::for($entity)->add(AddressData::make(
        city: 'Bratislava',
        street: 'Main 1',
        postalCode: '81101',
        countryIso: 'SK',
        type: AddressType::Billing,
        isPrimary: true,
    ));

    Addresses::update($stored, AddressData::make(city: 'Kosice', street: 'Main 2', postalCode: '04001', countryIso: 'SK'));
    Addresses::for($entity)->setPrimary($stored);
    Addresses::delete($added);
}

/**
 * @return list<string>
 */
function everyAddressEvent(): array
{
    return ['AddressCreated', 'PrimaryAddressChanged', 'AddressUpdated', 'PrimaryAddressChanged', 'AddressDeleted'];
}

it('dispatches no event for writes a host transaction rolls back', function (): void {
    $log = listenToAddressEvents();
    $entity = TestModel::create();
    $stored = Address::factory()->create(['addressable_id' => $entity->id, 'addressable_type' => TestModel::class, 'city' => 'Zilina']);

    expect(fn () => DB::transaction(function () use ($entity, $stored): void {
        runEveryAddressEvent($entity, $stored);

        throw new RuntimeException('host rollback');
    }))->toThrow(RuntimeException::class, 'host rollback');

    expect($log->getArrayCopy())->toBe([])
        ->and(Address::withTrashed()->count())->toBe(1)
        ->and(Address::query()->sole()->city)->toBe('Zilina');
});

it('dispatches every event once the host transaction commits, in order', function (): void {
    $log = listenToAddressEvents();
    $entity = TestModel::create();
    $stored = Address::factory()->create(['addressable_id' => $entity->id, 'addressable_type' => TestModel::class]);

    $during = DB::transaction(function () use ($entity, $stored, $log): array {
        runEveryAddressEvent($entity, $stored);

        return $log->getArrayCopy();
    });

    expect($during)->toBe([])
        ->and($log->getArrayCopy())->toBe(everyAddressEvent());
});

it('dispatches every event at once when there is no outer transaction', function (): void {
    $log = listenToAddressEvents();
    $entity = TestModel::create();
    $stored = Address::factory()->create(['addressable_id' => $entity->id, 'addressable_type' => TestModel::class]);

    runEveryAddressEvent($entity, $stored);

    expect($log->getArrayCopy())->toBe(everyAddressEvent());
});

function deletedBillingPrimary(TestModel $entity): Address
{
    $address = Addresses::for($entity)->add(AddressData::make(
        city: 'Bratislava',
        street: 'Main 1',
        postalCode: '81101',
        countryIso: 'SK',
        type: AddressType::Billing,
        isPrimary: true,
    ));

    Addresses::delete($address);

    return $address;
}

describe('restoring a deleted primary', function (): void {
    it('dispatches primary changed once when the address comes back as the primary', function (): void {
        $address = deletedBillingPrimary($entity = TestModel::create());
        Event::fake([PrimaryAddressChanged::class]);

        expect($address->restore())->toBeTrue()
            ->and(Addresses::for($entity)->primary(AddressType::Billing)?->is($address))->toBeTrue();

        Event::assertDispatchedTimes(PrimaryAddressChanged::class, 1);
        Event::assertDispatched(PrimaryAddressChanged::class, fn (PrimaryAddressChanged $event): bool => $event->address === $address && $event->address->is_primary);
    });

    it('dispatches nothing when the address does not come back as the primary, or comes back quietly', function (Closure $prepare, Closure $restore): void {
        $address = deletedBillingPrimary($entity = TestModel::create());
        $prepare($entity);
        $log = listenToAddressEvents();

        expect($restore($address))->toBeTrue()
            ->and($log->getArrayCopy())->toBe([]);
    })->with([
        'another primary took the slot' => [
            fn (TestModel $entity): Address => Addresses::for($entity)->add(AddressData::make(city: 'Kosice', street: 'Main 2', postalCode: '04001', countryIso: 'SK', type: AddressType::Billing, isPrimary: true)),
            function (Address $address): bool {
                $restored = $address->restore();

                expect($address->is_primary)->toBeFalse();

                return $restored;
            },
        ],
        'restoreQuietly()' => [
            fn (): null => null,
            function (Address $address): bool {
                $restored = $address->restoreQuietly();

                expect($address->fresh()?->is_primary)->toBeTrue();

                return $restored;
            },
        ],
    ]);

    it('dispatches nothing for a restore a host transaction rolls back', function (): void {
        $address = deletedBillingPrimary(TestModel::create());
        $log = listenToAddressEvents();

        expect(fn () => DB::transaction(function () use ($address): void {
            $address->restore();

            throw new RuntimeException('host rollback');
        }))->toThrow(RuntimeException::class, 'host rollback');

        expect($log->getArrayCopy())->toBe([])
            ->and(Address::withTrashed()->findOrFail($address->id)->trashed())->toBeTrue();
    });
});
