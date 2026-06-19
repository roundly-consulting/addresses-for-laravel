<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Facades;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\AddressManager;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\PendingAddress;

/**
 * @method static PendingAddress for(Model $addressable)
 * @method static Address|null primaryFor(Model $addressable, ?AddressType $type = null)
 * @method static Collection<int, Address> ofType(Model $addressable, AddressType $type)
 *
 * @see AddressManager
 */
final class Addresses extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AddressManager::class;
    }
}
