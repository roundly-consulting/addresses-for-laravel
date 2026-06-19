<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Support;

use RoundlyConsulting\Addresses\Exceptions\InvalidCountryException;

/**
 * Trims, upper-cases, and validates a country code as ISO 3166-1 alpha-2 or
 * alpha-3. Pure string logic — it bundles no country list and makes no network
 * call, keeping the runtime dependency footprint to Laravel/Symfony only.
 */
final class CountryNormaliser
{
    /**
     * @throws InvalidCountryException
     */
    public static function normalise(string $country): string
    {
        $normalised = strtoupper(trim($country));

        if (preg_match('/^[A-Z]{2}$|^[A-Z]{3}$/', $normalised) !== 1) {
            throw InvalidCountryException::for($country);
        }

        return $normalised;
    }
}
