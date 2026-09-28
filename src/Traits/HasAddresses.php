<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Traits;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\AddressBook;
use RoundlyConsulting\Addresses\AddressManager;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\AddressOwnershipException;
use RoundlyConsulting\Addresses\PendingAddress;
use RoundlyConsulting\Addresses\Support\AddressModel;

/**
 * @mixin Model
 */
trait HasAddresses
{
    /**
     * @return MorphMany<Address, $this>
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(AddressModel::class(), 'addressable');
    }

    public function getAddressOfType(AddressType $type, bool $onlyPrimary = false): ?Address
    {
        return $onlyPrimary
            ? $this->getPrimaryAddressOfType($type)
            : $this->addressBook()->ofType($type)->first();
    }

    public function getPrimaryAddressOfType(AddressType $type): ?Address
    {
        return $this->addressBook()->primary($type);
    }

    /**
     * @param  AddressType|null  $type  null falls back to `addresses.default_type`
     * @param  Collection<array-key, mixed>|null  $meta
     */
    public function createAddress(
        string $city,
        string $street,
        string $postalCode,
        string $countryIsoCode,
        ?string $name = null,
        bool $isPrimary = false,
        ?AddressType $type = null,
        ?Collection $meta = null,
    ): Address {
        return $this->addAddress(AddressData::make(
            city: $city,
            street: $street,
            postalCode: $postalCode,
            countryIso: $countryIsoCode,
            name: $name,
            type: $type,
            isPrimary: $isPrimary,
            meta: $meta,
        ));
    }

    public function addAddress(AddressData $data): Address
    {
        return $this->addressBook()->add($data);
    }

    public function newAddress(): PendingAddress
    {
        return $this->addressBook()->new();
    }

    /**
     * The primary address, optionally scoped to a type.
     */
    public function primaryAddress(?AddressType $type = null): ?Address
    {
        return $this->addressBook()->primary($type);
    }

    /**
     * @return EloquentCollection<int, Address>
     */
    public function addressesOfType(AddressType $type): EloquentCollection
    {
        return $this->addressBook()->ofType($type);
    }

    public function hasAddresses(): bool
    {
        return $this->addressBook()->all()->isNotEmpty();
    }

    /**
     * Promote one of this owner's addresses to primary within its type group.
     *
     * @throws AddressOwnershipException when the address does not belong to this model
     */
    public function setPrimaryAddress(Address $address): void
    {
        $this->addressBook()->setPrimary($address);
    }

    /**
     * This model's address book — every shortcut above goes through it, so the
     * `Addresses` facade, host overrides and `Addresses::fake()` see each call.
     */
    public function addressBook(): AddressBook
    {
        return app(AddressManager::class)->for($this);
    }
}
