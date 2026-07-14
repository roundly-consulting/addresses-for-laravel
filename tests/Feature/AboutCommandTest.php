<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Addresses\Tests\Support\CustomAddress;
use RoundlyConsulting\Addresses\Tests\Support\StaticCountryResolver;

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
