<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\AddressManager;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\Tests\Support\CustomAddress;
use RoundlyConsulting\Addresses\Tests\Support\SwappedAddressTestCase;
use RoundlyConsulting\Addresses\Tests\TestModel;

/**
 * The model-swap proof (S) for the `addresses.model` seam, driven through the REAL flows.
 *
 * This replaces `Feature/ModelSwapTest.php`, which set the config at runtime in a
 * `beforeEach` and asserted `instanceof`. Both halves were too weak for the bugs this class
 * of test exists for:
 *
 *  - a runtime `config()->set()` leaves every observer the provider hung at boot on the
 *    packaged Address (media #28);
 *  - `instanceof` passes for a row created as the packaged class — which never fires the
 *    host's model events (permissions #31). Only the concrete class, plus a `created` event
 *    counted on the subclass itself, proves the row was really made as the host's model.
 *
 * The swap is applied before boot by {@see SwappedAddressTestCase}, which this directory is
 * bound to — Pest binds a test case per directory, not per file.
 */
it('honours a host address model through every write flow', function (): void {
    expect('addresses.model')->toHonourModelSwap(CustomAddress::class, function (): array {
        $entity = TestModel::create();

        // The trait, the facade's fluent builder, and a plain read back — the three public
        // ways a host reaches this seam.
        $viaTrait = $entity->addAddress(AddressData::make(
            city: 'A',
            street: 'B',
            postalCode: 'C',
            countryIso: 'SK',
            type: AddressType::Home,
        ));

        $viaFacade = Addresses::for($entity)->new()
            ->in('A')->at('B')->postalCode('C')->country('SK')->save();

        return [
            $viaTrait,
            $viaFacade,
            // The morph relation hydrates through the seam too, not just the writes.
            ...$entity->addresses()->get()->all(),
        ];
    });
});

/**
 * The manager's reads are a separate path from the trait's writes — the primary-address
 * invariant is enforced there, so a swap honoured on write but not on read would hand the
 * host a packaged model for exactly the query its own overrides exist to influence.
 */
it('reads through the swapped model in manager queries', function (): void {
    $entity = TestModel::create();

    $entity->addAddress(AddressData::make(
        city: 'A',
        street: 'B',
        postalCode: 'C',
        countryIso: 'SK',
        type: AddressType::Work,
        isPrimary: true,
    ));

    $primary = app(AddressManager::class)->for($entity)->primary(AddressType::Work);

    expect($primary)->toBeInstanceOf(CustomAddress::class)
        // The concrete class, not just `instanceof`: a row hydrated as the packaged class
        // would satisfy an `instanceof Address` check and still be the wrong model.
        ->and($primary::class)->toBe(CustomAddress::class)
        ->and(Address::class)->not->toBe(CustomAddress::class);
});

// The structural half of the seam — Address is non-final, and `addresses.model` really
// defaults to the packaged model — is pinned once in tests/ArchTest.php by
// `ArchPresets::swappableModelsAreNotFinal()`. It deliberately does NOT live here: that
// preset asserts the config *default*, which this directory has swapped away.
