<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RoundlyConsulting\Addresses\Address;

/**
 * Renders an Address for API responses with an explicit, stable shape.
 *
 * @mixin Address
 */
final class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Address $address */
        $address = $this->resource;

        return [
            'id' => $address->id,
            'type' => $address->type->value,
            'name' => $address->name,
            'city' => $address->city,
            'street' => $address->street,
            'postal_code' => $address->postal_code,
            'country_iso' => $address->country_iso,
            'country_name' => $address->country_name,
            'is_primary' => $address->is_primary,
            'formatted' => $address->formatted(),
            'meta' => $address->meta,
            'created_at' => $address->created_at,
            'updated_at' => $address->updated_at,
        ];
    }
}
