<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Exceptions\AddressOwnershipException;

/**
 * Soft-deletes an address. The model's `deleted` hook dispatches AddressDeleted.
 *
 * Given an owner, the address must be theirs: the stored row is read under a lock and
 * compared by morph type and key, so neither a forged copy nor a row moved to another
 * owner meanwhile gets through. Without one, any address is deleted (`Addresses::delete()`).
 */
final readonly class DeleteAddressAction
{
    /**
     * @throws AddressOwnershipException when `$owner` is given and the address is not theirs
     */
    public function execute(Address $address, ?Model $owner = null): void
    {
        if (! $owner instanceof Model) {
            $address->delete();

            return;
        }

        $address->getConnection()->transaction(function () use ($address, $owner): void {
            $stored = $address->newModelQuery()->whereKey($address->getKey())->lockForUpdate()->first();

            if (! $address->isOwnedBy($owner) || ($stored instanceof Address && ! $stored->isOwnedBy($owner))) {
                throw AddressOwnershipException::make();
            }

            $address->delete();
        }, 3);
    }
}
