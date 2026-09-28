<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses;

use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\Actions\CreateAddressAction;
use RoundlyConsulting\Addresses\Actions\DeleteAddressAction;
use RoundlyConsulting\Addresses\Actions\SetPrimaryAddressAction;
use RoundlyConsulting\Addresses\Actions\UpdateAddressAction;
use RoundlyConsulting\Addresses\Contracts\CountryResolver;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Support\AddressModel;

/**
 * The root of the `Addresses` facade: an owner's address book (`for()`), plus the
 * operations on a single address that need no owner scope.
 *
 * Deliberately not `final`: `Testing\AddressesFake` extends it, so a constructor-injected
 * manager keeps working under `Addresses::fake()`.
 */
class AddressManager
{
    public function __construct(
        protected readonly Container $container,
    ) {}

    /**
     * The address book of one owner: add, read and promote its addresses.
     */
    public function for(Model $addressable): AddressBook
    {
        return new AddressBook($this, $addressable);
    }

    /**
     * Overwrite an address with new data; a primary flag promotes it within its type group.
     */
    public function update(Address $address, AddressData $data): Address
    {
        return $this->container->make(UpdateAddressAction::class)->execute($address, $data);
    }

    /**
     * Soft-delete an address (dispatches AddressDeleted).
     */
    public function delete(Address $address): void
    {
        $this->container->make(DeleteAddressAction::class)->execute($address);
    }

    /**
     * The country's name from the bound CountryResolver, falling back to the normalised
     * ISO code when no resolver is bound or it does not know the code.
     */
    public function countryName(string $iso): string
    {
        $iso = strtoupper(trim($iso));

        if (! $this->container->bound(CountryResolver::class)) {
            return $iso;
        }

        return $this->container->make(CountryResolver::class)->name($iso) ?? $iso;
    }

    /*
     * The operations behind for(). They are public only so the address book can reach
     * them, and @internal so the facade never documents them. They are also the ONE place
     * AddressesFake overrides: every call — through the facade, an injected manager or the
     * HasAddresses trait — lands here.
     */

    /**
     * @internal the body of `for($addressable)->add()` and `->new()->save()`
     */
    public function addFor(Model $addressable, AddressData $data): Address
    {
        return $this->container->make(CreateAddressAction::class)->execute($addressable, $data);
    }

    /**
     * @internal the body of `for($addressable)->setPrimary()`
     */
    public function setPrimaryFor(Model $addressable, Address $address): Address
    {
        return $this->container->make(SetPrimaryAddressAction::class)->execute($addressable, $address);
    }

    /**
     * @internal the body of `for($addressable)->primary()`
     */
    public function primaryFor(Model $addressable, ?AddressType $type = null): ?Address
    {
        return $this->query($addressable)
            ->where('is_primary', true)
            ->when($type instanceof AddressType, fn (Builder $query) => $query->where('type', $type?->value))
            ->first();
    }

    /**
     * @internal the body of `for($addressable)->ofType()`
     *
     * @return Collection<int, Address>
     */
    public function ofTypeFor(Model $addressable, AddressType $type): Collection
    {
        return $this->query($addressable)
            ->where('type', $type->value)
            ->get();
    }

    /**
     * @internal the body of `for($addressable)->all()`
     *
     * @return Collection<int, Address>
     */
    public function allFor(Model $addressable): Collection
    {
        return $this->query($addressable)->get();
    }

    /**
     * @return Builder<Address>
     */
    private function query(Model $addressable): Builder
    {
        return AddressModel::class()::query()
            ->whereMorphedTo('addressable', $addressable)
            ->oldest('id');
    }
}
