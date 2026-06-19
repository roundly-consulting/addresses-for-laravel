<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Address;

return [

    /*
    |--------------------------------------------------------------------------
    | Address Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to store addresses. Override this with your own
    | class (extending the package model) if you need custom behaviour while
    | still using the HasAddresses trait.
    |
    */

    'model' => Address::class,

];
