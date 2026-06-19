<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Traits;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use RoundlyConsulting\Addresses\Actions\CreateAddressAction;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\AddressOwnershipException;
use RoundlyConsulting\Addresses\PendingAddress;

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
        /** @var class-string<Address> $model */
        $model = config('addresses.model', Address::class);

        return $this->morphMany($model, 'addressable');
    }

    public function getAddressOfType(AddressType $type, bool $onlyPrimary = false): ?Address
    {
        return $this->addresses()
            ->where('type', $type->value)
            ->when($onlyPrimary, fn ($query) => $query->where('is_primary', true))
            ->first();
    }

    public function getPrimaryAddressOfType(AddressType $type): ?Address
    {
        return $this->getAddressOfType(
            type: $type,
            onlyPrimary: true,
        );
    }

    /**
     * @param  Collection<array-key, mixed>|null  $meta
     */
    public function createAddress(
        string $city,
        string $street,
        string $postalCode,
        string $countryIsoCode,
        ?string $name = null,
        bool $isPrimary = false,
        AddressType $type = AddressType::Default,
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
        return app(CreateAddressAction::class)->execute($this, $data);
    }

    public function newAddress(): PendingAddress
    {
        return new PendingAddress($this);
    }

    /**
     * The primary address, optionally scoped to a type.
     */
    public function primaryAddress(?AddressType $type = null): ?Address
    {
        return $this->addresses()
            ->where('is_primary', true)
            ->when($type instanceof AddressType, fn ($query) => $query->where('type', $type?->value))
            ->first();
    }

    /**
     * @return EloquentCollection<int, Address>
     */
    public function addressesOfType(AddressType $type): EloquentCollection
    {
        /** @var EloquentCollection<int, Address> $addresses */
        $addresses = $this->addresses()
            ->where('type', $type->value)
            ->get();

        return $addresses;
    }

    public function hasAddresses(): bool
    {
        return $this->addresses()->exists();
    }

    /**
     * Promote one of this owner's addresses to primary within its type group.
     *
     * @throws AddressOwnershipException when the address does not belong to this model
     */
    public function setPrimaryAddress(Address $address): void
    {
        $belongsToOwner = (string) $address->addressable_type === $this->getMorphClass()
            && (string) $address->addressable_id === (string) $this->getKey();

        if (! $belongsToOwner) {
            throw AddressOwnershipException::make();
        }

        $address->markAsPrimary();
    }
}
