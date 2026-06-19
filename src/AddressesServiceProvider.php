<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Addresses\Contracts\CountryResolver;
use RoundlyConsulting\Addresses\Facades\Addresses;

final class AddressesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/addresses.php', 'addresses');

        $this->app->singleton(AddressManager::class, fn (): AddressManager => new AddressManager);

        $this->registerCountryResolver();
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerFacadeAlias();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/addresses.php' => config_path('addresses.php'),
            ], 'addresses-config');

            $this->publishes([
                __DIR__.'/../database/migrations/create_addresses_table.php' => $this->publishedMigrationPath(),
            ], 'addresses-migrations');
        }
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

    private function registerFacadeAlias(): void
    {
        $alias = config('addresses.facade_alias');

        if (! is_string($alias) || $alias === '') {
            return;
        }

        AliasLoader::getInstance()->alias($alias, Addresses::class);
    }

    private function publishedMigrationPath(): string
    {
        return database_path('migrations/'.date('Y_m_d_His').'_create_addresses_table.php');
    }
}
