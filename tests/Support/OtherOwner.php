<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\Traits\HasAddresses;

/**
 * A second owner class on the same table as TestModel: its rows share keys with TestModel's,
 * so an ownership check that compares only the key (not the morph type) is caught.
 */
class OtherOwner extends Model
{
    use HasAddresses;

    public $table = 'test_models';

    protected $guarded = [];

    public $timestamps = false;
}
