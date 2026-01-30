<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Console\Commands\GenerateIdeHelperCommand;
use Contoweb\AbacusApi\Console\Commands\MakeAbacusModelCommand;
use Contoweb\AbacusApi\Console\Commands\MakeAbacusReportCommand;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AbacusServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        /* Merge config */
        $this->mergeConfigFrom(
            __DIR__ . '/../config/abacus-api.php',
            'abacus-api'
        );

        $this->app->singleton('abacus.logger', function ($app) {
            if (config('abacus-api.request_logging.enabled')) {
                return $app->make(LoggerInterface::class);
            }

            return new NullLogger();
        });

        /* Register AbacusClient as singleton */
        $this->app->singleton(AbacusODataClient::class, function ($app) {
            return new AbacusODataClient(logger: $app->make('abacus.logger'));
        });

        /* Register AbacusService as singleton */
        $this->app->singleton(AbacusService::class, function ($app) {
            return new AbacusService($app->make(AbacusODataClient::class));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config with multiple tags
        $this->publishes([
            __DIR__ . '/../config/abacus-api.php' => config_path('abacus-api.php'),
        ], ['config', 'abacus-config', 'abacus', 'abacus-api']);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateIdeHelperCommand::class,
                MakeAbacusModelCommand::class,
                MakeAbacusReportCommand::class,
            ]);
        }
    }
}