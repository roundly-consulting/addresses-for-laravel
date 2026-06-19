<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\DataTransferObjects\Coordinates;

it('holds latitude and longitude', function () {
    $coordinates = new Coordinates(48.1486, 17.1077);

    expect($coordinates)
        ->latitude->toBe(48.1486)
        ->longitude->toBe(17.1077);
});
