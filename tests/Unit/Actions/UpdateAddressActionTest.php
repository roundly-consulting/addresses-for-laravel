<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Addresses\Actions\UpdateAddressAction;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Events\AddressUpdated;
use RoundlyConsulting\Addresses\Events\PrimaryAddressChanged;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\Tests\TestModel;

it('updates the address fields', function () {
    $entity = TestModel::create();

    $address = Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'city' => 'Old',
    ]);

    $updated = app(UpdateAddressAction::class)->execute($address, AddressData::make(
        city: 'New',
        street: 'Street',
        postalCode: '00000',
        countryIso: 'SK',
        type: AddressType::Billing,
    ));

    expect($updated)
        ->city->toBe('New')
        ->type->toBe(AddressType::Billing);
});

it('promotes to primary when requested', function () {
    $entity = TestModel::create();

    $address = Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => AddressType::Home->value,
        'is_primary' => false,
    ]);

    app(UpdateAddressAction::class)->execute($address, AddressData::make(
        city: 'New',
        street: 'Street',
        postalCode: '00000',
        countryIso: 'SK',
        type: AddressType::Home,
        isPrimary: true,
    ));

    expect($address->refresh()->is_primary)->toBeTrue();
});

it('dispatches the updated event', function () {
    Event::fake();

    $entity = TestModel::create();
    $address = Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
    ]);

    app(UpdateAddressAction::class)->execute($address, AddressData::make(
        city: 'New',
        street: 'Street',
        postalCode: '00000',
        countryIso: 'SK',
    ));

    Event::assertDispatched(AddressUpdated::class);
});

/**
 * A stored plain address and a second copy of it: the first goes stale once the second is
 * changed behind it.
 *
 * @return array{0: TestModel, 1: Address}
 */
function staleAddressCopy(AddressType $type = AddressType::Default): array
{
    $entity = TestModel::create();

    return [$entity, Address::factory()->create([
        'addressable_id' => $entity->id,
        'addressable_type' => TestModel::class,
        'type' => $type->value,
        'is_primary' => false,
        'city' => 'Bratislava',
    ])];
}

it('demotes the stored primary through a stale plain copy', function () {
    [$entity, $stale] = staleAddressCopy();
    Addresses::for($entity)->setPrimary(Address::query()->findOrFail($stale->id));

    $updated = app(UpdateAddressAction::class)->execute($stale, AddressData::make(
        city: 'Kosice',
        street: 'Street',
        postalCode: '00000',
        countryIso: 'SK',
        isPrimary: false,
    ));

    expect($stale->fresh()?->is_primary)->toBeFalse()
        ->and($updated->is_primary)->toBeFalse()
        ->and($updated->city)->toBe('Kosice');
});

it('writes a DTO value over a column changed behind a stale copy', function () {
    [, $stale] = staleAddressCopy();
    Address::query()->whereKey($stale->id)->update(['city' => 'Changed elsewhere']);

    app(UpdateAddressAction::class)->execute($stale, AddressData::make(
        city: 'Bratislava',
        street: 'Street',
        postalCode: '00000',
        countryIso: 'SK',
    ));

    expect($stale->fresh()?->city)->toBe('Bratislava');
});

it('decides a type change from the stored type, not a stale copy', function () {
    [$entity, $stale] = staleAddressCopy();
    Addresses::update(Address::query()->findOrFail($stale->id), AddressData::make(
        city: 'Bratislava',
        street: 'Street',
        postalCode: '00000',
        countryIso: 'SK',
        type: AddressType::Billing,
        isPrimary: true,
    ));
    Event::fake([PrimaryAddressChanged::class]);

    app(UpdateAddressAction::class)->execute($stale, AddressData::make(
        city: 'Bratislava',
        street: 'Street',
        postalCode: '00000',
        countryIso: 'SK',
        type: AddressType::Default,
        isPrimary: true,
    ));

    expect($stale->fresh())
        ->type->toBe(AddressType::Default)
        ->is_primary->toBeTrue()
        ->and(Addresses::for($entity)->primary(AddressType::Billing))->toBeNull();
    Event::assertDispatched(PrimaryAddressChanged::class, fn (PrimaryAddressChanged $event): bool => $event->address->is($stale));
});

it('keeps the caller\'s own unsaved changes to columns the DTO does not carry', function () {
    [, $address] = staleAddressCopy();
    $address->created_at = Carbon::parse('2020-01-01 00:00:00');

    app(UpdateAddressAction::class)->execute($address, AddressData::make(
        city: 'Kosice',
        street: 'Street',
        postalCode: '00000',
        countryIso: 'SK',
    ));

    expect($address->fresh()?->created_at?->toDateTimeString())->toBe('2020-01-01 00:00:00');
});

it('never writes a stale copy\'s clean columns back: a row trashed behind it stays trashed', function () {
    [, $stale] = staleAddressCopy();
    Address::query()->whereKey($stale->id)->delete();

    app(UpdateAddressAction::class)->execute($stale, AddressData::make(
        city: 'Kosice',
        street: 'Street',
        postalCode: '00000',
        countryIso: 'SK',
    ));

    expect(Address::withTrashed()->findOrFail($stale->id))
        ->trashed()->toBeTrue()
        ->city->toBe('Kosice');
});

it('still refuses an address whose row is gone', function (Closure $address) {
    app(UpdateAddressAction::class)->execute($address(), AddressData::make(
        city: 'Kosice',
        street: 'Street',
        postalCode: '00000',
        countryIso: 'SK',
        isPrimary: true,
    ));
})->with([
    'never saved' => [fn (): Address => Address::factory()->make(['addressable_id' => TestModel::create()->id, 'addressable_type' => TestModel::class])],
    'force-deleted' => [function (): Address {
        [, $address] = staleAddressCopy();
        Address::query()->whereKey($address->id)->forceDelete();

        return $address;
    }],
])->throws(ModelNotFoundException::class);
