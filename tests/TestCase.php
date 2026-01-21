<?php

namespace Contoweb\AbacusApi\Tests;

use Contoweb\AbacusApi\AbacusServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            AbacusServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        /* Configure test environment */
        $app['config']->set('abacus-api.rest_api.url', 'https://api.example.com');
        $app['config']->set('abacus-api.rest_api.mandate', 'test-mandate');
        $app['config']->set('abacus-api.rest_api.client_id', 'test-client-id');
        $app['config']->set('abacus-api.rest_api.client_secret', 'test-client-secret');
        $app['config']->set('abacus-api.rest_api.version', 'v1');
        $app['config']->set('cache.default', 'array');

        /* Configure for console commands */
        $app['config']->set('abacus-api.models_namespace', 'App\Models\Abacus');
        $app['config']->set('abacus-api.reports.reports_namespace', 'App\Reports');
    }
}
