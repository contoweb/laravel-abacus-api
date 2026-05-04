<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Console\Commands\GenerateIdeHelperCommand;
use Contoweb\AbacusApi\Console\Commands\MakeAbacusComponentCommand;
use Contoweb\AbacusApi\Console\Commands\MakeAbacusModelCommand;
use Contoweb\AbacusApi\Console\Commands\MakeAbacusReportCommand;
use Contoweb\AbacusApi\Credentials\AbacusCredentialsProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AbacusServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        /* Merge config */
        $this->mergeConfigFrom(
            __DIR__.'/../config/abacus-api.php',
            'abacus-api'
        );

        $this->app->bind(AbacusCredentialsProvider::class, function (Application $app) {
            $provider = $app->make($app['config']->get('abacus-api.credentials_provider'));

            if (! $provider instanceof AbacusCredentialsProvider) {
                throw new InvalidArgumentException('The credentials provider must implement the AbacusCredentialsProvider interface');
            }

            return $provider;
        });

        /*
         * We use bind() so that the credentials for the Abacus REST API can change during the request lifecycle.
         * This ensures a fresh instance is resolved each time, so we always get the current credentials.
         */
        $this->app->bind(AbacusODataClient::class, function (Application $app) {
            return new AbacusODataClient($app->make(AbacusCredentialsProvider::class));
        });

        $this->app->bind(AbacusService::class, function (Application $app) {
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
            __DIR__.'/../config/abacus-api.php' => config_path('abacus-api.php'),
        ], ['config', 'abacus-config', 'abacus', 'abacus-api']);

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateIdeHelperCommand::class,
                MakeAbacusComponentCommand::class,
                MakeAbacusModelCommand::class,
                MakeAbacusReportCommand::class,
            ]);
        }
    }
}
