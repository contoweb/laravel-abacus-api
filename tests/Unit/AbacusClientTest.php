<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\Exceptions\AbacusAuthenticationException;
use Contoweb\AbacusApi\Exceptions\AbacusRateLimitException;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class AbacusClientTest extends TestCase
{
    protected AbacusODataClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->client = new AbacusODataClient($this->makeCredentialsProvider());
    }

    #[Test]
    public function it_constructs_with_credentials_provider(): void
    {
        $client = new AbacusODataClient($this->makeCredentialsProvider());

        $this->assertEquals('https://api.example.com', $client->getUrl());
        $this->assertEquals('1212', $client->getMandate());
    }

    #[Test]
    public function it_constructs_with_custom_credentials_provider(): void
    {
        $client = new AbacusODataClient($this->makeCustomCredentialsProvider(
            baseUrl: 'https://custom-api.example.com',
            mandate: 'custom-mandate',
            clientId: 'custom-client-id',
            clientSecret: 'custom-secret',
            apiVersion: 'v2',
        ));

        $this->assertEquals('https://custom-api.example.com', $client->getUrl());
        $this->assertEquals('custom-mandate', $client->getMandate());
    }

    #[Test]
    public function it_prepends_https_to_base_url_if_missing(): void
    {
        $client = new AbacusODataClient($this->makeCustomCredentialsProvider(baseUrl: 'api.example.com'));

        $this->assertEquals('https://api.example.com', $client->getUrl());
    }

    #[Test]
    public function it_does_not_prepend_https_if_already_present(): void
    {
        $client = new AbacusODataClient($this->makeCustomCredentialsProvider(baseUrl: 'https://api.example.com'));

        $this->assertEquals('https://api.example.com', $client->getUrl());
    }

    #[Test]
    public function it_respects_http_protocol(): void
    {
        $client = new AbacusODataClient($this->makeCustomCredentialsProvider(baseUrl: 'http://api.example.com'));

        $this->assertEquals('http://api.example.com', $client->getUrl());
    }

    #[Test]
    public function it_fetches_fresh_access_token(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token-12345',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
        ]);

        /* Use reflection to call protected method */
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('fetchFreshAccessToken');
        $method->setAccessible(true);

        $token = $method->invoke($this->client);

        $this->assertEquals('test-token-12345', $token);
    }

    #[Test]
    public function it_caches_access_token_with_buffer(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'cached-token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('fetchFreshAccessToken');
        $method->setAccessible(true);

        $method->invoke($this->client);

        /* Check cache key method */
        $cacheKeyMethod = $reflection->getMethod('getCacheKey');
        $cacheKeyMethod->setAccessible(true);
        $cacheKey = $cacheKeyMethod->invoke($this->client);

        $cachedEncryptedToken = Cache::get($cacheKey);
        $cachedToken = decrypt($cachedEncryptedToken);
        $this->assertEquals('cached-token', $cachedToken);
    }

    #[Test]
    public function it_throws_exception_when_token_fetch_fails(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([], 401),
        ]);

        $this->expectException(AbacusAuthenticationException::class);
        $this->expectExceptionMessage('Cannot fetch access token from API.');

        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('fetchFreshAccessToken');
        $method->setAccessible(true);

        $method->invoke($this->client);
    }

    #[Test]
    public function it_throws_exception_when_access_token_missing_in_response(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'expires_in' => 3600,
            ], 200),
        ]);

        $this->expectException(AbacusAuthenticationException::class);
        $this->expectExceptionMessage('Cannot fetch access token from API.');

        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('fetchFreshAccessToken');
        $method->setAccessible(true);

        $method->invoke($this->client);
    }

    #[Test]
    public function it_reuses_cached_access_token(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'initial-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response(['value' => []], 200),
        ]);

        /* First call fetches token */
        $this->client->get('/api/entities');

        Http::assertSentCount(2); /* Token + API call */

        /* Second call reuses cached token */
        $this->client->get('/api/entities');

        Http::assertSentCount(3); /* No additional token request */
    }

    #[Test]
    public function it_performs_get_request(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entities*' => Http::response([
                'value' => [
                    ['Id' => 1, 'Name' => 'Test'],
                ],
            ], 200),
        ]);

        $response = $this->client->get('/api/entities', ['$top' => 10]);

        $this->assertEquals(200, $response->status());
        $this->assertEquals([['Id' => 1, 'Name' => 'Test']], $response->json('value'));

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.example.com/api/entities?%24top=10' &&
                   $request->hasHeader('Authorization', 'Bearer test-token');
        });
    }

    #[Test]
    public function it_performs_post_request(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entities' => Http::response([
                'Id' => 1,
                'Name' => 'Created Entity',
            ], 201),
        ]);

        $response = $this->client->post('/api/entities', ['Name' => 'Created Entity']);

        $this->assertEquals(201, $response->status());
        $this->assertEquals('Created Entity', $response->json('Name'));

        Http::assertSent(function (Request $request) {
            return $request->method() === 'POST' &&
                   $request->data() === ['Name' => 'Created Entity'];
        });
    }

    #[Test]
    public function it_performs_patch_request(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entities/1' => Http::response([
                'Id' => 1,
                'Name' => 'Updated Entity',
            ], 200),
        ]);

        $response = $this->client->patch('/api/entities/1', ['Name' => 'Updated Entity']);

        $this->assertEquals(200, $response->status());

        Http::assertSent(function (Request $request) {
            return $request->method() === 'PATCH' &&
                   $request->data() === ['Name' => 'Updated Entity'];
        });
    }

    #[Test]
    public function it_performs_put_request(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entities/1' => Http::response([
                'Id' => 1,
                'Name' => 'Replaced Entity',
            ], 200),
        ]);

        $response = $this->client->put('/api/entities/1', ['Name' => 'Replaced Entity']);

        $this->assertEquals(200, $response->status());

        Http::assertSent(function (Request $request) {
            return $request->method() === 'PUT';
        });
    }

    #[Test]
    public function it_performs_delete_request(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entities/1' => Http::response(null, 204),
        ]);

        $response = $this->client->delete('/api/entities/1');

        $this->assertEquals(204, $response->status());

        Http::assertSent(function (Request $request) {
            return $request->method() === 'DELETE' &&
                   str_contains($request->url(), '/api/entities/1');
        });
    }

    #[Test]
    public function it_refreshes_token_on_401_response(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::sequence()
                ->push(['access_token' => 'initial-token', 'expires_in' => 3600], 200)
                ->push(['access_token' => 'refreshed-token', 'expires_in' => 3600], 200),
            '*/api/entities' => Http::sequence()
                ->push(['error' => 'Unauthorized'], 401)
                ->push(['value' => []], 200),
        ]);

        $response = $this->client->get('/api/entities');

        $this->assertEquals(200, $response->status());

        /* Should have: 1st token request, 401 response, 2nd token request, success response */
        Http::assertSentCount(4);
    }

    #[Test]
    public function it_clears_cache_on_401_response(): void
    {
        /* Get cache key first */
        $reflection = new \ReflectionClass($this->client);
        $cacheKeyMethod = $reflection->getMethod('getCacheKey');
        $cacheKeyMethod->setAccessible(true);
        $cacheKey = $cacheKeyMethod->invoke($this->client);

        /* Manually cache an old token */
        $oldExpiredToken = 'old-expired-token';
        $oldEncryptedToken = encrypt($oldExpiredToken);
        Cache::put($cacheKey, $oldEncryptedToken, 3600);

        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'refreshed-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entities' => Http::sequence()
                ->push(['error' => 'Unauthorized'], 401)
                ->push(['value' => []], 200),
        ]);

        $response = $this->client->get('/api/entities');

        $this->assertEquals(200, $response->status());

        /* Verify cache was cleared and updated */
        $encryptedCachedToken = Cache::get($cacheKey);
        $cachedToken = decrypt($encryptedCachedToken);
        $this->assertEquals('refreshed-token', $cachedToken);
    }

    #[Test]
    public function it_throws_exception_on_request_failure(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entities' => Http::response(['error' => 'Bad Request'], 400),
        ]);

        $this->expectException(RequestException::class);

        $this->client->get('/api/entities');
    }

    #[Test]
    public function it_throws_rate_limit_exception_on_429_response(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entities' => Http::response(['error' => 'Too Many Requests'], 429),
        ]);

        $this->expectException(AbacusRateLimitException::class);

        $this->client->get('/api/entities');
    }

    #[Test]
    public function it_includes_accept_json_header(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response(['value' => []], 200),
        ]);

        $this->client->get('/api/entities');

        Http::assertSent(function (Request $request) {
            return $request->hasHeader('Accept', 'application/json');
        });
    }

    #[Test]
    public function it_generates_unique_cache_key(): void
    {
        $client1 = new AbacusODataClient($this->makeCustomCredentialsProvider(
            baseUrl: 'https://api1.example.com',
            mandate: 'mandate1',
            clientId: 'client1',
        ));
        $client2 = new AbacusODataClient($this->makeCustomCredentialsProvider(
            baseUrl: 'https://api2.example.com',
            mandate: 'mandate2',
            clientId: 'client2',
        ));

        $reflection = new \ReflectionClass($client1);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);

        $key1 = $method->invoke($client1);
        $key2 = $method->invoke($client2);

        $this->assertNotEquals($key1, $key2);
        $this->assertStringStartsWith('abacus_access_token:', $key1);
        $this->assertStringStartsWith('abacus_access_token:', $key2);
    }
}
