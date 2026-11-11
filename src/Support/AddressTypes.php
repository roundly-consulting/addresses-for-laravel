<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Support;

use Illuminate\Support\Str;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\InvalidAddressTypeException;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/**
 * The address types an application allows: `addresses.types`. It ships the six AddressType
 * values; an app lists its own (`registered`, `postal`, …) to store those. The list is the
 * whole allow-list: a built-in type left out of it is refused like any unknown one.
 *
 * A type is a lowercase key of letters, digits, `-` and `_`, at most 255 characters (the
 * `type` column). Input is trimmed and lower-cased before it is checked, so `' Billing '`
 * is `billing`. A built-in key always comes back as its AddressType case; a custom one as
 * the string.
 *
 * Entries take three forms, freely mixed:
 *
 *     'types' => [
 *         'billing',                                  // a type, labelled by translation or headline
 *         'postal' => 'Postal address',               // a type and its label
 *         'operating' => ['label' => 'Operating site'],
 *     ],
 *
 * A comma-separated string (`'default,billing,registered'`, e.g. from an env value) is a list
 * too. Not set (null, blank or an empty list) means the six AddressType values. Anything else
 * malformed throws InvalidConfigurationException naming `addresses.types`.
 */
final class AddressTypes
{
    private const string KEY = 'addresses.types';

    /**
     * The configured type keys, in config order.
     *
     * @return list<string>
     *
     * @throws InvalidConfigurationException
     */
    public static function keys(): array
    {
        return array_keys(self::registry());
    }

    /**
     * Whether the type is configured.
     *
     * @throws InvalidConfigurationException
     */
    public static function allows(AddressType|string $type): bool
    {
        return array_key_exists(self::key($type), self::registry());
    }

    /**
     * The configured type: its AddressType case when it is a built-in key, else the key.
     *
     * @throws InvalidAddressTypeException when the type is not configured
     * @throws InvalidConfigurationException
     */
    public static function resolve(AddressType|string $type): AddressType|string
    {
        $key = self::key($type);

        if (! self::allows($key)) {
            throw InvalidAddressTypeException::for($key);
        }

        return AddressType::tryFrom($key) ?? $key;
    }

    /**
     * The stored key of a type, normalised (trimmed, lower-case) but not checked.
     */
    public static function key(AddressType|string $type): string
    {
        return $type instanceof AddressType ? $type->value : strtolower(trim($type));
    }

    /**
     * A display label: the one configured for the type, else the `addresses::types.<key>`
     * translation (shipped for the six built-ins in every package language), else a
     * headline of the key (`head_office` → `Head Office`).
     *
     * @throws InvalidConfigurationException
     */
    public static function label(AddressType|string $type): string
    {
        $key = self::key($type);
        $configured = self::registry()[$key] ?? null;

        if ($configured !== null) {
            return $configured;
        }

        $line = 'addresses::types.'.$key;
        $translated = trans($line);

        if (is_string($translated) && $translated !== $line && $translated !== '') {
            return $translated;
        }

        return Str::headline($key);
    }

    /**
     * Type key => configured label (null when none is configured).
     *
     * @return array<string, string|null>
     *
     * @throws InvalidConfigurationException
     */
    private static function registry(): array
    {
        $types = config('addresses.types');

        if (is_string($types)) {
            $types = array_values(array_filter(array_map(trim(...), explode(',', $types)), static fn (string $type): bool => $type !== ''));
        }

        if ($types === null || $types === []) {
            $types = array_map(static fn (AddressType $type): string => $type->value, AddressType::cases());
        }

        if (! is_array($types)) {
            throw InvalidConfigurationException::notAList(self::KEY, $types);
        }

        $registry = [];

        foreach ($types as $index => $entry) {
            [$type, $label] = is_int($index) ? [$entry, null] : [$index, self::configuredLabel($index, $entry)];

            if (! is_string($type)) {
                throw InvalidConfigurationException::notAStringItem(self::KEY, $type);
            }

            $key = self::key($type);

            if (preg_match('/^[a-z0-9_-]{1,255}$/', $key) !== 1) {
                throw InvalidConfigurationException::notAValidListItem(self::KEY, $type);
            }

            $registry[$key] = $label;
        }

        return $registry;
    }

    /**
     * The label a `type => label` or `type => ['label' => …]` entry configures; blank or
     * null is none.
     *
     * @throws InvalidConfigurationException
     */
    private static function configuredLabel(string $type, mixed $entry): ?string
    {
        if (is_array($entry)) {
            if (array_diff(array_keys($entry), ['label']) !== []) {
                throw InvalidConfigurationException::notAValidListItem(self::KEY.'.'.$type, implode(', ', array_map(strval(...), array_keys($entry))));
            }

            $entry = $entry['label'] ?? null;
        }

        if ($entry === null) {
            return null;
        }

        if (! is_string($entry)) {
            throw InvalidConfigurationException::notAString(self::KEY.'.'.$type, $entry);
        }

        return trim($entry) === '' ? null : $entry;
    }
}
