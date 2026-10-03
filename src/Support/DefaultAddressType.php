<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Support;

use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\InvalidAddressTypeException;

/**
 * The AddressType an address gets when its creator names none: `addresses.default_type`,
 * or `AddressType::Default` when the key is not set (absent, null or blank: `''` or
 * whitespace). Any other value that is not an AddressType — an unknown name, a non-string —
 * fails loudly rather than quietly storing a type nobody configured.
 */
final class DefaultAddressType
{
    /**
     * @throws InvalidAddressTypeException
     */
    public static function resolve(): AddressType
    {
        $configured = config('addresses.default_type');

        if ($configured instanceof AddressType) {
            return $configured;
        }

        if ($configured === null || (is_string($configured) && trim($configured) === '')) {
            return AddressType::Default;
        }

        if (! is_string($configured)) {
            throw InvalidAddressTypeException::for(get_debug_type($configured));
        }

        return AddressType::tryFrom(strtolower(trim($configured)))
            ?? throw InvalidAddressTypeException::for($configured);
    }
}
