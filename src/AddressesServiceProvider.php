<?php

declare(strict_types=1);

namespace RoundlyConsulting\Addresses;

use Illuminate\Support\ServiceProvider;

final class AddressesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/addresses.php', 'addresses');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/addresses.php' => config_path('addresses.php'),
            ], 'addresses-config');

            $this->publishes([
                __DIR__.'/../database/migrations/create_addresses_table.php' => $this->publishedMigrationPath(),
            ], 'addresses-migrations');
        }
    }

    private function publishedMigrationPath(): string
    {
        return database_path('migrations/'.date('Y_m_d_His').'_create_addresses_table.php');
    }
}
