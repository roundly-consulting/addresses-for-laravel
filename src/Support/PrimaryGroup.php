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
}
