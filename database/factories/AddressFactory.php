<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RoundlyConsulting\Addresses\Address;

/**
 * @extends Factory<Address>
 */
final class AddressFactory extends Factory
{
    /** @var class-string<Address> */
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'is_primary' => $this->faker->boolean(),
            'type' => 'default',
            'name' => $this->faker->streetName().' - '.$this->faker->randomElement(['Home', 'Office', 'Work']),
            'city' => $this->faker->city(),
            'street' => $this->faker->streetName(),
            'postal_code' => $this->faker->postcode(),
            'country_iso' => $this->faker->countryISOAlpha3(),
        ];
    }
}
