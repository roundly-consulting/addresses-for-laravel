<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RoundlyConsulting\Addresses\Database\Factories\AddressFactory;

/**
 * @property int $id
 * @property string $addressable_type
 * @property int $addressable_id
 * @property bool $is_primary
 * @property string $type
 * @property string|null $name
 * @property string|null $city
 * @property string|null $street
 * @property string|null $postal_code
 * @property string|null $country_iso
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

    use SoftDeletes;

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
        ];
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
     * Mark this address as the primary one for its addressable + type, demoting
     * any siblings. Passing false simply demotes every address in the group.
     */
    public function markAsPrimary(bool $isPrimary = true): void
    {
        $this->newModelQuery()
            ->when($isPrimary, fn ($query) => $query->where('id', '!=', $this->id))
            ->where('addressable_type', $this->addressable_type)
            ->where('addressable_id', $this->addressable_id)
            ->where('type', $this->type)
            ->update(['is_primary' => false]);

        if ($isPrimary && ! $this->is_primary) {
            $this->update(['is_primary' => true]);
        }
    }
}
