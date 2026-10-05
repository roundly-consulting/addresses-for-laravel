<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Testing;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Assert as PHPUnit;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\AddressManager;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\AddressOwnershipException;
use RoundlyConsulting\Addresses\Exceptions\TrashedAddressException;
use RoundlyConsulting\Addresses\Support\AddressModel;

/**
 * Test double for the address manager, installed by `Addresses::fake()`. Nothing is
 * written: added addresses live in memory (unsaved), and the owner's reads answer from the
 * stored rows with every add, update, delete and primary change made through the fake
 * applied on top — so `primary()` reports what the real manager would after the same calls.
 * Each of those calls — through the facade, an injected manager, the address book or the
 * HasAddresses trait — is also recorded for the assertions below. Ownership is still
 * enforced, and so is the refusal to promote a deleted address.
 */
final class AddressesFake extends AddressManager
{
    /** @var list<array{owner: string, data: AddressData, address: Address}> */
    private array $added = [];

    /** @var list<array{address: Address, data: AddressData}> */
    private array $updated = [];

    /** @var list<Address> */
    private array $deleted = [];

    /** @var list<array{owner: string, address: Address}> */
    private array $promoted = [];

    /**
     * Stored addresses as the fake's updates left them, by key.
     *
     * @var array<array-key, Address>
     */
    private array $replaced = [];

    /**
     * The primary of each owner + type group the fake changed; null once the fake demoted
     * or deleted it. Groups the fake never touched answer from the stored flags.
     *
     * @var array<string, Address|null>
     */
    private array $primaries = [];

    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function update(Address $address, AddressData $data): Address
    {
        if ($data->isPrimary) {
            $this->refuseTrashed($address);
        }

        $this->updated[] = ['address' => $address, 'data' => $data];

        $this->release($address);

        // The passed instance itself, as the real manager updates and returns it: an address
        // added through the fake is already this object in the fake's list, so a second
        // update or a delete through the same variable reaches what the reads answer with.
        $address->forceFill($data->toAttributes());

        if ($address->exists) {
            $this->replaced[$this->key($address)] = $address;
        }

        if ($data->isPrimary) {
            $this->primaries[$this->group($address)] = $address;
        }

        return $address;
    }

    public function delete(Address $address): void
    {
        $this->deleted[] = $address;

        $this->release($address);
    }

    public function addFor(Model $addressable, AddressData $data): Address
    {
        $model = AddressModel::class();

        $address = (new $model)->forceFill([
            ...$data->toAttributes(),
            'addressable_type' => $addressable->getMorphClass(),
            'addressable_id' => $addressable->getKey(),
        ]);

        if ($data->isPrimary) {
            $this->primaries[$this->group($address)] = $address;
        }

        $this->added[] = ['owner' => $this->identify($addressable), 'data' => $data, 'address' => $address];

        $this->flag($this->view($addressable));

        return $address;
    }

    public function setPrimaryFor(Model $addressable, Address $address): Address
    {
        if (! $address->isOwnedBy($addressable)) {
            throw AddressOwnershipException::make();
        }

        $this->refuseTrashed($address);

        $this->primaries[$this->group($address)] = $address;
        $address->forceFill(['is_primary' => true]);

        $this->promoted[] = ['owner' => $this->identify($addressable), 'address' => $address];

        $this->flag($this->view($addressable));

        return $address;
    }

    /**
     * The first primary in the fake's view — the oldest stored one, then those added
     * through the fake — as the real manager answers it.
     */
    public function primaryFor(Model $addressable, ?AddressType $type = null): ?Address
    {
        return $this->allFor($addressable)->first(
            fn (Address $address): bool => $address->is_primary && ($type === null || $address->type === $type),
        );
    }

    /**
     * @return Collection<int, Address>
     */
    public function ofTypeFor(Model $addressable, AddressType $type): Collection
    {
        return $this->allFor($addressable)
            ->filter(fn (Address $address): bool => $address->type === $type)
            ->values();
    }

    /**
     * The owner's stored addresses followed by those added through the fake, with the
     * fake's updates, deletes and primary changes applied.
     *
     * @return Collection<int, Address>
     */
    public function allFor(Model $addressable): Collection
    {
        return $this->flag($this->view($addressable));
    }

