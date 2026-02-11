<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Console\Commands\GenerateIdeHelperCommand;
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

        /* Only register singletons if credentials are configured */
        if ($this->hasConfiguredCredentials()) {
            $this->app->singleton(AbacusODataClient::class, function ($app) {
                return new AbacusODataClient(logger: $app->make('abacus.logger'));
            });

        $this->app->singleton(AbacusService::class, function (Application $app) {
            return new AbacusService($app->make(AbacusODataClient::class));
        });
    }

    /**
     * Check if API credentials are configured.
     */
    protected function hasConfiguredCredentials(): bool
    {
        $baseUrl = config('abacus-api.rest_api.url');
        $mandate = config('abacus-api.rest_api.mandate');
        $clientId = config('abacus-api.rest_api.client_id');
        $clientSecret = config('abacus-api.rest_api.client_secret');

        return ! empty($baseUrl) && ! empty($mandate) && ! empty($clientId) && ! empty($clientSecret);
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
                MakeAbacusModelCommand::class,
                MakeAbacusReportCommand::class,
            ]);
        }
    }
}
