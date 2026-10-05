<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Addresses\Contracts\CountryResolver;
use RoundlyConsulting\Addresses\Database\Factories\AddressFactory;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Events\AddressDeleted;
use RoundlyConsulting\Addresses\Events\PrimaryAddressChanged;
use RoundlyConsulting\Addresses\Support\PrimaryGroup;
use RoundlyConsulting\PackageToolkit\Support\Config;
use RoundlyConsulting\PackageToolkit\Support\RawExpression;
use Throwable;

/**
 * @property int $id
 * @property string $addressable_type
 * @property int $addressable_id
 * @property bool $is_primary
 * @property AddressType $type
 * @property string|null $name
 * @property string|null $city
 * @property string|null $street
 * @property string|null $postal_code
 * @property string|null $country_iso
 * @property string|null $country_name
 * @property Collection<array-key, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * Kept non-final so host applications can extend it and swap the bound model
 * through config('addresses.model').
 */
class Address extends Model
{
    /** @use HasFactory<AddressFactory> */
    use HasFactory;

    use SoftDeletes {
        restore as private restoreTrashed;
    }

    /** @var list<string> */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'collection',
            'is_primary' => 'bool',
            'type' => AddressType::class,
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Address $address): void {
            AddressDeleted::dispatch($address);
        });

        // A model event, like `deleted` above: a quiet restore stays quiet, and one that
        // rolls back never announces itself (the event waits for the commit).
        static::restored(function (Address $address): void {
            if ($address->is_primary) {
                PrimaryAddressChanged::dispatch($address);
            }
        });
    }

    /**
     * Restore a soft-deleted address. Deleting the primary keeps its flag, so a restore undoes
     * the delete — while the owner + type group has no other live primary. When one holds the
     * slot by now, the address comes back as a plain one: a promotion already demoted it, or
     * a primary was written past the promotion (a direct write, a factory) and this restore
     * yields to it. The flag is read from the stored row under a lock on the group, so it
     * serialises with promotions; on PostgreSQL and SQLite a racing primary that lock could
     * not see is refused by the one-primary index, and the restore retries against it. A
     * restore that brings the address back as the primary dispatches PrimaryAddressChanged.
     */
    public function restore(): bool
    {
        $attributes = $this->getAttributes();
        $original = $this->getRawOriginal();

        // Each attempt, a cancelled restore and a failed one all start from (or end at) the
        // model as it was handed in.
        $reset = fn (): static => $this->setRawAttributes($original, true)->setRawAttributes($attributes);

        try {
            try {
                $restored = $this->restoreOnce($reset);
            } catch (UniqueConstraintViolationException) {
                $restored = $this->restoreOnce($reset);
            }
        } catch (Throwable $exception) {
            $reset();

            throw $exception;
        }

        if (! $restored) {
            $reset();
        }

        return $restored;
    }

    /**
     * @param  Closure(): static  $reset
     */
    private function restoreOnce(Closure $reset): bool
    {
        return $this->getConnection()->transaction(function () use ($reset): bool {
            $reset();

            $stored = $this->newModelQuery()->whereKey($this->getKey())->first();

            if ($stored instanceof self) {
                $locked = null;

                if ($stored->trashed() && $stored->is_primary) {
                    [$stored, $locked] = PrimaryGroup::lockWith($stored);
                }

                $self = $locked?->first(fn (Address $row): bool => $row->is($stored)) ?? $stored;
                $taken = $locked?->contains(fn (Address $row): bool => ! $row->is($stored) && $row->is_primary && ! $row->trashed()) ?? false;

                // The stored flag becomes the original, whatever the caller's copy says, so the
                // restore's own save writes a yield — and a cancelled restore writes nothing.
                $this->forceFill(['is_primary' => $self->is_primary])
                    ->syncOriginalAttribute('is_primary')
                    ->forceFill(['is_primary' => $self->is_primary && ! $taken]);
            }

            return (bool) $this->restoreTrashed();
        }, 3);
    }

    protected static function newFactory(): AddressFactory
    {
        return AddressFactory::new();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<Address>  $query
     */
    public function scopePrimary(Builder $query): void
    {
        $query->where('is_primary', true);
    }

    /**
     * @param  Builder<Address>  $query
     */
    public function scopeOfType(Builder $query, AddressType $type): void
    {
        $query->where('type', $type->value);
    }

    /**
     * Addresses in a country, matched the way codes were stored: normalised codes are
     * upper-case, so the argument is too; verbatim codes (`normalise_country` off) are
     * compared case-insensitively. Alpha-2 and alpha-3 are not cross-mapped — `SK` never
     * matches a row stored as `SVK`.
     *
     * @param  Builder<Address>  $query
     */
    public function scopeInCountry(Builder $query, string $iso): void
    {
        $iso = strtoupper(trim($iso));

        if (Config::boolean('addresses.normalise_country', true)) {
            $query->where('country_iso', $iso);

            return;
        }

        $column = $query->getQuery()->getGrammar()->wrap($query->qualifyColumn('country_iso'));

        $query->where(new RawExpression("upper({$column})"), $iso);
    }

    /**
     * The resolved country name, deferring to a bound CountryResolver if one
     * exists; otherwise null.
     */
    public function countryName(): ?string
    {
        if ($this->country_iso === null) {
            return null;
        }

        if (! app()->bound(CountryResolver::class)) {
            return null;
        }

        return app(CountryResolver::class)->name($this->country_iso);
    }

    public function getCountryNameAttribute(): ?string
    {
        return $this->countryName();
    }

    /**
     * A single-line label built from the populated address lines, skipping any
     * empty parts.
     */
    public function formatted(string $separator = ', '): string
    {
        $postalAndCity = trim(implode(' ', array_filter([
            $this->postal_code,
            $this->city,
        ], fn (?string $part): bool => $part !== null && trim($part) !== '')));

        $parts = array_filter([
            $this->name,
            $this->street,
            $postalAndCity === '' ? null : $postalAndCity,
            $this->country_iso,
        ], fn (?string $part): bool => $part !== null && trim($part) !== '');

        return implode($separator, $parts);
    }

    /**
     * Whether this address belongs to the given owner.
     */
    public function isOwnedBy(Model $addressable): bool
    {
        return $this->addressable_type === $addressable->getMorphClass()
            && (string) $this->addressable_id === (string) $addressable->getKey();
    }
}
