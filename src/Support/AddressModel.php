<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Support;

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing addresses from `addresses.model`.
 *
 * Absent config resolves the packaged model; anything else must be that model or a subclass of
 * it, or the toolkit's ModelResolver throws InvalidConfigurationException naming the key — a
 * foreign class is never silently replaced.
 */
final class AddressModel
{
    /**
     * @return class-string<Address>
     */
    public static function class(): string
    {
        return ModelResolver::for('addresses.model', Address::class);
    }
}
