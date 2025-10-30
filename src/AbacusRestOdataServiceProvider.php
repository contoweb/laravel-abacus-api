<?php

namespace Contoweb\AbacusRestOdata;

use Illuminate\Support\ServiceProvider;
use contoweb\AbacusRestOdata\Console\Commands\GenerateIdeHelperCommand;
use contoweb\AbacusRestOdata\Console\Commands\MakeAbacusModelCommand;

class AbacusRestOdataServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/abacus-rest-odata.php',
            'abacus-rest-odata'
        );

        // Register Client as Singleton
        $this->app->singleton(AbacusRestClient::class, function ($app) {
            return new AbacusRestClient();
        });

        // Register Service as Singleton
        $this->app->singleton(AbacusRestService::class, function ($app) {
            return new AbacusRestService(
                $app->make(AbacusRestClient::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/abacus-rest-odata.php' => config_path('abacus-rest-odata.php'),
        ], 'abacus-config');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateIdeHelperCommand::class,
                MakeAbacusModelCommand::class,
            ]);
        }
    }
}