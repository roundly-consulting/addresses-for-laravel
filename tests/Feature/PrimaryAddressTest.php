<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
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

/**
 * Runs `$then` once, right after a restore has read and locked its owner + type group — the
 * point where a racing primary its lock could not see would slip in.
 */
function afterLockingTheGroup(Closure $then): Closure
{
    $fired = false;

    DB::listen(function (QueryExecuted $query) use (&$fired, $then): void {
        $sql = strtolower($query->sql);

        if ($fired || ! str_starts_with($sql, 'select') || ! str_contains($sql, 'order by')) {
            return;
        }

        $fired = true;
        $then();
    });

    return function () use (&$fired): bool {
        return $fired;
    };
}

describe('a deleted primary', function (): void {
    it('holds no primary slot, so a primary written past the promotion is accepted', function (): void {
        $owner = TestModel::create();
        $deleted = groupAddress($owner, primary: true);
        Addresses::delete($deleted);

        $fresh = groupAddress($owner, primary: true);

        expect(Addresses::for($owner)->primary(AddressType::Shipping)?->is($fresh))->toBeTrue();
    });

    it('comes back as the primary when its group has none', function (): void {
        $owner = TestModel::create();
        $deleted = groupAddress($owner, primary: true);
        groupAddress($owner);
        Addresses::delete($deleted);

        expect($deleted->restore())->toBeTrue()
            ->and(primaryIds($owner))->toBe([$deleted->id])
            ->and(Addresses::for($owner)->primary(AddressType::Shipping)?->is($deleted))->toBeTrue();
    });

    it('comes back as a plain address once another was promoted', function (Closure $promote): void {
        $owner = TestModel::create();
        $deleted = groupAddress($owner, primary: true);
        $target = groupAddress($owner);
        Addresses::delete($deleted);

        $promoted = $promote($owner, $target);
        $deleted->restore();

        expect(primaryIds($owner))->toBe([$promoted->id])
            ->and($deleted->trashed())->toBeFalse()
            ->and($deleted->is_primary)->toBeFalse();
    })->with([
        'setPrimary()' => [fn (TestModel $owner, Address $target): Address => Addresses::for($owner)->setPrimary($target)],
        'update(isPrimary: true)' => [fn (TestModel $owner, Address $target): Address => Addresses::update($target, primaryData())],
        'add(isPrimary: true)' => [fn (TestModel $owner): Address => Addresses::for($owner)->add(primaryData())],
    ]);

    it('comes back as a plain address when a primary was written past the promotion meanwhile', function (Closure $restore): void {
        $owner = TestModel::create();
        $deleted = groupAddress($owner, primary: true);
        Addresses::delete($deleted);
        $fresh = groupAddress($owner, primary: true);

        $restore($deleted);

        expect(primaryIds($owner))->toBe([$fresh->id])
            ->and(Address::query()->find($deleted->id)?->is_primary)->toBeFalse()
            ->and($deleted->trashed())->toBeFalse()
            ->and($deleted->is_primary)->toBeFalse()
            ->and($deleted->isDirty())->toBeFalse();
    })->with([
        'restore()' => [fn (Address $address): bool => $address->restore()],
        'restoreQuietly()' => [fn (Address $address): bool => $address->restoreQuietly()],
        'a copy that thinks it is plain' => [function (Address $address): bool {
            $address->forceFill(['is_primary' => false])->syncOriginalAttribute('is_primary');

            return $address->restore();
        }],
    ]);

    it('retries a restore that loses the race to a concurrent primary', function (): void {
        $owner = TestModel::create();
        $deleted = groupAddress($owner, primary: true);
        Addresses::delete($deleted);

        // The interleaving Postgres allows: a racing transaction commits a live primary the
        // restore's lock could not see, so the restore collides with it and must yield.
        $fired = afterLockingTheGroup(fn () => DB::table('addresses')->insert([
            'addressable_type' => TestModel::class,
            'addressable_id' => $owner->id,
            'type' => AddressType::Shipping->value,
            'is_primary' => true,
            'city' => 'Racer',
        ]));

        expect($deleted->restore())->toBeTrue()
            ->and($fired())->toBeTrue()
            ->and($deleted->trashed())->toBeFalse()
            ->and(Address::query()->whereKey($deleted->id)->exists())->toBeTrue();
    });

    it('writes nothing when a listener cancels the restore', function (): void {
        $owner = TestModel::create();
        $deleted = groupAddress($owner, primary: true);
        Addresses::delete($deleted);
        $fresh = groupAddress($owner, primary: true);
        Address::restoring(fn (): bool => false);

        $stored = fn (): ?Address => Address::withTrashed()->find($deleted->id);

        expect($deleted->restore())->toBeFalse()
            ->and($deleted->trashed())->toBeTrue()
            ->and($deleted->is_primary)->toBeTrue()
            ->and($stored()?->trashed())->toBeTrue()
            ->and($stored()?->is_primary)->toBeTrue()
            ->and(primaryIds($owner))->toBe([$deleted->id, $fresh->id]);
    });

    it('rolls the yield back with the restore when the restore fails', function (): void {
        $owner = TestModel::create();
        $deleted = groupAddress($owner, primary: true);
        Addresses::delete($deleted);
        groupAddress($owner, primary: true);
        Address::restored(fn () => throw new RuntimeException('listener failed'));

        expect(fn () => $deleted->restore())->toThrow(RuntimeException::class, 'listener failed')
            ->and($deleted->trashed())->toBeTrue()
            ->and($deleted->is_primary)->toBeTrue()
            ->and(Address::withTrashed()->find($deleted->id)?->is_primary)->toBeTrue()
            ->and(Address::withTrashed()->find($deleted->id)?->trashed())->toBeTrue();
    });
});

