<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use RoundlyConsulting\Addresses\Address;

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

    public function getAddressOfType(string $type, bool $onlyPrimary = false): ?Address
    {
        return $this->addresses()
            ->where('type', $type)
            ->when($onlyPrimary, fn ($query) => $query->where('is_primary', true))
            ->first();
    }

    public function getPrimaryAddressOfType(string $type): ?Address
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
        string $type = 'default',
        ?Collection $meta = null,
    ): Address {
        return $this->addresses()->create([
            'type' => $type,
            'is_primary' => $isPrimary,
            'name' => $name,
            'city' => $city,
            'street' => $street,
            'postal_code' => $postalCode,
            'country_iso' => $countryIsoCode,
            'meta' => $meta,
        ]);
    }
}
