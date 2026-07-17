<?php

declare(strict_types=1);

use RoundlyConsulting\Addresses\Address;
use RoundlyConsulting\Addresses\Exceptions\AddressesException;
use RoundlyConsulting\Testing\Arch\ArchPresets;

/**
 * Addresses shipped with no architecture test at all, so every preset here is a new guard
 * rather than a replacement.
 */
ArchPresets::strictTypes('RoundlyConsulting\Addresses');

/**
 * Two deliberate extension points are exempt: Address, which `addresses.model` invites a
 * host to subclass (pinned by the preset below instead), and AddressesException, the base
 * every addresses error extends so a host can catch them uniformly.
 */
ArchPresets::finalByDefault('RoundlyConsulting\Addresses')
    ->ignoring([Address::class, AddressesException::class]);

/**
 * The counter-weight, and the fleet's 7×-shipped fatal: `final` on a config-swappable
 * model is a PHP fatal the moment a host uses the seam the config documents. The preset
 * also pins that `addresses.model` really defaults to the packaged model, so the seam
 * cannot rot in the other direction either.
 */
ArchPresets::swappableModelsAreNotFinal([
    Address::class => 'addresses.model',
]);

/**
 * Addresses does no cryptography; the ban is a standing guard against a country-code hash
 * or an address fingerprint being hand-rolled here rather than in crypto-for-laravel.
 */
ArchPresets::noLocalCryptoPrimitives('RoundlyConsulting\Addresses');

/**
 * `addresses.model` resolves through the AddressModel seam in Support. Adopted rather than
 * rejected as jwt rejected it: addresses has exactly the shape the preset targets — a real
 * Eloquent model behind a `*_model`-style key — so the stray-literal half has something to
 * say, and nothing here needs the late static binding the preset bans (the seam returns a
 * class-string and every call site goes through `AddressModel::class()`).
 */
ArchPresets::modelsResolveThroughSeam(__DIR__.'/../src', 'Support');

/**
 * The Dependency Policy as a test. No `alsoAllow`: addresses' `require` ships only
 * php/illuminate/roundly, and the workflow installs test tooling with `--dev`, so nothing
 * legitimately lands in `require` that this must forgive. If this goes red, the graph is
 * wrong — never widen the allow-list to quiet it.
 */
ArchPresets::runtimeRequireIsWhitelisted(__DIR__.'/../composer.json');

ArchPresets::noDebuggingLeftovers();
