<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Actions;

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Events\AddressUpdated;
use RoundlyConsulting\Addresses\Events\PrimaryAddressChanged;
use RoundlyConsulting\Addresses\Exceptions\TrashedAddressException;
use Throwable;

/**
 * Overwrites an address with new data; a primary flag promotes it within its type group,
 * and no flag demotes it. The write and the promotion commit together, or not at all.
 */
final readonly class UpdateAddressAction
{
    public function __construct(
        private PromoteAddressAction $promote,
    ) {}

    /**
     * @throws TrashedAddressException when a deleted address is asked to become primary
     */
    public function execute(Address $address, AddressData $data): Address
    {
        $attributesBefore = $address->getAttributes();
        $originalBefore = $address->getRawOriginal();

        // Each attempt (a deadlock retries the transaction) and every failure starts from the
        // model as it was handed in: a write the rolled-back attempt synced would otherwise
        // look clean in memory and never reach the database.
        $restore = static fn () => $address->setRawAttributes($originalBefore, true)->setRawAttributes($attributesBefore);

        try {
            $promoted = $address->getConnection()->transaction(function () use ($address, $data, $restore): bool {
                $restore();

                return $this->write($address, $data);
            }, 3);
        } catch (Throwable $exception) {
            $restore();

            throw $exception;
        }

        AddressUpdated::dispatch($address);

        if ($promoted) {
            PrimaryAddressChanged::dispatch($address);
        }

        return $address->refresh();
    }

    /**
     * @return bool whether the address became primary
     */
    private function write(Address $address, AddressData $data): bool
    {
        $attributes = $data->toAttributes();
        unset($attributes['is_primary']);

        // Leaving the primary flag untouched while it stays in its group means a re-save of
        // the primary is no promotion; moving to another type group (or dropping the flag)
        // demotes first, so the new group never holds two.
        if (! $data->isPrimary || $address->type !== $data->type) {
            $attributes['is_primary'] = false;
        }

        $address->update($attributes);

        return $data->isPrimary && $this->promote->execute($address);
    }
}
