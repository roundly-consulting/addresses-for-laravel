<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\InvalidAddressTypeException;
use RoundlyConsulting\Addresses\Support\AddressTypes;

/**
 * The `type` column: an AddressType case for the six built-in keys, the stored string for a
 * custom type from `addresses.types`. Laravel's enum cast would throw on a custom key, so
 * this one replaces it, and serialises to the key exactly as the enum cast did.
 *
 * Writes are checked against `addresses.types`. Assigning the type a row already has is
 * not: a row whose type was later dropped from the config can still be saved.
 *
 * Object caching is off: the cast value is computed per read and never written back, so
 * reading `$address->type` can never rewrite the column.
 *
 * @implements CastsAttributes<AddressType|string|null, mixed>
 */
final class AddressTypeCast implements CastsAttributes, SerializesCastableAttributes
{
    public bool $withoutObjectCaching = true;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): AddressType|string|null
    {
        if (! is_string($value)) {
            return null;
        }

        return AddressType::tryFrom($value) ?? $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidAddressTypeException when the type is not configured
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! $value instanceof AddressType && ! is_string($value)) {
            throw InvalidAddressTypeException::for(get_debug_type($value));
        }

        $type = AddressTypes::key($value);

        if ($type === ($attributes[$key] ?? null)) {
            return $type;
        }

        return AddressTypes::key(AddressTypes::resolve($type));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value instanceof AddressType ? $value->value : (is_string($value) ? $value : null);
    }
}
