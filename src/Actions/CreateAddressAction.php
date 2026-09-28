<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Events\AddressCreated;
use RoundlyConsulting\Addresses\Events\PrimaryAddressChanged;
use RoundlyConsulting\Addresses\Support\AddressModel;

/**
 * Adds an address to an owner; a primary flag promotes it within its type group. The row
 * and its promotion commit together, or not at all.
 */
final readonly class CreateAddressAction
{
    public function __construct(
        private PromoteAddressAction $promote,
    ) {}

    public function execute(Model $addressable, AddressData $data): Address
    {
        $model = AddressModel::class();

        [$address, $promoted] = (new $model)->getConnection()->transaction(function () use ($addressable, $data): array {
            // Inserted as a plain address, then promoted: the promotion is what demotes the
            // siblings and tells us the address became primary.
            /** @var array<model-property<Address>, mixed> $attributes */
            $attributes = [...$data->toAttributes(), 'is_primary' => false];

            /** @var Address $address */
            $address = $addressable->morphMany(AddressModel::class(), 'addressable')->create($attributes);

            return [$address, $data->isPrimary && $this->promote->execute($address)];
        }, 3);

        AddressCreated::dispatch($address);

        if ($promoted) {
            PrimaryAddressChanged::dispatch($address);
        }

        return $address;
    }
}
