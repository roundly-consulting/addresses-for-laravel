<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Contracts;

use Illuminate\Database\Eloquent\Collection;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\PendingAddress;

/**
 * Implemented by models using the HasAddresses trait. Lets consumers typehint
 * `Addressable` instead of referencing the trait directly.
 */
interface Addressable
{
    public function getAddressOfType(AddressType $type, bool $onlyPrimary = false): ?Address;

    public function getPrimaryAddressOfType(AddressType $type): ?Address;

    public function addAddress(AddressData $data): Address;

    public function newAddress(): PendingAddress;

    public function primaryAddress(?AddressType $type = null): ?Address;

    /**
     * @return Collection<int, Address>
     */
    public function addressesOfType(AddressType $type): Collection;

    public function hasAddresses(): bool;

    public function setPrimaryAddress(Address $address): void;
}
