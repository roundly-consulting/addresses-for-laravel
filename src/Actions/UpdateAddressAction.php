<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Actions;

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Events\AddressUpdated;

final class UpdateAddressAction
{
    public function execute(Address $address, AddressData $data): Address
    {
        $address->update($data->toAttributes());

        if ($data->isPrimary) {
            $address->markAsPrimary();
        }

        AddressUpdated::dispatch($address);

        return $address->refresh();
    }
}
