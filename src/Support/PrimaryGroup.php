<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use RoundlyConsulting\Addresses\Address;

/**
 * The addresses that share one primary: one owner, one stored type — trashed rows included,
 * so a promotion demotes a deleted primary too and it can only come back as a plain address.
 * Always built from the stored row, never a caller's in-memory copy.
 *
 * @internal the one-primary invariant behind promotion and restore
 */
final class PrimaryGroup
{
    /**
     * @return Builder<Address>
     */
    public static function of(Address $stored): Builder
    {
        return $stored->newModelQuery()
            ->where('addressable_type', $stored->addressable_type)
            ->where('addressable_id', $stored->addressable_id)
            ->where('type', $stored->getRawOriginal('type'));
    }

    /**
     * Lock the whole group in key order — so concurrent promotions and restores queue up
     * behind one another instead of deadlocking — and read each row's flag and trash state.
     *
     * @return Collection<int, Address>
     */
    public static function lock(Address $stored): Collection
    {
        return self::of($stored)
            ->orderBy($stored->getKeyName())
            ->lockForUpdate()
            ->get([$stored->getKeyName(), 'is_primary', $stored->getDeletedAtColumn()]);
    }

    /**
     * Lock the group of a row read without a lock, and return the row as it stands with the
     * group it is in. The unlocked read keeps every caller locking in key order, but a write
     * committed between that read and the lock can move the row to another group (a new type
     * or owner): then the group just locked no longer holds it. The row is read again under
     * its own lock, which pins its group, and that group is locked instead. A row that is
     * gone by then comes back with the group it left, so the caller finds it missing there.
     *
     * @return array{0: Address, 1: Collection<int, Address>} the row as current, and its locked group
     */
    public static function lockWith(Address $stored): array
    {
        $group = self::lock($stored);

        if ($group->contains(fn (Address $row): bool => $row->is($stored))) {
            return [$stored, $group];
        }

        $moved = $stored->newModelQuery()->whereKey($stored->getKey())->lockForUpdate()->first();

        return $moved instanceof Address ? [$moved, self::lock($moved)] : [$stored, $group];
    }
}
