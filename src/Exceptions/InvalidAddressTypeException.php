<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Exceptions;

final class InvalidAddressTypeException extends AddressesException
{
    public static function for(string $type): self
    {
        return new self(sprintf('The address type [%s] is not a valid AddressType.', $type));
    }
}
