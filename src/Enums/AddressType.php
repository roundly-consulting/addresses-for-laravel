<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Enums;

/**
 * The kind of address being stored. Backed by the lowercase string persisted in
 * the `type` column.
 */
enum AddressType: string
{
    case Default = 'default';
    case Billing = 'billing';
    case Shipping = 'shipping';
    case Home = 'home';
    case Work = 'work';
    case Office = 'office';

    /**
     * A human-friendly label suitable for UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Default => 'Default',
            self::Billing => 'Billing',
            self::Shipping => 'Shipping',
            self::Home => 'Home',
            self::Work => 'Work',
            self::Office => 'Office',
        };
    }
}
