<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Tests\Support;

use RoundlyConsulting\Addresses\Tests\TestCase;

/**
 * The suite's base case with `addresses.model` already pointed at {@see CustomAddress}
 * BEFORE the providers boot.
 *
 * Boot order is the whole point: the providers hang observers and relationship wiring on
 * whatever `addresses.model` names at boot. A `config()->set()` inside the test body reads
 * back correctly but leaves every listener on the packaged Address — precisely the shape
 * that let media #28 ship, and precisely what the test this replaces
 * (`Feature/ModelSwapTest.php`, a runtime `set` in `beforeEach` plus an `instanceof`) could
 * never have caught.
 *
 * Note the `array_merge(parent::configBeforeBoot(), …)`: dropping it would silently discard
 * whatever the base case wires, the same decapitation an un-parented `defineEnvironment()`
 * override causes one level up. It is empty today — that is not a reason to omit it.
 *
 * @see TestCase
 */
abstract class SwappedAddressTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'addresses.model' => CustomAddress::class,
        ]);
    }
}
