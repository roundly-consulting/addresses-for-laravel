<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Support;

use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\InvalidAddressTypeException;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * The type an address gets when its creator names none: `addresses.default_type`, or
 * `AddressType::Default` when the key is not set (absent, null or blank: `''` or
 * whitespace). Any other value that is not a type configured in `addresses.types` — an
 * unknown name, a non-string — fails loudly rather than quietly storing a type nobody
 * configured.
 */
final class DefaultAddressType
{
    /**
     * The default type: its AddressType case for a built-in key, else the custom key.
     *
     * @throws InvalidAddressTypeException
     * @throws InvalidConfigurationException when `addresses.types` is malformed
     */
    public static function type(): AddressType|string
    {
        $configured = config('addresses.default_type');

        if ($configured === null || (is_string($configured) && trim($configured) === '')) {
            $configured = AddressType::Default;
        }

        if (! $configured instanceof AddressType && ! is_string($configured)) {
            throw InvalidAddressTypeException::for(get_debug_type($configured));
        }

        return AddressTypes::resolve($configured);
    }

    /**
     * The default type as an AddressType case. Kept for callers typed on the enum; a custom
     * default type (one outside the six cases) throws — read it with type().
     *
     * @throws InvalidAddressTypeException
     * @throws InvalidConfigurationException when `addresses.types` is malformed
     */
    public static function resolve(): AddressType
    {
        $type = self::type();

        return $type instanceof AddressType ? $type : throw InvalidAddressTypeException::notBuiltIn($type);
    }
}
