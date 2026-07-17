<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Tests\Support;

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Testing\Fixtures\Concerns\CountsCreations;

/**
 * Host-style subclass used to prove the model is swappable via `addresses.model`.
 *
 * `CountsCreations` is what makes the proof independent of `instanceof`: it counts rows
 * created *as this exact class*, so a flow that resolved the seam correctly for the return
 * value but created the row as the packaged Address (permissions #31 — `static::query()`
 * inside the packaged model) is visible, where an `instanceof` check would pass.
 */
final class CustomAddress extends Address
{
    use CountsCreations;

    protected $table = 'addresses';
}
