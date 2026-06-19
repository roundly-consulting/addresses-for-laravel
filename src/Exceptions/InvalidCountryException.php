<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Exceptions;

final class InvalidCountryException extends AddressesException
{
    public static function for(string $country): self
    {
        return new self(sprintf(
            'The value [%s] is not a valid ISO 3166-1 alpha-2 or alpha-3 country code.',
            $country,
        ));
    }
}
