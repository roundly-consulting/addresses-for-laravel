<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use RoundlyConsulting\Addresses\Address;

final class AddressDeleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Address $address,
    ) {}
}
