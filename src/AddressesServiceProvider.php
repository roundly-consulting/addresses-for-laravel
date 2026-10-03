<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses;

use Illuminate\Contracts\Foundation\Application;
use RoundlyConsulting\Addresses\Contracts\CountryResolver;
use RoundlyConsulting\Addresses\Exceptions\InvalidAddressTypeException;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\Support\AddressModel;
use RoundlyConsulting\Addresses\Support\DefaultAddressType;
use RoundlyConsulting\PackageToolkit\Concerns\RegistersBlueprintMacros;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
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
     *
     * Any configured value is bound, and checked when the resolver is first
     * resolved: it must name a CountryResolver class, or the read throws naming the
     * key — a typo never silently leaves country names unresolved.
     */
    private function registerCountryResolver(): void
    {
        if (config('addresses.country_resolver') === null) {
            return;
        }

        $this->app->singleton(CountryResolver::class, static fn (Application $app): CountryResolver => $app->make(self::resolverClass()));
    }

    /**
     * @return class-string<CountryResolver>
     *
     * @throws InvalidConfigurationException
     */
    private static function resolverClass(): string
    {
        $resolver = config('addresses.country_resolver');

        if (! is_string($resolver) || ! class_exists($resolver) || ! is_a($resolver, CountryResolver::class, true)) {
            throw InvalidConfigurationException::notAnImplementation('addresses.country_resolver', CountryResolver::class, $resolver);
        }

        return $resolver;
    }

    private static function defaultType(): string
    {
        try {
            return DefaultAddressType::resolve()->value;
        } catch (InvalidAddressTypeException) {
            return 'INVALID';
        }
    }

    private static function resolverName(): string
    {
        if (config('addresses.country_resolver') === null) {
            return 'NONE';
        }

        try {
            return class_basename(self::resolverClass());
        } catch (InvalidConfigurationException) {
            return 'INVALID';
        }
    }

    /**
     * The alias the toolkit registers for `addresses.facade_alias`: null or a false
     * spelling skips it, a true spelling keeps the declared `Addresses`, any other string
     * renames it.
     */
    private static function aliasName(): string
    {
        $alias = config('addresses.facade_alias', true);

        if (is_string($alias) && filter_var($alias, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === null) {
            return $alias;
        }

        try {
            return $alias !== null && Config::boolean('addresses.facade_alias', true) ? 'Addresses' : 'DISABLED';
        } catch (InvalidConfigurationException) {
            return 'INVALID';
        }
    }

    private static function switch(string $key, bool $default): string
    {
        return Config::boolean($key, $default) ? 'ON' : 'OFF';
    }
}
