<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RoundlyConsulting\Addresses\Address;

/**
 * Dispatched once the surrounding database transaction commits (immediately outside one), so a
 * write that rolls back never announces itself.
 */
final class AddressCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Address $address,
    ) {}
}
