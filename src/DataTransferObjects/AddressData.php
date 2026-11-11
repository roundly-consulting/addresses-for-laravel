<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\DataTransferObjects;

use Illuminate\Support\Collection;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\InvalidAddressTypeException;
use RoundlyConsulting\Addresses\Exceptions\InvalidCountryException;
use RoundlyConsulting\Addresses\Support\AddressTypes;
use RoundlyConsulting\Addresses\Support\CountryNormaliser;
use RoundlyConsulting\Addresses\Support\DefaultAddressType;
use RoundlyConsulting\PackageToolkit\Support\Config;

final readonly class AddressData
{
    /**
     * The type: its AddressType case for a built-in key, the key itself for a custom type
     * configured in `addresses.types`. `typeKey()` is the string either way.
     */
    public AddressType|string $type;

    /**
     * @param  AddressType|string|null  $type  a type configured in `addresses.types`; null falls back to `addresses.default_type`
     * @param  Collection<array-key, mixed>|null  $meta
     *
     * @throws InvalidAddressTypeException when the type (or, with none given, the configured default) is not configured
     */
    public function __construct(
        public string $city,
        public string $street,
        public string $postalCode,
        public string $countryIso,
        public ?string $name = null,
        AddressType|string|null $type = null,
        public bool $isPrimary = false,
        public ?Collection $meta = null,
    ) {
        $this->type = $type === null ? DefaultAddressType::type() : AddressTypes::resolve($type);
    }

    /**
     * Named-argument-friendly factory that trims string fields and, when
     * country normalisation is enabled, normalises the country code on the way in.
     *
     * @param  AddressType|string|null  $type  a type configured in `addresses.types`; null falls back to `addresses.default_type`
     * @param  Collection<array-key, mixed>|null  $meta
     *
     * @throws InvalidCountryException when normalisation is on and the code is not two or three letters
     * @throws InvalidAddressTypeException when the type (or, with none given, the configured default) is not configured
     */
    public static function make(
        string $city,
        string $street,
        string $postalCode,
        string $countryIso,
        ?string $name = null,
        AddressType|string|null $type = null,
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
     * The type as stored: the key of a built-in or a custom type.
     */
    public function typeKey(): string
    {
        return AddressTypes::key($this->type);
    }

    /**
     * The attributes used to write the underlying model. A framework-shaped
     * array (an Eloquent write payload), which the standard explicitly allows.
     *
     * @return array<model-property<Address>, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'type' => $this->typeKey(),
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
        if (! Config::boolean('addresses.normalise_country', true)) {
            return trim($countryIso);
        }

        return CountryNormaliser::normalise($countryIso);
    }
}
