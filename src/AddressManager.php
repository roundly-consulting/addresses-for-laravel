<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Support\AddressModel;

class AddressManager
{
    /**
     * Start a fluent address builder for the given addressable model.
     */
    public function for(Model $addressable): PendingAddress
    {
        return new PendingAddress($addressable);
    }

    /**
     * The primary address for an addressable, optionally scoped to a type.
     */
    public function primaryFor(Model $addressable, ?AddressType $type = null): ?Address
    {
        return $this->query($addressable)
            ->where('is_primary', true)
            ->when($type instanceof AddressType, fn ($query) => $query->where('type', $type?->value))
            ->first();
    }

    /**
     * All addresses of a given type for an addressable.
     *
     * @return Collection<int, Address>
     */
    public function ofType(Model $addressable, AddressType $type): Collection
    {
        return $this->query($addressable)
            ->where('type', $type->value)
            ->get();
    }

    /**
     * @return Builder<Address>
     */
    private function query(Model $addressable): Builder
    {
        $model = AddressModel::class();

        return $model::query()
            ->whereMorphedTo('addressable', $addressable);
    }
}
