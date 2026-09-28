<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Actions\DeleteAddressAction;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Tests\TestModel;

it('soft-deletes the address', function (): void {
    $owner = TestModel::create();
    $address = Address::factory()->create([
        'addressable_id' => $owner->id,
        'addressable_type' => TestModel::class,
    ]);

    app(DeleteAddressAction::class)->execute($address);

    expect($address->fresh()?->trashed())->toBeTrue();
});
