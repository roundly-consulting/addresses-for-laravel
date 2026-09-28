<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Exceptions;

final class TrashedAddressException extends AddressesException
{
    public static function cannotBePrimary(): self
    {
        return new self('A deleted address cannot be made primary; restore it first.');
    }
}
