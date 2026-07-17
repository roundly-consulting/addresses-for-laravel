<?php

declare(strict_types=1);

/**
 * The config contract addresses never had, pinned in both directions:
 *
 *  - forward — every key the code reads is shipped. This is shops #18, whose whole
 *    store-credit feature read `shops.payments.*` while the file shipped `payment.*`; 330
 *    tests stayed green because the suite set the same wrong key.
 *  - reverse — every shipped leaf is read. A documented key nothing reads is dead config
 *    that lies to the host: media #27's `max_file_size` cap that never applied (an upload
 *    endpoint with no size limit at all), alerts #24's thrice-documented `escalation` key.
 *    `addresses.country_resolver` is the key here with the most room to rot — it is
 *    optional, defaults to null, and the package bundles no implementation.
 */
it('ships exactly the config keys it reads', function (): void {
    expect(__DIR__.'/../../config/addresses.php')->toSatisfyConfigContract([__DIR__.'/../../src', __DIR__.'/../../database'], [
        // Two real reads reach a key without ever writing a `config(` token, so the prefix
        // is what makes them visible to the scraper:
        //   - `addresses.model` goes through the toolkit's
        //     `ModelResolver::for('addresses.model', …)` seam;
        //   - `addresses.facade_alias` is passed to the toolkit's
        //     `hasFacadeAlias(Addresses::class, 'addresses.facade_alias')` as a literal;
        //   - `addresses.key_type` is read through `KeyType::fromConfig('addresses.key_type')`
        //     in the migration (hence `database` in the scanned dirs) — it decides the
        //     shipped morph column type.
        // All drive real behaviour; none is a `config()` call.
        'extraReadPrefixes' => ['addresses.'],

        // Deliberately NO `excludeFromReverse` for the provider. The testing README's own
        // example excludes the service provider on the grounds that "a render is not a
        // read" — but this provider's `contributesToAbout()` closure calls
        // `config('addresses.…')` for real, and `registerCountryResolver()` reads
        // `addresses.country_resolver` to decide whether to bind at all. Excluding it would
        // discard the only reader of several keys and weaken the reverse direction for
        // nothing.
    ]);
});
