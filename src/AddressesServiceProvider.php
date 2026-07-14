<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses;

use RoundlyConsulting\Addresses\Contracts\CountryResolver;
use RoundlyConsulting\Addresses\Facades\Addresses;
use RoundlyConsulting\Addresses\Support\AddressModel;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

final class AddressesServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('addresses')
            ->hasConfigFile()
            // The migration is both auto-loaded (a plain `php artisan migrate`
            // creates the table) and publishable under a host-stamped filename.
            // The toolkit's hasMigration() only publishes a `.php.stub` — it does
            // not load — so the publish half uses the generic stub escape hatch
            // and boot() keeps loading the directory.
            ->publishesStubs(
                __DIR__.'/../database/migrations/create_addresses_table.php',
                self::publishedMigrationPath(),
                'addresses-migrations',
            )
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

        $this->app->singleton(AddressManager::class, fn (): AddressManager => new AddressManager);

        $this->registerCountryResolver();
    }

    public function boot(): void
    {
        parent::boot();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
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

    private static function publishedMigrationPath(): string
    {
        return database_path('migrations/'.date('Y_m_d_His').'_create_addresses_table.php');
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
        return (bool) config($key, $default) ? 'ON' : 'OFF';
    }
}
