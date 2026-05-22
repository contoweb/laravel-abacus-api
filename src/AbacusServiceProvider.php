<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Console\Commands\GenerateIdeHelperCommand;
use Contoweb\AbacusApi\Console\Commands\MakeAbacusComponentCommand;
use Contoweb\AbacusApi\Console\Commands\MakeAbacusModelCommand;
use Contoweb\AbacusApi\Console\Commands\MakeAbacusReportCommand;
use Contoweb\AbacusApi\Credentials\AbacusCredentialsProvider;
use Contoweb\AbacusApi\Credentials\ConfigCredentialsProvider;
use Contoweb\AbacusApi\Reports\AbacusReportsClient;
use Contoweb\AbacusApi\Reports\AbacusReportsService;
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

        $this->app->bind(ConfigCredentialsProvider::class, function (Application $app) {
            $config = $app['config'];

            $values = [
                'url' => $config->get('abacus-api.rest_api.url'),
                'mandate' => $config->get('abacus-api.rest_api.mandate'),
                'client_id' => $config->get('abacus-api.rest_api.client_id'),
                'client_secret' => $config->get('abacus-api.rest_api.client_secret'),
                'version' => $config->get('abacus-api.rest_api.version'),
            ];

            foreach ($values as $key => $value) {
                if ($value === null || trim($value) === '') {
                    throw new InvalidArgumentException("Config value $key is missing or empty.");
                }
            }

            return new ConfigCredentialsProvider(
                $values['url'],
                $values['mandate'],
                $values['client_id'],
                $values['client_secret'],
                $values['version']
            );
        });

        $this->app->bind(AbacusCredentialsProvider::class, function (Application $app) {
            $provider = $app->make($app['config']->get('abacus-api.credentials_provider'));

            if (! $provider instanceof AbacusCredentialsProvider) {
                throw new InvalidArgumentException('The credentials provider must implement the '.AbacusCredentialsProvider::class.' interface');
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

        $this->app->bind(AbacusReportsService::class, function (Application $app) {
            return new AbacusReportsService(
                $app->make(AbacusReportsClient::class),
                $app['config']->get('abacus-api.reports.poll_interval'),
                $app['config']->get('abacus-api.reports.max_poll_attempts')
            );
        });

        $this->app->bind(GenerateIdeHelperCommand::class, function (Application $app) {
            return new GenerateIdeHelperCommand(
                $app['config']->get('abacus-api.ide_helper.output_file'),
                $app['config']->get('abacus-api.models_namespace')
            );
        });

        $this->app->bind(MakeAbacusComponentCommand::class, function (Application $app) {
            return new MakeAbacusComponentCommand($app['config']->get('abacus-api.components_namespace'));
        });

        $this->app->bind(MakeAbacusModelCommand::class, function (Application $app) {
            return new MakeAbacusModelCommand($app['config']->get('abacus-api.models_namespace'));
        });

        $this->app->bind(MakeAbacusReportCommand::class, function (Application $app) {
            return new MakeAbacusReportCommand($app['config']->get('abacus-api.reports.reports_namespace'));
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
