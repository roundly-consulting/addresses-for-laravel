<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Exceptions\AddressOwnershipException;

/**
 * Promotes one of an owner's addresses to primary within its type group, demoting the
 * siblings. An address that belongs to another owner is refused — the owner scope is the
 * security boundary, so a caller holding one owner cannot reshuffle another's addresses.
 */
final readonly class SetPrimaryAddressAction
{
    /**
     * @throws AddressOwnershipException
     */
    public function execute(Model $addressable, Address $address): Address
    {
        if (! $address->isOwnedBy($addressable)) {
            throw AddressOwnershipException::make();
        }

        $address->markAsPrimary();

        return $address;
    }
}
