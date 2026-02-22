<?php

namespace Crumbls\FilamentDatabase;

use Illuminate\Support\ServiceProvider;

class FilamentDatabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/filament-database.php', 'filament-database');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-database');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/filament-database.php' => config_path('filament-database.php'),
            ], 'filament-database-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/filament-database'),
            ], 'filament-database-views');
        }
    }
}
