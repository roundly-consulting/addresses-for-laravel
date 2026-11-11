<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\AddressOwnershipException;

/**
 * `Addresses::for($owner)` — one owner's addresses. Each method goes through the
 * manager, so host overrides and `Addresses::fake()` see it.
 */
final readonly class AddressBook
{
    /**
     * @internal build it with `Addresses::for($addressable)`
     */
    public function __construct(
        private AddressManager $addresses,
        private Model $addressable,
    ) {}

    /**
     * Start a fluent address for this owner; `save()` adds it.
     */
    public function new(): PendingAddress
    {
        return new PendingAddress($this);
    }

    public function add(AddressData $data): Address
    {
        return $this->addresses->addFor($this->addressable, $data);
    }

    /**
     * The primary address, optionally of one type.
     */
    public function primary(?AddressType $type = null): ?Address
    {
        return $this->addresses->primaryFor($this->addressable, $type);
    }

    /**
     * @return Collection<int, Address>
     */
    public function ofType(AddressType $type): Collection
    {
        return $this->addresses->ofTypeFor($this->addressable, $type);
    }

    /**
     * @return Collection<int, Address>
     */
    public function all(): Collection
    {
        return $this->addresses->allFor($this->addressable);
    }

    /**
     * Overwrite one of this owner's addresses with new data, as `Addresses::update()` does —
     * after checking, against the stored row, that the address is this owner's (morph type
     * and key) and that the write does not hand it to another owner.
     *
     * @throws AddressOwnershipException when the address belongs to another owner
     */
    public function update(Address $address, AddressData $data): Address
    {
        return $this->addresses->updateFor($this->addressable, $address, $data);
    }

    /**
     * Soft-delete one of this owner's addresses, as `Addresses::delete()` does — after
     * checking, against the stored row, that the address is this owner's.
     *
     * @throws AddressOwnershipException when the address belongs to another owner
     */
    public function delete(Address $address): void
    {
        $this->addresses->deleteFor($this->addressable, $address);
    }

    /**
     * Promote one of this owner's addresses to primary within its type group.
     *
     * @throws AddressOwnershipException when the address belongs to another owner
     */
    public function setPrimary(Address $address): Address
    {
        return $this->addresses->setPrimaryFor($this->addressable, $address);
    }
}
