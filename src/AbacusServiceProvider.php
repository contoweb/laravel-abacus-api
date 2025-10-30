<?php

namespace Contoweb\AbacusOdata;

use Contoweb\AbacusOdata\Console\Commands\GenerateIdeHelperCommand;
use Contoweb\AbacusOdata\Console\Commands\MakeAbacusModelCommand;
use Illuminate\Support\ServiceProvider;

class AbacusServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/abacus-odata.php',
            'abacus-odata'
        );

        // Register Client as Singleton
        $this->app->singleton(AbacusClient::class, function ($app) {
            return new AbacusClient();
        });

        // Register Service as Singleton
        $this->app->singleton(AbacusService::class, function ($app) {
            return new AbacusService(
                $app->make(AbacusClient::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config with multiple tags
        $this->publishes([
            __DIR__.'/../config/abacus-odata.php' => config_path('abacus-odata.php'),
        ], ['config', 'abacus-config', 'abacus', 'abacus-odata']);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateIdeHelperCommand::class,
                MakeAbacusModelCommand::class,
            ]);
        }
    }
}