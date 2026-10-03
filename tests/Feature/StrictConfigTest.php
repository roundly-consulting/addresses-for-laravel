<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use RoundlyConsulting\Addresses\AddressesServiceProvider;
use RoundlyConsulting\Addresses\Contracts\CountryResolver;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\Tests\TestModel;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;

/*
 | A typo in a host's config must fail loudly, never quietly become a default. A
 | `country_resolver` that was not a non-empty string used to leave no resolver bound — every
 | country name silently fell back to the ISO code — and a class that was not a
 | CountryResolver only failed later, with a container error that never named the key.
 */

function registerAddressesWithResolver(mixed $resolver): void
{
    config()->set('addresses.country_resolver', $resolver);
    app()->forgetInstance(CountryResolver::class);
    (new AddressesServiceProvider(app()))->register();
}

it('refuses a country resolver that is not a CountryResolver class (strict config)', function (mixed $resolver): void {
    registerAddressesWithResolver($resolver);

    expect(fn () => Addresses::countryName('sk'))
        ->toThrow(InvalidConfigurationException::class, 'Configuration value [addresses.country_resolver] must be a class-string of ['.CountryResolver::class.']');
})->with([
    'blank' => '',
    'missing class' => 'App\\Support\\MissingResolver',
    'not a resolver' => TestModel::class,
    'not a string' => [['App\\Support\\Resolver']],
    'bool' => true,
]);

it('binds no resolver when none is configured (strict config)', function (): void {
    registerAddressesWithResolver(null);

    expect(app()->bound(CountryResolver::class))->toBeFalse()
        ->and(Addresses::countryName('sk'))->toBe('SK');
});

it('keeps the about section rendering on a malformed host config (strict config)', function (): void {
    config()->set('addresses.country_resolver', TestModel::class);
    config()->set('addresses.default_type', 'warehouse');

    Artisan::call('about', ['--only' => 'addresses']);

    expect(Artisan::output())
        ->toContain('Country resolver')
        ->toContain('Default type')
        ->toContain('INVALID')
        ->not->toContain('warehouse')
        ->not->toContain('TestModel');
});

it('reports the facade alias a true switch registers (strict config)', function (): void {
    config()->set('addresses.facade_alias', true);

    Artisan::call('about', ['--only' => 'addresses']);

    expect(Artisan::output())->toMatch('/Facade alias \.+ Addresses/')
        ->not->toContain('DISABLED');
});

it('reports an unparseable facade alias switch as invalid (strict config)', function (): void {
    config()->set('addresses.facade_alias', 2);

    Artisan::call('about', ['--only' => 'addresses']);

    expect(Artisan::output())->toMatch('/Facade alias \.+ INVALID/');
});
