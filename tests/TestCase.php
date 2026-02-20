<?php

namespace Contoweb\AbacusApi\Tests;

use Contoweb\AbacusApi\AbacusServiceProvider;
use Contoweb\AbacusApi\Credentials\AbacusCredentialsProvider;
use Contoweb\AbacusApi\Credentials\ConfigCredentialsProvider;
use Contoweb\AbacusApi\DataTransferObjects\AbacusApiCredentialsDto;
use Contoweb\AbacusApi\Tests\Helpers\WithEncryption;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    use WithEncryption;

    protected function setUp(): void
    {
        parent::setUp();

        /* Set up encryption for all tests */
        $this->setUpEncryption();
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
        $app['config']->set('abacus-api.credentials_provider', ConfigCredentialsProvider::class);
        $app['config']->set('cache.default', 'array');

        /* Configure for console commands */
        $app['config']->set('abacus-api.models_namespace', 'App\Models\Abacus');
        $app['config']->set('abacus-api.reports.reports_namespace', 'App\Reports');
    }

    protected function makeCredentialsProvider(): AbacusCredentialsProvider
    {
        return $this->makeCustomCredentialsProvider(
            'https://api.example.com',
            'test-mandate',
            'test-client-id',
            'test-client-secret',
            'v1',
        );
    }

    protected function makeCustomCredentialsProvider(
        string $baseUrl = 'https://api.example.com',
        string $mandate = 'test-mandate',
        string $clientId = 'test-client-id',
        string $clientSecret = 'test-client-secret',
        string $apiVersion = 'v1',
    ): AbacusCredentialsProvider {
        return new class($baseUrl, $mandate, $clientId, $clientSecret, $apiVersion) implements AbacusCredentialsProvider
        {
            public function __construct(
                private readonly string $baseUrl,
                private readonly string $mandate,
                private readonly string $clientId,
                private readonly string $clientSecret,
                private readonly string $apiVersion,
            ) {}

            public function getCredentials(): AbacusApiCredentialsDto
            {
                return new AbacusApiCredentialsDto(
                    $this->baseUrl,
                    $this->mandate,
                    $this->clientId,
                    $this->clientSecret,
                    $this->apiVersion,
                );
            }
        };
    }
}
