<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Tests\Support;

use RoundlyConsulting\Addresses\Contracts\CountryResolver;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\DataTransferObjects\Coordinates;

final class StaticCountryResolver implements CountryResolver
{
    public function name(string $iso): ?string
    {
        return match (strtoupper($iso)) {
            'SK' => 'Slovakia',
            default => null,
        };
    }

    public function coordinates(AddressData $data): ?Coordinates
    {
        if ($this->name($data->countryIso) === null) {
            return null;
        }

        return new Coordinates(48.1486, 17.1077);
    }
}
