<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\DataTransferObjects;

use Illuminate\Support\Collection;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Support\CountryNormaliser;

final readonly class AddressData
{
    /**
     * @param  Collection<array-key, mixed>|null  $meta
     */
    public function __construct(
        public string $city,
        public string $street,
        public string $postalCode,
        public string $countryIso,
        public ?string $name = null,
        public AddressType $type = AddressType::Default,
        public bool $isPrimary = false,
        public ?Collection $meta = null,
    ) {}

    /**
     * Named-argument-friendly factory that trims string fields and, when
     * country normalisation is enabled, normalises the ISO code on the way in.
     *
     * @param  Collection<array-key, mixed>|null  $meta
     */
    public static function make(
        string $city,
        string $street,
        string $postalCode,
        string $countryIso,
        ?string $name = null,
        AddressType $type = AddressType::Default,
        bool $isPrimary = false,
        ?Collection $meta = null,
    ): self {
        $name = $name === null ? null : trim($name);

        return new self(
            city: trim($city),
            street: trim($street),
            postalCode: trim($postalCode),
            countryIso: self::resolveCountry($countryIso),
            name: $name === '' ? null : $name,
            type: $type,
            isPrimary: $isPrimary,
            meta: $meta,
        );
    }

    /**
     * The attributes used to write the underlying model. A framework-shaped
     * array (an Eloquent write payload), which the standard explicitly allows.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'type' => $this->type->value,
            'is_primary' => $this->isPrimary,
            'name' => $this->name,
            'city' => $this->city,
            'street' => $this->street,
            'postal_code' => $this->postalCode,
            'country_iso' => $this->countryIso,
            'meta' => $this->meta,
        ];
    }

    private static function resolveCountry(string $countryIso): string
    {
        if (config('addresses.normalise_country', true) === false) {
            return trim($countryIso);
        }

        return CountryNormaliser::normalise($countryIso);
    }
}
