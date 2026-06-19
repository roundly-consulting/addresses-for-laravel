<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses\DataTransferObjects;

final readonly class Coordinates
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {}
}
