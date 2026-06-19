<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Events\AddressCreated;

final class CreateAddressAction
{
    public function execute(Model $addressable, AddressData $data): Address
    {
        /** @var Address $address */
        $address = $addressable->morphMany($this->model(), 'addressable')
            ->create($data->toAttributes());

        if ($data->isPrimary) {
            // Persist the primary invariant for the whole type group.
            $address->markAsPrimary();
        }

        AddressCreated::dispatch($address);

        return $address;
    }

    /**
     * @return class-string<Address>
     */
    private function model(): string
    {
        /** @var class-string<Address> $model */
        $model = config('addresses.model', Address::class);

        return $model;
    }
}
