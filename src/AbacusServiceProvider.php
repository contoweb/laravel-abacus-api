<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Console\Commands\GenerateIdeHelperCommand;
use Contoweb\AbacusApi\Console\Commands\MakeAbacusModelCommand;
use Contoweb\AbacusApi\Console\Commands\MakeAbacusReportCommand;
use Illuminate\Support\ServiceProvider;

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