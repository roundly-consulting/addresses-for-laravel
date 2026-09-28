<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Support;

use RoundlyConsulting\Addresses\Exceptions\InvalidCountryException;

/**
 * Trims, upper-cases, and shape-checks a country code: two or three letters, the
 * shape of ISO 3166-1 alpha-2/alpha-3. Pure string logic — it bundles no country
 * list and makes no network call — so it does not know whether a code is
 * assigned (`XX` passes) and never maps alpha-3 onto alpha-2 (`SVK` stays `SVK`).
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
