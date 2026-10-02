<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Exceptions\AddressOwnershipException;
use RoundlyConsulting\Addresses\Exceptions\TrashedAddressException;
use RoundlyConsulting\Addresses\Support\PrimaryGroup;

/**
 * Makes an address the one primary of its owner + type group, demoting the rest — in one
 * transaction, under a lock on the group, so a failure half-way leaves the old primary in
 * place and two concurrent promotions cannot both win. The stored row is the source of
 * truth: its owner, type and trash state are read under the lock, never taken from the
 * caller's in-memory copy.
 *
 * It does not dispatch `PrimaryAddressChanged`: the calling action does, once its own
 * transaction has committed.
 *
 * @internal the primary invariant behind add, update and setPrimary; promote an address
 *           with `Addresses::for($owner)->setPrimary($address)`
 */
final readonly class PromoteAddressAction
{
    /**
     * @return bool whether the address became primary; false when it already was
     *
     * @throws AddressOwnershipException when `$owner` is given and the stored row is not theirs
     * @throws TrashedAddressException
     * @throws ModelNotFoundException<Address>
     */
    public function execute(Address $address, ?Model $owner = null): bool
    {
        try {
            return $this->attempt($address, $owner);
        } catch (UniqueConstraintViolationException) {
            // A racing promotion committed a primary our lock could not see (on Postgres: a
            // row inserted after this transaction's snapshot), and the one-primary index
            // refused ours. The winner is visible now, so a second pass demotes it.
            return $this->attempt($address, $owner);
        }
    }

    private function attempt(Address $address, ?Model $owner): bool
    {
        return $address->getConnection()->transaction(function () use ($address, $owner): bool {
            $stored = $this->stored($address);

            if ($owner instanceof Model && ! $stored->isOwnedBy($owner)) {
                throw AddressOwnershipException::make();
            }

            $locked = PrimaryGroup::lock($stored);

            $self = $locked->first(fn (Address $row): bool => $row->is($stored)) ?? throw $this->notFound($stored);

            if ($self->trashed()) {
                throw TrashedAddressException::cannotBePrimary();
            }

            // Deleted primaries are demoted too: one superseded here comes back plain on restore.
            PrimaryGroup::of($stored)
                ->whereKeyNot($stored->getKey())
                ->where('is_primary', true)
                ->update(['is_primary' => false]);

            if ($self->is_primary) {
                return false;
            }

            $stored->newModelQuery()->whereKey($stored->getKey())->update(['is_primary' => true]);

            $address->forceFill(['is_primary' => true])->syncOriginalAttribute('is_primary');

            return true;
        }, 3);
    }

    /**
     * The address as stored, trashed or not — the caller's copy may be stale or forged.
     */
    private function stored(Address $address): Address
    {
        return $address->newModelQuery()->whereKey($address->getKey())->first()
            ?? throw $this->notFound($address);
    }

    /**
     * @return ModelNotFoundException<Address>
     */
    private function notFound(Address $address): ModelNotFoundException
    {
        $key = $address->getKey();

        /** @var ModelNotFoundException<Address> $exception */
        $exception = new ModelNotFoundException;

        return $exception->setModel($address::class, is_int($key) || is_string($key) ? [$key] : []);
    }
}
