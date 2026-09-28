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
use RoundlyConsulting\Addresses\Support\AddressModel;

/**
 * Test double for the address manager, installed by `Addresses::fake()`. Nothing is
 * written: added addresses live in memory (unsaved) and are merged into the owner's
 * reads, and every add, update, delete and primary change — through the facade, an
 * injected manager, the address book or the HasAddresses trait — is recorded for the
 * assertions below. Ownership is still enforced.
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

    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function update(Address $address, AddressData $data): Address
    {
        $this->updated[] = ['address' => $address, 'data' => $data];

        return (clone $address)->forceFill($data->toAttributes());
    }

    public function delete(Address $address): void
    {
        $this->deleted[] = $address;
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
            $this->demoteSiblings($address);
        }

        $this->added[] = ['owner' => $this->identify($addressable), 'data' => $data, 'address' => $address];

        return $address;
    }

    public function setPrimaryFor(Model $addressable, Address $address): Address
    {
        if (! $address->isOwnedBy($addressable)) {
            throw AddressOwnershipException::make();
        }

        $this->demoteSiblings($address);
        $address->forceFill(['is_primary' => true]);

        $this->promoted[] = ['owner' => $this->identify($addressable), 'address' => $address];

        return $address;
    }

    /**
     * The latest primary added through the fake wins; otherwise the stored one.
     */
    public function primaryFor(Model $addressable, ?AddressType $type = null): ?Address
    {
        foreach (array_reverse($this->memoryFor($addressable)) as $address) {
            if ($address->is_primary && ($type === null || $address->type === $type)) {
                return $address;
            }
        }

        return parent::primaryFor($addressable, $type);
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
     * The owner's stored addresses followed by those added through the fake.
     *
     * @return Collection<int, Address>
     */
    public function allFor(Model $addressable): Collection
    {
        return parent::allFor($addressable)->concat($this->memoryFor($addressable))->values();
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

    private function demoteSiblings(Address $address): void
    {
        foreach ($this->added as $added) {
            $sibling = $added['address'];

            if ($sibling !== $address
                && $sibling->addressable_type === $address->addressable_type
                && (string) $sibling->addressable_id === (string) $address->addressable_id
                && $sibling->type === $address->type) {
                $sibling->forceFill(['is_primary' => false]);
            }
        }
    }

    /**
     * @return list<Address>
     */
    private function memoryFor(Model $addressable): array
    {
        $owner = $this->identify($addressable);

        return array_column(
            array_filter($this->added, fn (array $added): bool => $added['owner'] === $owner),
            'address',
        );
    }

    private function identify(Model $addressable): string
    {
        return $addressable->getMorphClass().':'.((string) $addressable->getKey());
    }
}
