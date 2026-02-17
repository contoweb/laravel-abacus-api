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

    #[Test]
    public function it_resolves_fresh_client_with_new_credentials_on_user_switch(): void
    {
        config()->set('abacus-api.credentials_provider', UserCredentialsProvider::class);

        $userA = Mockery::mock(Authenticatable::class, ProvidesApiCredentials::class);
        $userA->shouldReceive('abacusCredentials')->andReturn(new AbacusApiCredentialsDto(
            'https://api.example.com', 'mandate-a', 'client-a', 'secret-a', 'v1',
        ));

        $userB = Mockery::mock(Authenticatable::class, ProvidesApiCredentials::class);
        $userB->shouldReceive('abacusCredentials')->andReturn(new AbacusApiCredentialsDto(
            'https://api.example.com', 'mandate-b', 'client-b', 'secret-b', 'v1',
        ));

        $currentUser = $userA;

        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->andReturnUsing(function () use (&$currentUser) {
            return $currentUser;
        });

        $this->app->instance(Guard::class, $guard);

        // User A is logged in
        $clientA = $this->app->make(AbacusODataClient::class);
        $this->assertEquals('mandate-a', $clientA->getMandate());

        // Simulate request boundary (user switch)
        $this->app->forgetScopedInstances();
        $currentUser = $userB;

        // User B is now logged in
        $clientB = $this->app->make(AbacusODataClient::class);
        $this->assertEquals('mandate-b', $clientB->getMandate());
        $this->assertNotSame($clientA, $clientB);
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
        $this->app->forgetScopedInstances();
        $currentUser = null;

        // Resolving after logout throws exception
        $this->expectException(AuthenticationException::class);
        $this->app->make(AbacusODataClient::class);
    }
}
