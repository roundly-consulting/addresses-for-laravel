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
    | Default Address Type
    |--------------------------------------------------------------------------
    |
    | The AddressType used when none is provided when creating an address.
    |
    */

    'default_type' => AddressType::Default->value,

    /*
    |--------------------------------------------------------------------------
    | Normalise Country Codes
    |--------------------------------------------------------------------------
    |
    | When true, country codes are trimmed, upper-cased, and validated as ISO
    | 3166-1 alpha-2/alpha-3 on the way in. Set to false to store them verbatim.
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
    |
    */

    'country_resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Facade Alias
    |--------------------------------------------------------------------------
    |
    | The class alias registered for the Addresses facade. Set to null to skip
    | registering a global alias.
    |
    */

    'facade_alias' => 'Addresses',

];
