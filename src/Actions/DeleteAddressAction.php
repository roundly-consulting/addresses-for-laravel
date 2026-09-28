<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Actions;

use RoundlyConsulting\Addresses\Address;

/**
 * Soft-deletes an address. The model's `deleted` hook dispatches AddressDeleted.
 */
final readonly class DeleteAddressAction
{
    public function execute(Address $address): void
    {
        $address->delete();
    }
}
