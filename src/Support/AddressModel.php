<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Support;

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\PackageToolkit\Support\ModelResolver;

/**
 * Resolves the Eloquent model backing addresses from `addresses.model`.
 *
 * The toolkit's ModelResolver validates that the configured value is a real
 * Eloquent model; anything that is not an Address (so it cannot answer the
 * package's scopes, primary-address invariant or country accessor) falls back
 * to the packaged model.
 */
final class AddressModel
{
    /**
     * @return class-string<Address>
     */
    public static function class(): string
    {
        $model = ModelResolver::for('addresses.model', Address::class);

        return is_a($model, Address::class, true) ? $model : Address::class;
    }
}
