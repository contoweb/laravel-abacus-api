<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\AbacusService;
use Contoweb\AbacusApi\Credentials\AbacusCredentialsProvider;
use Contoweb\AbacusApi\Credentials\ConfigCredentialsProvider;
use Contoweb\AbacusApi\Credentials\ProvidesApiCredentials;
use Contoweb\AbacusApi\Credentials\UserCredentialsProvider;
use Contoweb\AbacusApi\DataTransferObjects\AbacusApiCredentialsDto;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use InvalidArgumentException;
use Mockery;
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
    public function it_resolves_fresh_odata_client_instance_on_each_make(): void
    {
        $client1 = $this->app->make(AbacusODataClient::class);
        $client2 = $this->app->make(AbacusODataClient::class);

        $this->assertInstanceOf(AbacusODataClient::class, $client1);
        $this->assertNotSame($client1, $client2);
    }

    #[Test]
    public function it_resolves_fresh_abacus_service_instance_on_each_make(): void
    {
        $service1 = $this->app->make(AbacusService::class);
        $service2 = $this->app->make(AbacusService::class);

        $this->assertInstanceOf(AbacusService::class, $service1);
        $this->assertNotSame($service1, $service2);
    }

    #[Test]
    public function it_throws_exception_when_user_logs_out_and_client_is_resolved(): void
    {
        config()->set('abacus-api.credentials_provider', UserCredentialsProvider::class);

        $user = Mockery::mock(Authenticatable::class, ProvidesApiCredentials::class);
        $user->shouldReceive('abacusCredentials')->andReturn(new AbacusApiCredentialsDto(
            'https://api.example.com', 'mandate-a', 'client-a', 'secret-a', 'v1',
        ));

        $currentUser = $user;

        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturnUsing(function () use (&$currentUser) {
            return $currentUser;
        });

        $this->app->instance(Guard::class, $guard);

        // User is logged in — resolving works
        $client = $this->app->make(AbacusODataClient::class);
        $this->assertEquals('mandate-a', $client->getMandate());

        // Simulate logout
        $currentUser = null;

        // Resolving after logout throws exception
        $this->expectException(AuthenticationException::class);
        $this->app->make(AbacusODataClient::class);
    }

    #[Test]
    public function it_resolves_fresh_client_with_new_config_credentials_on_each_make(): void
    {
        // Initial config
        config()->set('abacus-api.rest_api.mandate', 'mandate-initial');
        config()->set('abacus-api.rest_api.client_id', 'client-initial');
        config()->set('abacus-api.rest_api.url', 'https://api-initial.example.com');

        // First client with initial credentials
        $client1 = $this->app->make(AbacusODataClient::class);
        $this->assertEquals('mandate-initial', $client1->getMandate());
        $this->assertEquals('https://api-initial.example.com', $client1->getUrl());

        // Change config at runtime
        config()->set('abacus-api.rest_api.mandate', 'mandate-changed');
        config()->set('abacus-api.rest_api.client_id', 'client-changed');
        config()->set('abacus-api.rest_api.url', 'https://api-changed.example.com');

        // Second client should have new credentials
        $client2 = $this->app->make(AbacusODataClient::class);
        $this->assertEquals('mandate-changed', $client2->getMandate());
        $this->assertEquals('https://api-changed.example.com', $client2->getUrl());

        // Verify they are different instances
        $this->assertNotSame($client1, $client2);

        // Verify first client still has old credentials (immutable after construction)
        $this->assertEquals('mandate-initial', $client1->getMandate());
    }

    #[Test]
    public function it_resolves_fresh_client_with_new_user_credentials_on_user_switch(): void
    {
        config()->set('abacus-api.credentials_provider', UserCredentialsProvider::class);

        // Create two different users with different credentials
        $userA = Mockery::mock(Authenticatable::class, ProvidesApiCredentials::class);
        $userA->shouldReceive('abacusCredentials')->andReturn(new AbacusApiCredentialsDto(
            'https://api-a.example.com', 'mandate-a', 'client-a', 'secret-a', 'v1',
        ));

        $userB = Mockery::mock(Authenticatable::class, ProvidesApiCredentials::class);
        $userB->shouldReceive('abacusCredentials')->andReturn(new AbacusApiCredentialsDto(
            'https://api-b.example.com', 'mandate-b', 'client-b', 'secret-b', 'v1',
        ));

        // Mock Guard with dynamic user switching
        $currentUser = $userA;
        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturnUsing(function () use (&$currentUser) {
            return $currentUser;
        });

        $this->app->instance(Guard::class, $guard);

        // User A is active
        $clientA = $this->app->make(AbacusODataClient::class);
        $this->assertEquals('mandate-a', $clientA->getMandate());
        $this->assertEquals('https://api-a.example.com', $clientA->getUrl());

        // Switch to User B (NO forgetScopedInstances needed!)
        $currentUser = $userB;

        // User B is now active - should get fresh instance with new credentials
        $clientB = $this->app->make(AbacusODataClient::class);
        $this->assertEquals('mandate-b', $clientB->getMandate());
        $this->assertEquals('https://api-b.example.com', $clientB->getUrl());

        // Verify they are different instances
        $this->assertNotSame($clientA, $clientB);

        // Verify first client still has User A credentials (immutable after construction)
        $this->assertEquals('mandate-a', $clientA->getMandate());
    }
}