/**
 * Runs `$then` once, right after the first read of `$address`'s row by key — the window
 * between that unlocked read and the lock on the group it names, where a concurrent write
 * can move the row to another group. One process cannot run two transactions at once, so a
 * write on the same connection at that point is the closest deterministic stand-in.
 */
function afterReadingTheRow(Address $address, Closure $then): void
{
    $fired = false;

    DB::listen(function (QueryExecuted $query) use (&$fired, $then, $address): void {
        $sql = strtolower($query->sql);

        if ($fired || ! str_starts_with($sql, 'select *') || ! str_contains($sql, 'addresses') || $query->bindings != [$address->getKey()]) {
            return;
        }

        $fired = true;
        $then();
    });
}

describe('a row that moves group between its read and the group lock', function (): void {
    it('is promoted in the group it moved to', function (): void {
        $owner = TestModel::create();
        $billing = groupAddress($owner, AddressType::Billing, primary: true);
        $address = groupAddress($owner, AddressType::Default);
        afterReadingTheRow($address, fn () => DB::table('addresses')->where('id', $address->id)->update(['type' => AddressType::Billing->value]));

        Addresses::for($owner)->setPrimary($address);

        expect(primaryIds($owner, AddressType::Billing))->toBe([$address->id])
            ->and($billing->fresh()?->is_primary)->toBeFalse();
    });

    it('is refused once it moved to another owner', function (): void {
        $owner = TestModel::create();
        $address = groupAddress($owner);
        $other = TestModel::create();
        afterReadingTheRow($address, fn () => DB::table('addresses')->where('id', $address->id)->update(['addressable_id' => $other->id]));

        expect(fn () => Addresses::for($owner)->setPrimary($address))->toThrow(AddressOwnershipException::class)
            ->and(primaryIds($other))->toBe([]);
    });

    it('is not found once it is gone', function (): void {
        $owner = TestModel::create();
        $address = groupAddress($owner);
        afterReadingTheRow($address, fn () => DB::table('addresses')->where('id', $address->id)->delete());

        Addresses::for($owner)->setPrimary($address);
    })->throws(ModelNotFoundException::class);

    it('is restored against the group it moved to, keeping one primary there', function (): void {
        $owner = TestModel::create();
        $deleted = groupAddress($owner, AddressType::Default, primary: true);
        Addresses::delete($deleted);
        $shipping = groupAddress($owner, primary: true);

        // The MySQL shape: no one-primary index, so only the lock keeps a second primary out.
        if (Schema::hasIndex('addresses', 'addresses_one_primary_per_type')) {
            Schema::table('addresses', fn (Blueprint $table) => $table->dropIndex('addresses_one_primary_per_type'));
        }

        afterReadingTheRow($deleted, fn () => DB::table('addresses')->where('id', $deleted->id)->update(['type' => AddressType::Shipping->value]));

        expect($deleted->restore())->toBeTrue()
            ->and($deleted->is_primary)->toBeFalse()
            ->and(primaryIds($owner))->toBe([$shipping->id]);
    });
});
