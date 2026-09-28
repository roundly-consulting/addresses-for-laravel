<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Events\PrimaryAddressChanged;
use RoundlyConsulting\Addresses\Exceptions\AddressOwnershipException;
use RoundlyConsulting\Addresses\Exceptions\TrashedAddressException;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\Tests\TestModel;

function primaryData(AddressType $type = AddressType::Shipping, bool $primary = true, string $city = 'Bratislava'): AddressData
{
    return AddressData::make(city: $city, street: 'Main 1', postalCode: '81101', countryIso: 'SK', type: $type, isPrimary: $primary);
}

function groupAddress(TestModel $owner, AddressType $type = AddressType::Shipping, bool $primary = false): Address
{
    return Address::factory()->create([
        'addressable_id' => $owner->id,
        'addressable_type' => TestModel::class,
        'type' => $type->value,
        'is_primary' => $primary,
    ]);
}

/**
 * @return list<int>
 */
function primaryIds(TestModel $owner, AddressType $type = AddressType::Shipping): array
{
    return Address::withTrashed()
        ->whereMorphedTo('addressable', $owner)
        ->where('type', $type->value)
        ->where('is_primary', true)
        ->pluck('id')
        ->map(fn (mixed $id): int => (int) $id)
        ->all();
}

/**
 * Runs `$then` once, right after the first statement demoting the siblings of a promotion —
 * the point where the old code had already demoted everyone and not yet promoted anyone.
 */
function afterDemotingSiblings(Closure $then): void
{
    $fired = false;

    DB::listen(function (QueryExecuted $query) use (&$fired, $then): void {
        $sql = strtolower($query->sql);

        if ($fired || ! str_starts_with($sql, 'update') || ! in_array(false, $query->bindings, true)) {
            return;
        }

        $fired = true;
        $then();
    });
}

describe('PrimaryAddressChanged', function (): void {
    it('fires on every path that makes an address primary', function (Closure $promote): void {
        $owner = TestModel::create();
        $sibling = groupAddress($owner, primary: true);
        $target = groupAddress($owner);
        Event::fake([PrimaryAddressChanged::class]);

        $promoted = $promote($owner, $target);

        Event::assertDispatchedTimes(PrimaryAddressChanged::class, 1);
        Event::assertDispatched(PrimaryAddressChanged::class, fn (PrimaryAddressChanged $event): bool => $event->address->is($promoted));
        expect(primaryIds($owner))->toBe([$promoted->id])
            ->and($sibling->fresh()?->is_primary)->toBeFalse();
    })->with([
        'book add()' => [fn (TestModel $owner): Address => Addresses::for($owner)->add(primaryData())],
        'builder primary()->save()' => [fn (TestModel $owner): Address => Addresses::for($owner)->new()
            ->type(AddressType::Shipping)->primary()
            ->in('Kosice')->at('Main 2')->postalCode('04001')->country('SK')->save()],
        'trait createAddress(isPrimary: true)' => [fn (TestModel $owner): Address => $owner->createAddress(
            city: 'Kosice', street: 'Main 2', postalCode: '04001', countryIsoCode: 'SK', isPrimary: true, type: AddressType::Shipping,
        )],
        'trait addAddress()' => [fn (TestModel $owner): Address => $owner->addAddress(primaryData())],
        'Addresses::update(isPrimary: true)' => [fn (TestModel $owner, Address $target): Address => Addresses::update($target, primaryData())],
        'book setPrimary()' => [fn (TestModel $owner, Address $target): Address => Addresses::for($owner)->setPrimary($target)],
        'trait setPrimaryAddress()' => [function (TestModel $owner, Address $target): Address {
            $owner->setPrimaryAddress($target);

            return $target;
        }],
    ]);

    it('fires for each promotion in a sequence of add and update', function (): void {
        $owner = TestModel::create();
        $fired = 0;
        Event::listen(PrimaryAddressChanged::class, function () use (&$fired): void {
            $fired++;
        });

        Addresses::for($owner)->add(primaryData());
        $other = Addresses::for($owner)->add(primaryData(primary: false));
        Addresses::update($other, primaryData());

        expect($fired)->toBe(2)
            ->and(primaryIds($owner))->toBe([$other->id]);
    });

    it('does not fire when the address already is the primary', function (Closure $keep): void {
        $owner = TestModel::create();
        $primary = groupAddress($owner, primary: true);
        Event::fake([PrimaryAddressChanged::class]);

        $keep($owner, $primary);

        Event::assertNotDispatched(PrimaryAddressChanged::class);
        expect(primaryIds($owner))->toBe([$primary->id]);
    })->with([
        'setPrimary()' => [fn (TestModel $owner, Address $primary) => Addresses::for($owner)->setPrimary($primary)],
        'update(isPrimary: true)' => [fn (TestModel $owner, Address $primary) => Addresses::update($primary, primaryData(city: 'Kosice'))],
    ]);

    it('fires when an update moves the primary into another type group', function (): void {
        $owner = TestModel::create();
        $home = groupAddress($owner, AddressType::Home, primary: true);
        $work = groupAddress($owner, AddressType::Work, primary: true);
        Event::fake([PrimaryAddressChanged::class]);

        Addresses::update($home, primaryData(AddressType::Work));

        Event::assertDispatchedTimes(PrimaryAddressChanged::class, 1);
        expect(primaryIds($owner, AddressType::Work))->toBe([$home->id])
            ->and(primaryIds($owner, AddressType::Home))->toBe([])
            ->and($work->fresh()?->is_primary)->toBeFalse();
    });

    it('is dispatched after AddressCreated when an address is added as primary', function (): void {
        $owner = TestModel::create();
        $order = [];
        Event::listen('*', function (string $event) use (&$order): void {
            if (str_starts_with($event, 'RoundlyConsulting\\Addresses\\Events\\')) {
                $order[] = class_basename($event);
            }
        });

        Addresses::for($owner)->add(primaryData());

        expect($order)->toBe(['AddressCreated', 'PrimaryAddressChanged']);
    });
});

