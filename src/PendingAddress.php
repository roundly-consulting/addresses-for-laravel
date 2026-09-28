<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses;

use Illuminate\Support\Collection;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Exceptions\IncompleteAddressException;

/**
 * Fluent builder produced by `Addresses::for($owner)->new()` and `$model->newAddress()`.
 * It accumulates fields and adds the address through the owner's AddressBook on save().
 */
final class PendingAddress
{
    private ?string $city = null;

    private ?string $street = null;

    private ?string $postalCode = null;

    private ?string $country = null;

    private ?string $name = null;

    /** Null until `type()` is called: the address then gets `addresses.default_type`. */
    private ?AddressType $type = null;

    private bool $isPrimary = false;

    /** @var Collection<array-key, mixed>|null */
    private ?Collection $meta = null;

    /**
     * @internal build it with `Addresses::for($owner)->new()`
     */
    public function __construct(
        private readonly AddressBook $book,
    ) {}

    public function type(AddressType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function primary(bool $isPrimary = true): self
    {
        $this->isPrimary = $isPrimary;

        return $this;
    }

    public function in(string $city): self
    {
        $this->city = $city;

        return $this;
    }

    public function at(string $street): self
    {
        $this->street = $street;

        return $this;
    }

    public function postalCode(string $postalCode): self
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function country(string $country): self
    {
        $this->country = $country;

        return $this;
    }

    /**
     * @param  iterable<array-key, mixed>  $meta
     */
    public function meta(iterable $meta): self
    {
        $this->meta = Collection::make($meta);

        return $this;
    }

    public function save(): Address
    {
        return $this->book->add($this->data());
    }

    private function data(): AddressData
    {
        $missing = $this->missingFields();

        if ($missing !== []) {
            throw IncompleteAddressException::missing($missing);
        }

        return AddressData::make(
            city: (string) $this->city,
            street: (string) $this->street,
            postalCode: (string) $this->postalCode,
            countryIso: (string) $this->country,
            name: $this->name,
            type: $this->type,
            isPrimary: $this->isPrimary,
            meta: $this->meta,
        );
    }

    /**
     * @return list<string>
     */
    private function missingFields(): array
    {
        $missing = [];

        foreach (['city', 'street', 'postalCode', 'country'] as $field) {
            if ($this->{$field} === null || trim((string) $this->{$field}) === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }
}
