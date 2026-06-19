<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Enums\AddressType;

it('exposes the expected backing values', function () {
    expect(AddressType::Default->value)->toBe('default')
        ->and(AddressType::Billing->value)->toBe('billing')
        ->and(AddressType::Shipping->value)->toBe('shipping')
        ->and(AddressType::Home->value)->toBe('home')
        ->and(AddressType::Work->value)->toBe('work')
        ->and(AddressType::Office->value)->toBe('office');
});

it('builds from a backing value', function () {
    expect(AddressType::from('billing'))->toBe(AddressType::Billing);
    expect(AddressType::tryFrom('nope'))->toBeNull();
});

it('returns a human label for every case', function () {
    foreach (AddressType::cases() as $case) {
        expect($case->label())->toBeString()->not->toBe('');
    }

    expect(AddressType::Office->label())->toBe('Office');
});
