<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Enums\AddressType;

return [

    /*
    |--------------------------------------------------------------------------
    | Address Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to store addresses. Override this with your own
    | class (extending the package model) if you need custom behaviour while
    | still using the HasAddresses trait.
    |
    */

    'model' => Address::class,

    /*
    |--------------------------------------------------------------------------
    | Key Type
    |--------------------------------------------------------------------------
    |
    | The key type used for the polymorphic addressable column. Use "uuid" or
    | "ulid" when the models that own addresses use UUID/ULID primary keys,
    | otherwise leave it as "bigint". Your addressable models must share one key
    | type; set this to match them. Any other value throws an
    | InvalidConfigurationException when the migration runs.
    |
    | Supported: "bigint", "uuid", "ulid"
    |
    */

    'key_type' => env('ADDRESSES_KEY_TYPE', 'bigint'),

    /*
    |--------------------------------------------------------------------------
    | Default Address Type
    |--------------------------------------------------------------------------
    |
    | The AddressType value an address gets when its creator names none — the
    | fluent builder without type(), AddressData without a type, and
    | createAddress() without one. A value that is not an AddressType case
    | throws InvalidAddressTypeException when such an address is built.
    |
    | Supported: "default", "billing", "shipping", "home", "work", "office"
    |
    */

    'default_type' => AddressType::Default->value,

    /*
    |--------------------------------------------------------------------------
    | Normalise Country Codes
    |--------------------------------------------------------------------------
    |
    | When true, country codes are trimmed, upper-cased, and checked to be two
    | or three letters (the shape of ISO 3166-1 alpha-2/alpha-3) on the way in.
    | No country list is bundled, so the code itself is not looked up, and an
    | alpha-3 code is kept as alpha-3. Set to false to store codes as given
    | (trimmed); inCountry() then matches them case-insensitively.
    |
    */

    'normalise_country' => true,

    /*
    |--------------------------------------------------------------------------
    | Country Resolver
    |--------------------------------------------------------------------------
    |
    | Optional class-string implementing
    | RoundlyConsulting\Addresses\Contracts\CountryResolver. When set, the
    | package resolves country names (and lets you geocode) through it. The
    | package bundles no implementation and makes no network calls itself.
    | Any value other than null must name such a class, or resolving it throws
    | an InvalidConfigurationException.
    |
    */

    'country_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Facade Alias
    |--------------------------------------------------------------------------
    |
    | The class alias registered for the Addresses facade. Set to null (or
    | false) to skip registering a global alias, or to a string to rename it.
    |
    */

    'facade_alias' => 'Addresses',

];