    /**
     * @param  (Closure(AddressData): bool)|null  $where
     */
    public function assertAdded(?Model $to = null, ?Closure $where = null): void
    {
        $owner = $to === null ? null : $this->identify($to);

        $matches = array_filter(
            $this->added,
            fn (array $added): bool => ($owner === null || $added['owner'] === $owner)
                && ($where === null || $where($added['data']) === true),
        );

        PHPUnit::assertNotSame([], $matches, 'No matching address was added'.($owner === null ? '' : " to [{$owner}]").'.');
    }

    public function assertNothingAdded(): void
    {
        PHPUnit::assertSame([], $this->added, 'Unexpected addresses were added.');
    }

    /**
     * @param  (Closure(AddressData): bool)|null  $where
     */
    public function assertUpdated(?Address $address = null, ?Closure $where = null): void
    {
        $matches = array_filter(
            $this->updated,
            fn (array $updated): bool => ($address === null || $this->same($updated['address'], $address))
                && ($where === null || $where($updated['data']) === true),
        );

        PHPUnit::assertNotSame([], $matches, 'No matching address was updated.');
    }

    public function assertNothingUpdated(): void
    {
        PHPUnit::assertSame([], $this->updated, 'Unexpected addresses were updated.');
    }

    public function assertDeleted(?Address $address = null): void
    {
        $matches = array_filter(
            $this->deleted,
            fn (Address $deleted): bool => $address === null || $this->same($deleted, $address),
        );

        PHPUnit::assertNotSame([], $matches, 'No matching address was deleted.');
    }

    public function assertNothingDeleted(): void
    {
        PHPUnit::assertSame([], $this->deleted, 'Unexpected addresses were deleted.');
    }

    public function assertPrimarySet(?Address $address = null): void
    {
        $matches = array_filter(
            $this->promoted,
            fn (array $promoted): bool => $address === null || $this->same($promoted['address'], $address),
        );

        PHPUnit::assertNotSame([], $matches, 'No matching address was set as primary.');
    }

    public function assertNothingPrimarySet(): void
    {
        PHPUnit::assertSame([], $this->promoted, 'Unexpected primary addresses were set.');
    }

    /**
     * In-memory addresses carry no key, so they match by identity; stored ones by key.
     */
    private function same(Address $a, Address $b): bool
    {
        return $a === $b || ($a->exists && $b->exists && $a->is($b));
    }

    /**
     * @return Collection<int, Address>
     */
    private function view(Model $addressable): Collection
    {
        $owner = $this->identify($addressable);

        $stored = parent::allFor($addressable)
            ->map(fn (Address $address): Address => $this->replaced[$this->key($address)] ?? $address);

        $memory = array_column(
            array_filter($this->added, fn (array $added): bool => $added['owner'] === $owner),
            'address',
        );

        return $stored->concat($memory)
            ->reject(fn (Address $address): bool => $this->wasDeleted($address))
            ->values();
    }

    /**
     * Set each address's primary flag from the groups the fake changed.
     *
     * @param  Collection<int, Address>  $addresses
     * @return Collection<int, Address>
     */
    private function flag(Collection $addresses): Collection
    {
        foreach ($addresses as $address) {
            $group = $this->group($address);

            if (array_key_exists($group, $this->primaries)) {
                $primary = $this->primaries[$group];

                $address->forceFill(['is_primary' => $primary instanceof Address && $this->same($address, $primary)]);
            }
        }

        return $addresses;
    }

    /**
     * Drop the address from any group it is the fake's primary of.
     */
    private function release(Address $address): void
    {
        foreach ($this->primaries as $group => $primary) {
            if ($primary instanceof Address && $this->same($primary, $address)) {
                $this->primaries[$group] = null;
            }
        }
    }

    /**
     * @throws TrashedAddressException
     */
    private function refuseTrashed(Address $address): void
    {
        if ($address->trashed() || $this->wasDeleted($address)) {
            throw TrashedAddressException::cannotBePrimary();
        }
    }

    private function wasDeleted(Address $address): bool
    {
        foreach ($this->deleted as $deleted) {
            if ($this->same($deleted, $address)) {
                return true;
            }
        }

        return false;
    }

    private function group(Address $address): string
    {
        return $address->addressable_type.':'.((string) $address->addressable_id).'|'.$address->type->value;
    }

    private function key(Address $address): string
    {
        $key = $address->getKey();

        return is_scalar($key) ? (string) $key : '';
    }

    private function identify(Model $addressable): string
    {
        return $addressable->getMorphClass().':'.((string) $addressable->getKey());
    }
}
