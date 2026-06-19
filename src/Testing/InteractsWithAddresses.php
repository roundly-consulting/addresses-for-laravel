<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Enums\AddressType;

/**
 * Opt-in testing ergonomics for host applications. Use from a Pest/PHPUnit case:
 *
 *     uses(RoundlyConsulting\Addresses\Testing\InteractsWithAddresses::class);
 */
trait InteractsWithAddresses
{
    /**
     * Assert the model has an address matching the given column criteria.
     *
     * @param  array<string, mixed>  $criteria
     */
    public function assertHasAddress(Model $addressable, array $criteria = []): void
    {
        /** @var MorphMany<Address, Model> $relation */
        $relation = $addressable->addresses();

        $exists = $relation->where($criteria)->exists();

        Assert::assertTrue(
            $exists,
            sprintf('Failed asserting that the model has a matching address (%s).', json_encode($criteria)),
        );
    }

    /**
     * Assert the model's primary address of a type matches the expected type.
     */
    public function assertPrimaryAddress(Model $addressable, AddressType $type): void
    {
        /** @var MorphMany<Address, Model> $relation */
        $relation = $addressable->addresses();

        $exists = $relation
            ->where('type', $type->value)
            ->where('is_primary', true)
            ->exists();

        Assert::assertTrue(
            $exists,
            sprintf('Failed asserting that the model has a primary [%s] address.', $type->value),
        );
    }
}
