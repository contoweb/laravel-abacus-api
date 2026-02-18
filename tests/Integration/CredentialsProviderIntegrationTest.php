<?php

namespace Contoweb\AbacusApi\Tests\Integration;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\Credentials\ProvidesApiCredentials;
use Contoweb\AbacusApi\Credentials\UserCredentialsProvider;
use Contoweb\AbacusApi\DataTransferObjects\AbacusApiCredentialsDto;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class CredentialsProviderIntegrationTest extends TestCase
{
    #[Test]
    public function it_uses_different_credentials_per_user_in_same_request_lifecycle(): void
    {
        config()->set('abacus-api.credentials_provider', UserCredentialsProvider::class);

        // Create User A mock with different credentials
        $userA = Mockery::mock(Authenticatable::class, ProvidesApiCredentials::class);
        $userA->shouldReceive('abacusCredentials')->andReturn(new AbacusApiCredentialsDto(
            baseUrl: 'https://api-a.example.com',
            mandate: 'mandate-a',
            clientId: 'client-a',
            clientSecret: 'secret-a',
            apiVersion: 'v1',
        ));
        $userA->shouldReceive('getAuthIdentifierName')->andReturn('id');
        $userA->shouldReceive('getAuthIdentifier')->andReturn(1);

        // Create User B mock with different credentials
        $userB = Mockery::mock(Authenticatable::class, ProvidesApiCredentials::class);
        $userB->shouldReceive('abacusCredentials')->andReturn(new AbacusApiCredentialsDto(
            baseUrl: 'https://api-b.example.com',
            mandate: 'mandate-b',
            clientId: 'client-b',
            clientSecret: 'secret-b',
            apiVersion: 'v1',
        ));
        $userB->shouldReceive('getAuthIdentifierName')->andReturn('id');
        $userB->shouldReceive('getAuthIdentifier')->andReturn(2);

        // Mock HTTP responses for both users
        Http::fake([
            'https://api-a.example.com/oauth/*' => Http::response([
                'access_token' => 'token-a',
                'expires_in' => 3600,
            ], 200),
            'https://api-b.example.com/oauth/*' => Http::response([
                'access_token' => 'token-b',
                'expires_in' => 3600,
            ], 200),
            'https://api-a.example.com/api/entity/v1/mandants/mandate-a/Subjects*' => Http::response([
                'value' => [
                    ['Id' => 1, 'FirstName' => 'User A Subject'],
                ],
            ], 200),
            'https://api-b.example.com/api/entity/v1/mandants/mandate-b/Subjects*' => Http::response([
                'value' => [
                    ['Id' => 2, 'FirstName' => 'User B Subject'],
                ],
            ], 200),
        ]);

        // User A Request
        Auth::login($userA);

        $clientA = $this->app->make(AbacusODataClient::class);
        $this->assertEquals('mandate-a', $clientA->getMandate());
        $this->assertEquals('https://api-a.example.com', $clientA->getUrl());

        // Make an actual HTTP request as User A
        $responseA = $clientA->get($clientA->entityPath('Subjects'));
        $this->assertTrue($responseA->successful());
        $dataA = $responseA->json('value');
        $this->assertCount(1, $dataA);
        $this->assertEquals('User A Subject', $dataA[0]['FirstName']);

        // Verify request was sent to User A's mandate
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api-a.example.com')
                && str_contains($request->url(), 'mandate-a')
                && str_contains($request->url(), 'Subjects');
        });

        // User Switch
        Auth::login($userB);

        $clientB = $this->app->make(AbacusODataClient::class);
        $this->assertEquals('mandate-b', $clientB->getMandate());
        $this->assertEquals('https://api-b.example.com', $clientB->getUrl());

        // Make an actual HTTP request as User B
        $responseB = $clientB->get($clientB->entityPath('Subjects'));
        $this->assertTrue($responseB->successful());
        $dataB = $responseB->json('value');
        $this->assertCount(1, $dataB);
        $this->assertEquals('User B Subject', $dataB[0]['FirstName']);

        // Verify request was sent to User B's mandate
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api-b.example.com')
                && str_contains($request->url(), 'mandate-b')
                && str_contains($request->url(), 'Subjects');
        });

        // Verify clients are different instances
        $this->assertNotSame($clientA, $clientB);

        $this->assertEquals('mandate-a', $clientA->getMandate());
        $this->assertEquals('https://api-a.example.com', $clientA->getUrl());

        $this->assertEquals('mandate-b', $clientB->getMandate());
        $this->assertEquals('https://api-b.example.com', $clientB->getUrl());
    }

    #[Test]
    public function it_prevents_requests_after_user_logout(): void
    {
        config()->set('abacus-api.credentials_provider', UserCredentialsProvider::class);

        // Create user mock
        $user = Mockery::mock(Authenticatable::class, ProvidesApiCredentials::class);
        $user->shouldReceive('abacusCredentials')->andReturn(new AbacusApiCredentialsDto(
            baseUrl: 'https://api.example.com',
            mandate: 'test-mandate',
            clientId: 'test-client',
            clientSecret: 'test-secret',
            apiVersion: 'v1',
        ));
        $user->shouldReceive('getAuthIdentifierName')->andReturn('id');
        $user->shouldReceive('getAuthIdentifier')->andReturn(1);
        $user->shouldReceive('getRememberToken')->andReturn(null);
        $user->shouldReceive('setRememberToken')->andReturnNull();

        // Mock HTTP responses
        Http::fake([
            'https://api.example.com/oauth/*' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            'https://api.example.com/api/entity/v1/mandants/test-mandate/Subjects*' => Http::response([
                'value' => [
                    ['Id' => 1, 'FirstName' => 'Test Subject'],
                ],
            ], 200),
        ]);

        // Logged in - request succeeds
        Auth::login($user);

        $client = $this->app->make(AbacusODataClient::class);
        $this->assertEquals('test-mandate', $client->getMandate());

        // Make an actual HTTP request while logged in
        $response = $client->get($client->entityPath('Subjects'));
        $this->assertTrue($response->successful());

        // Verify request was sent
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'Subjects');
        });

        // After logout - request should fail
        Auth::logout();

        // Trying to resolve a new client after logout should throw AuthenticationException
        $this->expectException(AuthenticationException::class);

        $this->app->make(AbacusODataClient::class);
    }
}
