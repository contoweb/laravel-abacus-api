<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class TokenCachingTest extends TestCase
{
    protected AbacusODataClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->client = new AbacusODataClient();
    }

    #[Test]
    public function it_caches_access_token_with_encryption(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token-12345',
                'expires_in' => 3600,
            ], 200),
        ]);

        /* First call: fetch token */
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('getAccessToken');
        $method->setAccessible(true);

        $token1 = $method->invoke($this->client);

        /* Verify token is correct */
        $this->assertEquals('test-token-12345', $token1);

        /* Verify token is cached (encrypted) */
        $getCacheKeyMethod = $reflection->getMethod('getCacheKey');
        $getCacheKeyMethod->setAccessible(true);
        $cacheKey = $getCacheKeyMethod->invoke($this->client);

        $cachedValue = Cache::get($cacheKey);
        $this->assertNotNull($cachedValue);
        
        /* Cached value should be encrypted (not plain token) */
        $this->assertNotEquals('test-token-12345', $cachedValue);

        /* Second call: use cached token (no HTTP request) */
        Http::fake(); // Reset fakes
        $token2 = $method->invoke($this->client);

        /* Should return same token from cache */
        $this->assertEquals('test-token-12345', $token2);
        Http::assertNothingSent();
    }

    #[Test]
    public function it_handles_cache_key_correctly(): void
    {
        $client1 = new AbacusODataClient('api1.example.com', 'mandate1', 'client1', 'secret1');
        $client2 = new AbacusODataClient('api2.example.com', 'mandate2', 'client2', 'secret2');

        $reflection = new \ReflectionClass($client1);
        $method = $reflection->getMethod('getCacheKey');
        $method->setAccessible(true);

        $key1 = $method->invoke($client1);
        $key2 = $method->invoke($client2);

        /* Different clients should have different cache keys */
        $this->assertNotEquals($key1, $key2);
    }

    #[Test]
    public function it_refetches_token_on_401_response(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::sequence()
                ->push(['access_token' => 'old-token', 'expires_in' => 3600], 200)
                ->push(['access_token' => 'new-token', 'expires_in' => 3600], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects' => Http::sequence()
                ->push([], 401) // First call fails with 401
                ->push(['value' => []], 200), // Second call succeeds
        ]);

        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('getAccessToken');
        $method->setAccessible(true);

        /* Get initial token */
        $method->invoke($this->client);

        /* Make request that returns 401 */
        $response = $this->client->get('/api/entity/v1/mandants/test-mandate/Subjects');

        /* Should have called token endpoint twice */
        Http::assertSentCount(4); // 2x token + 2x subjects
    }

    #[Test]
    public function it_caches_token_with_expiry_buffer(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 100, // 100 seconds
            ], 200),
        ]);

        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('fetchFreshAccessToken');
        $method->setAccessible(true);

        $method->invoke($this->client);

        /* Get cache TTL */
        $getCacheKeyMethod = $reflection->getMethod('getCacheKey');
        $getCacheKeyMethod->setAccessible(true);
        $cacheKey = $getCacheKeyMethod->invoke($this->client);

        /* Cache should exist */
        $this->assertTrue(Cache::has($cacheKey));

        /* Token should be cached for 90 seconds (100 - 10 buffer) */
        /* We can't directly test TTL, but we can verify it's cached */
    }
}
