<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Events\PrimaryAddressChanged;
use RoundlyConsulting\Addresses\Exceptions\AddressOwnershipException;
use RoundlyConsulting\Addresses\Exceptions\TrashedAddressException;

/**
 * Promotes one of an owner's addresses to primary within its type group, demoting the
 * siblings. An address that belongs to another owner is refused — the owner scope is the
 * security boundary, so a caller holding one owner cannot reshuffle another's addresses —
 * and so is a soft-deleted one, which could never be read back as the primary.
 */
final readonly class SetPrimaryAddressAction
{
    public function __construct(
        private PromoteAddressAction $promote,
    ) {}

    /**
     * @throws AddressOwnershipException
     * @throws TrashedAddressException
     */
    public function execute(Model $addressable, Address $address): Address
    {
        if (! $address->isOwnedBy($addressable)) {
            throw AddressOwnershipException::make();
        }

        if ($this->promote->execute($address, $addressable)) {
            PrimaryAddressChanged::dispatch($address);
        }

        return $address;
    }
}
