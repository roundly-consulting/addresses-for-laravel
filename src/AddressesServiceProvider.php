<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses;

use RoundlyConsulting\Addresses\Contracts\CountryResolver;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\Support\AddressModel;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;
use RoundlyConsulting\PackageToolkit\Support\Config;

final class AddressesServiceProvider extends PackageServiceProvider
{
    use RegistersBlueprintMacros;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('addresses')
            ->hasConfigFile()
            ->hasMigrations()
            ->hasFacadeAlias(Addresses::class, 'addresses.facade_alias')
            ->contributesToAbout(static fn (): array => [
                // The resolver is a host class name, so only its base name shows;
                // no config value here is a credential or a destination.
                'Model' => class_basename(AddressModel::class()),
                'Table' => 'addresses',
                'Default type' => self::defaultType(),
                'Normalise country' => self::switch('addresses.normalise_country', true),
                'Country resolver' => self::resolverName(),
                'Facade alias' => self::aliasName(),
            ]);
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(AddressManager::class);

        $this->registerCountryResolver();
    }

    public function boot(): void
    {
        parent::boot();

        // The migration's key-type-aware morph column is a macro, so it must exist
        // before a host runs `php artisan migrate`.
        $this->registerBlueprintMacros();
    }

    /**
     * Bind a host-provided CountryResolver only when one is configured. The
     * package never ships a default that touches the network.
     */
    private function registerCountryResolver(): void
    {
        $resolver = config('addresses.country_resolver');

        if (! is_string($resolver) || $resolver === '') {
            return;
        }

        /** @var class-string<CountryResolver> $resolver */
        $this->app->singleton(CountryResolver::class, $resolver);
    }

    private static function defaultType(): string
    {
        $type = config('addresses.default_type');

        return is_string($type) && $type !== '' ? $type : 'default';
    }

    private static function resolverName(): string
    {
        $resolver = config('addresses.country_resolver');

        return is_string($resolver) && $resolver !== '' ? class_basename($resolver) : 'NONE';
    }

    private static function aliasName(): string
    {
        $alias = config('addresses.facade_alias');

        return is_string($alias) && $alias !== '' ? $alias : 'DISABLED';
    }

    private static function switch(string $key, bool $default): string
    {
        return Config::boolean($key, $default) ? 'ON' : 'OFF';
    }
}
