<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\AbacusService;
use Contoweb\AbacusApi\Credentials\AbacusCredentialsProvider;
use Contoweb\AbacusApi\Credentials\ConfigCredentialsProvider;
use Contoweb\AbacusApi\Tests\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;

class AbacusServiceProviderTest extends TestCase
{
    #[Test]
    public function it_binds_credentials_provider_from_config(): void
    {
        $provider = $this->app->make(AbacusCredentialsProvider::class);

        $this->assertInstanceOf(ConfigCredentialsProvider::class, $provider);
    }

    #[Test]
    public function it_throws_exception_for_invalid_credentials_provider(): void
    {
        config()->set('abacus-api.credentials_provider', \stdClass::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The credentials provider must implement the AbacusCredentialsProvider interface');

        $this->app->make(AbacusCredentialsProvider::class);
    }

    #[Test]
    public function it_registers_odata_client_as_singleton(): void
    {
        $client1 = $this->app->make(AbacusODataClient::class);
        $client2 = $this->app->make(AbacusODataClient::class);

        $this->assertInstanceOf(AbacusODataClient::class, $client1);
        $this->assertSame($client1, $client2);
    }

    #[Test]
    public function it_registers_abacus_service_as_singleton(): void
    {
        $service1 = $this->app->make(AbacusService::class);
        $service2 = $this->app->make(AbacusService::class);

        $this->assertInstanceOf(AbacusService::class, $service1);
        $this->assertSame($service1, $service2);
    }
}
