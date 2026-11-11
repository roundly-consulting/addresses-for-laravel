<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Enums;

use RoundlyConsulting\Addresses\Support\AddressTypes;

/**
 * The built-in kinds of address. Backed by the lowercase string persisted in the `type`
 * column. `addresses.types` lists the types an application allows — these six by default —
 * and may add its own, which are stored and read as plain strings.
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
     * A human-friendly label suitable for UI: the one configured in `addresses.types`, else
     * the `addresses::types.*` translation.
     */
    public function label(): string
    {
        return AddressTypes::label($this);
    }
}
