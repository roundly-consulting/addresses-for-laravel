<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Contracts;

use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\DataTransferObjects\Coordinates;

/**
 * Optional, host-pluggable resolver for country names and coordinates. The
 * package never binds an implementation that calls the network — a host app
 * binds its own (e.g. a geocoder) via config('addresses.country_resolver').
 */
interface CountryResolver
{
    /**
     * The human-readable country name for an ISO code, or null when unknown.
     */
    public function name(string $iso): ?string;

    /**
     * Geocode the given address, or null when it cannot be resolved.
     */
    public function coordinates(AddressData $data): ?Coordinates;
}
