<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Exceptions\AddressesException;
use RoundlyConsulting\Addresses\Exceptions\AddressOwnershipException;
use RoundlyConsulting\Addresses\Exceptions\IncompleteAddressException;
use RoundlyConsulting\Addresses\Exceptions\InvalidAddressTypeException;
use RoundlyConsulting\Addresses\Exceptions\InvalidCountryException;

it('builds each typed exception under the base hierarchy', function () {
    $exceptions = [
        InvalidAddressTypeException::for('nope'),
        InvalidCountryException::for('XX1'),
        IncompleteAddressException::missing(['city', 'street']),
        AddressOwnershipException::make(),
    ];

    foreach ($exceptions as $exception) {
        expect($exception)->toBeInstanceOf(AddressesException::class)
            ->and($exception->getMessage())->not->toBe('');
    }
});

it('lists the missing fields in the incomplete message', function () {
    expect(IncompleteAddressException::missing(['city'])->getMessage())
        ->toContain('city');
});
