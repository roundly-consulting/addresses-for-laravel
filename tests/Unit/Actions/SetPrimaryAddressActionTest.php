<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Actions\SetPrimaryAddressAction;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\AddressOwnershipException;
use RoundlyConsulting\Addresses\Tests\TestModel;

function ownedAddress(TestModel $owner, AddressType $type, bool $primary): Address
{
    return Address::factory()->create([
        'addressable_id' => $owner->id,
        'addressable_type' => TestModel::class,
        'type' => $type->value,
        'is_primary' => $primary,
    ]);
}

it('promotes the address and demotes only its type group', function (): void {
    $owner = TestModel::create();
    $current = ownedAddress($owner, AddressType::Home, true);
    $next = ownedAddress($owner, AddressType::Home, false);
    $work = ownedAddress($owner, AddressType::Work, true);

    $promoted = app(SetPrimaryAddressAction::class)->execute($owner, $next);

    expect($promoted->is_primary)->toBeTrue()
        ->and($current->fresh()?->is_primary)->toBeFalse()
        ->and($work->fresh()?->is_primary)->toBeTrue();
});

it('refuses an address owned by someone else', function (): void {
    $address = ownedAddress(TestModel::create(), AddressType::Home, false);

    app(SetPrimaryAddressAction::class)->execute(TestModel::create(), $address);
})->throws(AddressOwnershipException::class);
