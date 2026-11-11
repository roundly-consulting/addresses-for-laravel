<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Exceptions;

final class InvalidAddressTypeException extends AddressesException
{
    public static function for(string $type): self
    {
        return new self(sprintf('The address type [%s] is not one of the configured address types (addresses.types).', $type));
    }

    public static function notBuiltIn(string $type): self
    {
        return new self(sprintf(
            'The address type [%s] is a custom type, not an AddressType case; read it with DefaultAddressType::type().',
            $type,
        ));
    }
}
