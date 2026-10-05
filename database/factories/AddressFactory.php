<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Support\AddressModel;

/**
 * @extends Factory<Address>
 */
final class AddressFactory extends Factory
{
    /** @var class-string<Address> */
    protected $model = Address::class;

    /**
     * Build the model the host configured, not the packaged one: a host that points
     * `addresses.model` at its own subclass gets that subclass, with its casts, events and
     * observers, out of the factory the package ships.
     *
     * @return class-string<Address>
     */
    public function modelName(): string
    {
        return AddressModel::class();
    }

    /** @return array<model-property<Address>, mixed> */
    public function definition(): array
    {
        return [
            // Plain by default: an owner holds one primary per type, so a batch of random flags
            // would collide. Ask for one with the primary() state.
            'is_primary' => false,
            'type' => AddressType::Default->value,
            'name' => $this->faker->streetName().' - '.$this->faker->randomElement(['Home', 'Office', 'Work']),
            'city' => $this->faker->city(),
            'street' => $this->faker->streetName(),
            'postal_code' => $this->faker->postcode(),
            'country_iso' => $this->faker->countryISOAlpha3(),
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (): array => ['is_primary' => true]);
    }

    public function ofType(AddressType $type): static
    {
        return $this->state(fn (): array => ['type' => $type->value]);
    }

    public function billing(): static
    {
        return $this->ofType(AddressType::Billing);
    }

    public function shipping(): static
    {
        return $this->ofType(AddressType::Shipping);
    }
}
