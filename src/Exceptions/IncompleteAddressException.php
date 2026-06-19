<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Exceptions;

final class IncompleteAddressException extends AddressesException
{
    /**
     * @param  list<string>  $missing
     */
    public static function missing(array $missing): self
    {
        return new self(sprintf(
            'The address is missing required field(s): [%s].',
            implode(', ', $missing),
        ));
    }
}
