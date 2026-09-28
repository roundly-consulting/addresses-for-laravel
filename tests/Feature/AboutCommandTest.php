<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Addresses\DataTransferObjects\AddressData;
use RoundlyConsulting\Addresses\Enums\AddressType;
use RoundlyConsulting\Addresses\Tests\Support\CustomAddress;
use RoundlyConsulting\Addresses\Tests\Support\StaticCountryResolver;
use RoundlyConsulting\Addresses\Tests\TestModel;

function addressesAboutOutput(): string
{
    Artisan::call('about', ['--only' => 'addresses']);

    return Artisan::output();
}

it('contributes an addresses section to about', function () {
    expect(addressesAboutOutput())
        ->toContain('Addresses')
        ->toContain('Model')
        ->toContain('Address')
        ->toContain('Table')
        ->toContain('addresses');
});

it('reports the configured model and switches', function () {
    config()->set('addresses.model', CustomAddress::class);
    config()->set('addresses.normalise_country', false);

    expect(addressesAboutOutput())
        ->toContain('CustomAddress')
        ->toContain('OFF');
});

it('reports the country resolver by base name only, never its namespace', function () {
    config()->set('addresses.country_resolver', StaticCountryResolver::class);

    expect(addressesAboutOutput())
        ->toContain('StaticCountryResolver')
        ->not->toContain(StaticCountryResolver::class);
});

it('reports a disabled facade alias without inventing a name', function () {
    config()->set('addresses.facade_alias', null);

    expect(addressesAboutOutput())->toContain('DISABLED');
});

/**
 * The secret-safe `about` capture (A).
 *
 * Purchases #13 is the bug this exists for: the fleet's most credential-heavy `about`
 * section was guarded by negative assertions against `app(Kernel::class)->output()`, which
 * returns `''`. Every "does not leak" check was vacuous — passing against empty output.
 * The checks above capture through `Artisan::output()` and so are real, but they are all
 * positive; nothing here asserted what must *never* render.
 *
 * Addresses' section carries no credentials, which is exactly why it is worth pinning: the
 * risk here is **PII**. Rows in this table are people's home addresses, and the section
 * reports the resolver and the model — never a street, a postcode, or the namespace of a
 * host class. `mustRender` is required and non-empty, so the negative half can never pass
 * over empty output.
 */
it('renders the addresses section without leaking the addresses it stores', function () {
    config()->set('addresses.country_resolver', StaticCountryResolver::class);

    $entity = TestModel::create();
    $entity->addAddress(AddressData::make(
        city: 'Bratislava',
        street: 'Hlavná 1',
        postalCode: '81101',
        countryIso: 'SK',
        type: AddressType::Home,
    ));

    expect('addresses')->toLeakNoSecrets(
        secrets: [
            // No stored address may ever reach an `about` section.
            'Hlavná 1',
            'Bratislava',
            '81101',
            // The resolver is reported by base name only; its namespace names a host's
            // internal class layout and is not ours to print.
            StaticCountryResolver::class,
        ],
        mustRender: [
            'Model',
            'Table',
            'Default type',
            'Normalise country',
            'Country resolver',
            'Facade alias',
            // The positive proof that the resolver line reports rather than sitting
            // silently empty.
            'StaticCountryResolver',
        ],
    );
});

it('reads an env-string normalisation switch the way the package applies it', function () {
    config()->set('addresses.normalise_country', 'false');

    expect(addressesAboutOutput())->toMatch('/Normalise country\W+OFF/');
});
