<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\Credentials\ConfigCredentialsProvider;
use Contoweb\AbacusApi\DataTransferObjects\AbacusApiCredentialsDto;
use Contoweb\AbacusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ConfigCredentialsProviderTest extends TestCase
{
    #[Test]
    public function it_reads_credentials_from_config(): void
    {
        $provider = new ConfigCredentialsProvider($this->app['config']);

        $credentials = $provider->getCredentials();

        $this->assertInstanceOf(AbacusApiCredentialsDto::class, $credentials);
        $this->assertEquals('https://api.example.com', $credentials->baseUrl);
        $this->assertEquals('test-mandate', $credentials->mandate);
        $this->assertEquals('test-client-id', $credentials->clientId);
        $this->assertEquals('test-client-secret', $credentials->clientSecret);
        $this->assertEquals('v1', $credentials->apiVersion);
    }

    #[Test]
    public function it_reflects_config_changes(): void
    {
        config()->set('abacus-api.rest_api.url', 'https://changed.example.com');
        config()->set('abacus-api.rest_api.mandate', 'changed-mandate');

        $provider = new ConfigCredentialsProvider($this->app['config']);
        $credentials = $provider->getCredentials();

        $this->assertEquals('https://changed.example.com', $credentials->baseUrl);
        $this->assertEquals('changed-mandate', $credentials->mandate);
    }

    #[Test]
    public function it_resolves_from_service_container(): void
    {
        $provider = $this->app->make(ConfigCredentialsProvider::class);

        $this->assertInstanceOf(ConfigCredentialsProvider::class, $provider);

        $credentials = $provider->getCredentials();
        $this->assertEquals('https://api.example.com', $credentials->baseUrl);
    }
}