describe('atomic promotion', function (): void {
    it('rolls the demotion back when the promotion fails half-way', function (Closure $promote): void {
        $owner = TestModel::create();
        $primary = groupAddress($owner, primary: true);
        $target = groupAddress($owner);

        afterDemotingSiblings(fn () => throw new RuntimeException('connection lost'));

        expect(fn () => $promote($owner, $target))->toThrow(RuntimeException::class, 'connection lost');

        expect(primaryIds($owner))->toBe([$primary->id]);
    })->with([
        'setPrimary()' => [fn (TestModel $owner, Address $target) => Addresses::for($owner)->setPrimary($target)],
        'update(isPrimary: true)' => [fn (TestModel $owner, Address $target) => Addresses::update($target, primaryData(city: 'Kosice'))],
        'add(isPrimary: true)' => [fn (TestModel $owner) => Addresses::for($owner)->add(primaryData())],
    ]);

    it('rolls the new row back too when adding a primary fails', function (): void {
        $owner = TestModel::create();
        groupAddress($owner, primary: true);

        afterDemotingSiblings(fn () => throw new RuntimeException('connection lost'));

        expect(fn () => Addresses::for($owner)->add(primaryData(city: 'Kosice')))->toThrow(RuntimeException::class);

        expect(Address::query()->where('city', 'Kosice')->exists())->toBeFalse();
    });

    it('lets the database refuse a second primary in one owner and type group', function (): void {
        $owner = TestModel::create();
        groupAddress($owner, primary: true);

        groupAddress($owner, primary: true);
    })->throws(UniqueConstraintViolationException::class);

    it('allows one primary per type and per owner, and any number of non-primaries', function (): void {
        $owner = TestModel::create();
        $other = TestModel::create();

        groupAddress($owner, AddressType::Home, primary: true);
        groupAddress($owner, AddressType::Work, primary: true);
        groupAddress($other, AddressType::Home, primary: true);
        groupAddress($owner, AddressType::Home);
        groupAddress($owner, AddressType::Home);

        expect(Address::query()->count())->toBe(5);
    });

    it('retries a promotion that loses the race to a concurrent primary', function (): void {
        $owner = TestModel::create();
        $target = groupAddress($owner);
        Event::fake([PrimaryAddressChanged::class]);

        // The interleaving Postgres allows: a racing transaction commits a primary our lock
        // could not see (it was inserted after our snapshot), so our promote collides with it.
        afterDemotingSiblings(fn () => DB::table('addresses')->insert([
            'addressable_type' => TestModel::class,
            'addressable_id' => $owner->id,
            'type' => AddressType::Shipping->value,
            'is_primary' => true,
            'city' => 'Racer',
        ]));

        Addresses::for($owner)->setPrimary($target);

        expect(primaryIds($owner))->toBe([$target->id]);
        Event::assertDispatchedTimes(PrimaryAddressChanged::class, 1);
    });
});

describe('the promotion guard', function (): void {
    it('refuses to make a soft-deleted address primary and leaves the group alone', function (Closure $promote): void {
        $owner = TestModel::create();
        $primary = groupAddress($owner, primary: true);
        $trashed = groupAddress($owner);
        Addresses::delete($trashed);
        Event::fake([PrimaryAddressChanged::class]);

        expect(fn () => $promote($owner, $trashed))->toThrow(TrashedAddressException::class);

        Event::assertNotDispatched(PrimaryAddressChanged::class);
        expect(primaryIds($owner))->toBe([$primary->id])
            ->and(Addresses::for($owner)->primary(AddressType::Shipping)?->is($primary))->toBeTrue();
    })->with([
        'setPrimary()' => [fn (TestModel $owner, Address $trashed) => Addresses::for($owner)->setPrimary($trashed)],
        'update(isPrimary: true)' => [fn (TestModel $owner, Address $trashed) => Addresses::update($trashed, primaryData(city: 'Kosice'))],
    ]);

    it('refuses a trashed address even when the caller holds a stale, live-looking copy', function (): void {
        $owner = TestModel::create();
        $primary = groupAddress($owner, primary: true);
        $address = groupAddress($owner);
        Address::query()->whereKey($address->id)->delete();

        expect(fn () => Addresses::for($owner)->setPrimary($address))->toThrow(TrashedAddressException::class)
            ->and(primaryIds($owner))->toBe([$primary->id]);
    });

    it('checks ownership against the stored row, not the in-memory copy', function (): void {
        $owner = TestModel::create();
        $victim = TestModel::create();
        $victimPrimary = groupAddress($victim, primary: true);
        $victimOther = groupAddress($victim);

        // A forged copy: the victim's row id dressed up with the caller's owner.
        $forged = (new Address)->forceFill([
            'id' => $victimOther->id,
            'addressable_type' => TestModel::class,
            'addressable_id' => $owner->id,
            'type' => AddressType::Shipping->value,
            'is_primary' => false,
        ]);
        $forged->exists = true;

        expect(fn () => Addresses::for($owner)->setPrimary($forged))->toThrow(AddressOwnershipException::class)
            ->and(primaryIds($victim))->toBe([$victimPrimary->id]);
    });
});
