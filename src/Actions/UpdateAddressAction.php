<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Actions;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Events\AddressUpdated;
use RoundlyConsulting\Addresses\Events\PrimaryAddressChanged;
use RoundlyConsulting\Addresses\Exceptions\AddressOwnershipException;
use RoundlyConsulting\Addresses\Exceptions\TrashedAddressException;
use Throwable;

/**
 * Overwrites an address with new data; a primary flag promotes it within its type group,
 * and no flag demotes it. The write and the promotion commit together, or not at all.
 *
 * Given an owner, the address must be theirs before and after the write: the stored row is
 * read under a lock and compared by morph type and key, and the caller's copy must not move
 * it to another owner. Without one, any address is updated (`Addresses::update()`).
 */
final readonly class UpdateAddressAction
{
    public function __construct(
        private PromoteAddressAction $promote,
    ) {}

    /**
     * @throws TrashedAddressException when a deleted address is asked to become primary
     * @throws AddressOwnershipException when `$owner` is given and the address is not theirs
     */
    public function execute(Address $address, AddressData $data, ?Model $owner = null): Address
    {
        $attributesBefore = $address->getAttributes();
        $originalBefore = $address->getRawOriginal();

        // Each attempt (a deadlock retries the transaction) and every failure starts from the
        // model as it was handed in: a write the rolled-back attempt synced would otherwise
        // look clean in memory and never reach the database.
        $restore = static fn () => $address->setRawAttributes($originalBefore, true)->setRawAttributes($attributesBefore);

        try {
            $promoted = $address->getConnection()->transaction(function () use ($address, $data, $owner, $restore): bool {
                $restore();

                return $this->write($address, $data, $owner);
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
    private function write(Address $address, AddressData $data, ?Model $owner): bool
    {
        $stored = $this->rebase($address);

        if ($owner instanceof Model && (! $address->isOwnedBy($owner) || ($stored instanceof Address && ! $stored->isOwnedBy($owner)))) {
            throw AddressOwnershipException::make();
        }

        $attributes = $data->toAttributes();
        unset($attributes['is_primary']);

        // Leaving the primary flag untouched while it stays in its group means a re-save of
        // the primary is no promotion; moving to another type group (or dropping the flag)
        // demotes first, so the new group never holds two.
        if (! $data->isPrimary || $address->typeKey() !== $data->typeKey()) {
            $attributes['is_primary'] = false;
        }

        $address->update($attributes);

        return $data->isPrimary && $this->promote->execute($address, $owner);
    }

    /**
     * Re-base the caller's copy on the stored row, read under a lock, keeping the caller's
     * own unsaved changes on top. A stale copy would otherwise be compared against its old
     * values: a DTO value it already holds is not dirty and never reaches the database (a
     * primary flag set elsewhere is never cleared), and the type-group check would read a
     * type the row no longer has. An address that is not stored is left as it is.
     *
     * @return Address|null the stored row, read under the lock; null when there is none
     */
    private function rebase(Address $address): ?Address
    {
        $stored = $address->newModelQuery()->whereKey($address->getKey())->lockForUpdate()->first();

        if (! $stored instanceof Address) {
            return null;
        }

        $unsaved = $address->getDirty();

        $address->setRawAttributes($stored->getAttributes(), true)
            ->setRawAttributes([...$stored->getAttributes(), ...$unsaved]);

        return $stored;
    }
}
