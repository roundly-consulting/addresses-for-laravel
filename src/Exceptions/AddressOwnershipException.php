<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Exceptions;

final class AddressOwnershipException extends AddressesException
{
    public static function make(): self
    {
        return new self('The given address does not belong to this model.');
    }
}
