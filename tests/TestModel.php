<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Tests;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Addresses\Traits\HasAddresses;

class TestModel extends Model
{
    use HasAddresses;

    public $table = 'test_models';

    protected $guarded = [];

    public $timestamps = false;
}
