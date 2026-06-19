<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Tests\Support;

use RoundlyConsulting\Addresses\Address;

/**
 * Host-style subclass used to prove the model is swappable via
 * config('addresses.model').
 */
final class CustomAddress extends Address
{
    protected $table = 'addresses';
}
