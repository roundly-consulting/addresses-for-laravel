<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\AddressBook;
use RoundlyConsulting\Addresses\AddressManager;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Testing\AddressesFake;

/**
 * @method static AddressBook for(Model $addressable)
 * @method static Address update(Address $address, AddressData $data)
 * @method static void delete(Address $address)
 * @method static string countryName(string $iso)
 *
 * @see AddressManager
 */
final class Addresses extends Facade
{
    /**
     * Swap the manager for a recording fake that writes nothing.
     */
    public static function fake(): AddressesFake
    {
        $fake = new AddressesFake(self::getFacadeApplication() ?? app());

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return AddressManager::class;
    }
}
