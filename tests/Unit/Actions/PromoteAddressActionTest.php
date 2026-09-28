<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\ModelNotFoundException;
use RoundlyConsulting\Addresses\Actions\PromoteAddressAction;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Tests\TestModel;

function promotable(TestModel $owner, bool $primary = false, AddressType $type = AddressType::Default): Address
{
    return Address::factory()->create([
        'addressable_id' => $owner->id,
        'addressable_type' => TestModel::class,
        'type' => $type->value,
        'is_primary' => $primary,
    ]);
}

it('promotes the address, demotes its group and reports the change', function () {
    $owner = TestModel::create();
    $current = promotable($owner, primary: true);
    $others = collect([promotable($owner), promotable($owner)]);
    $work = promotable($owner, primary: true, type: AddressType::Work);
    $address = promotable($owner);

    expect(app(PromoteAddressAction::class)->execute($address))->toBeTrue()
        ->and($address->is_primary)->toBeTrue()
        ->and($address->isDirty('is_primary'))->toBeFalse()
        ->and($address->fresh()?->is_primary)->toBeTrue()
        ->and($current->fresh()?->is_primary)->toBeFalse()
        ->and($others->every(fn (Address $other): bool => $other->fresh()?->is_primary === false))->toBeTrue()
        ->and($work->fresh()?->is_primary)->toBeTrue();
});

it('reports no change for the address that already is the primary', function () {
    $owner = TestModel::create();
    $address = promotable($owner, primary: true);

    expect(app(PromoteAddressAction::class)->execute($address))->toBeFalse()
        ->and($address->fresh()?->is_primary)->toBeTrue();
});

it('reads the group from the stored row, not from a stale copy', function () {
    $owner = TestModel::create();
    $home = promotable($owner, primary: true, type: AddressType::Home);
    $address = promotable($owner, type: AddressType::Home);
    $stale = Address::query()->findOrFail($address->id);
    $stale->type = AddressType::Work;

    app(PromoteAddressAction::class)->execute($stale);

    expect($address->fresh()?->is_primary)->toBeTrue()
        ->and($home->fresh()?->is_primary)->toBeFalse();
});

it('refuses an address that is not stored', function (Closure $address) {
    app(PromoteAddressAction::class)->execute($address(TestModel::create()));
})->with([
    'never saved' => [fn (TestModel $owner): Address => Address::factory()->make(['addressable_id' => $owner->id, 'addressable_type' => TestModel::class])],
    'force-deleted' => [function (TestModel $owner): Address {
        $address = promotable($owner);
        Address::query()->whereKey($address->id)->forceDelete();

        return $address;
    }],
])->throws(ModelNotFoundException::class);
