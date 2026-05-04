<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\AbacusService;
use Contoweb\AbacusApi\Batch\PendingBatchRequest;
use Contoweb\AbacusApi\Tests\Fixtures\TestSubject;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class AbacusServiceTest extends TestCase
{
    protected AbacusService $service;

    protected AbacusODataClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->client = new AbacusODataClient($this->makeCredentialsProvider());
        $this->service = new AbacusService($this->client);
    }

    #[Test]
    public function it_lists_available_entity_ids(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/1212/' => Http::response([
                'value' => [
                    ['name' => 'Subjects', 'url' => 'Subjects'],
                    ['name' => 'Invoices', 'url' => 'Invoices'],
                    ['name' => 'Products', 'url' => 'Products'],
                ],
            ], 200),
        ]);

        $result = $this->service->listEntityIds();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('value', $result);
        $this->assertCount(3, $result['value']);
        $this->assertEquals('Subjects', $result['value'][0]['name']);
        $this->assertEquals('Invoices', $result['value'][1]['name']);
        $this->assertEquals('Products', $result['value'][2]['name']);
    }

    #[Test]
    public function it_fetches_metadata(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/1212/$metadata' => Http::response(
                '<?xml version="1.0"?><edmx:Edmx xmlns:edmx="http://docs.oasis-open.org/odata/ns/edmx" Version="4.0"></edmx:Edmx>',
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);

        $result = $this->service->metadata();

        $this->assertIsString($result);
        $this->assertStringContainsString('<?xml', $result);
        $this->assertStringContainsString('edmx:Edmx', $result);
    }

    #[Test]
    public function it_caches_metadata_response(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/1212/$metadata' => Http::response(
                '<?xml version="1.0"?><edmx:Edmx>cached metadata</edmx:Edmx>',
                200
            ),
        ]);

        /* First call - fetches from API */
        $result1 = $this->service->metadata();

        /* Second call - should use cache */
        $result2 = $this->service->metadata();

        /* Third call - should still use cache */
        $result3 = $this->service->metadata();

        $this->assertEquals($result1, $result2);
        $this->assertEquals($result2, $result3);

        /* Only one metadata API call should be made (plus one token request) */
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_uses_correct_cache_key_for_metadata(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/$metadata' => Http::response('<edmx:Edmx>test</edmx:Edmx>', 200),
        ]);

        $this->service->metadata();

        $cacheKey = 'abacus_metadata_1212';
        $this->assertTrue(Cache::has($cacheKey));
    }

    #[Test]
    public function it_caches_metadata_for_one_hour(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/$metadata' => Http::response('<edmx:Edmx>cached</edmx:Edmx>', 200),
        ]);

        $this->service->metadata();

        $cacheKey = 'abacus_metadata_1212';

        /* Cache should exist */
        $this->assertTrue(Cache::has($cacheKey));

        /* Value should match */
        $this->assertEquals('<edmx:Edmx>cached</edmx:Edmx>', Cache::get($cacheKey));
    }

    #[Test]
    public function it_creates_batch_request_with_capture(): void
    {
        $batch = $this->service->newBatch();
        $batch->capture(function () {
            TestSubject::create(['FirstName' => 'Alice']);
            TestSubject::create(['FirstName' => 'Bob']);
            TestSubject::create(['FirstName' => 'Charlie']);
        });

        $this->assertInstanceOf(PendingBatchRequest::class, $batch);
        $this->assertEquals(3, $batch->count());
    }

    #[Test]
    public function it_creates_empty_batch_request(): void
    {
        $batch = $this->service->newBatch();

        $this->assertInstanceOf(PendingBatchRequest::class, $batch);
        $this->assertTrue($batch->isEmpty());
    }

    #[Test]
    public function it_creates_batch_with_mixed_operations(): void
    {
        $batch = $this->service->newBatch();
        $batch->capture(function () {
            TestSubject::paginate();
            TestSubject::create(['FirstName' => 'New']);
            TestSubject::update(123, ['FirstName' => 'Updated']);
            TestSubject::delete(456);
        });

        $this->assertInstanceOf(PendingBatchRequest::class, $batch);
        $this->assertEquals(4, $batch->count());
    }

    #[Test]
    public function it_passes_client_to_batch_request(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/$batch' => Http::response(
                $this->createBatchResponse([
                    ['Id' => 1, 'FirstName' => 'Test'],
                ]),
                200,
                ['Content-Type' => 'multipart/mixed; boundary=batch_boundary']
            ),
        ]);

        $batch = $this->service->batch(function () {
            return [TestSubject::create(['FirstName' => 'Test'])];
        });

        $results = $batch->send();

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isSuccess());
    }

    #[Test]
    public function it_handles_api_errors_gracefully(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/1212/' => Http::response([
                'error' => 'Internal Server Error',
            ], 500),
        ]);

        $this->expectException(RequestException::class);

        $this->service->listEntityIds();
    }

    #[Test]
    public function it_handles_metadata_fetch_errors(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/$metadata' => Http::response([
                'error' => 'Not Found',
            ], 404),
        ]);

        $this->expectException(RequestException::class);

        $this->service->metadata();
    }

    #[Test]
    public function it_returns_client_instance(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);

        $client = $property->getValue($this->service);

        $this->assertInstanceOf(AbacusODataClient::class, $client);
        $this->assertSame($this->client, $client);
    }

    #[Test]
    public function it_can_be_instantiated_with_custom_client(): void
    {
        $customClient = new AbacusODataClient($this->makeCustomCredentialsProvider(
            baseUrl: 'https://custom.api.com',
            mandate: 'custom-mandate',
            clientId: 'custom-client-id',
            clientSecret: 'custom-client-secret',
        ));

        $customService = new AbacusService($customClient);

        $reflection = new \ReflectionClass($customService);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);

        $client = $property->getValue($customService);

        $this->assertSame($customClient, $client);
    }

    #[Test]
    public function it_resolves_from_service_container(): void
    {
        $service = app(AbacusService::class);

        $this->assertInstanceOf(AbacusService::class, $service);
        $this->assertInstanceOf(AbacusODataClient::class, app(AbacusODataClient::class));
    }

    /**
     * Helper to create a multipart batch response
     */
    protected function createBatchResponse(array $responses, int|array $statusCodes = 200): string
    {
        $boundary = 'batch_boundary';
        $parts = [];

        foreach ($responses as $index => $responseData) {
            $statusCode = is_array($statusCodes) ? $statusCodes[$index] : $statusCodes;
            $statusText = $this->getStatusText($statusCode);
            $json = $responseData !== null ? json_encode($responseData) : '';

            $part = "Content-Type: application/http\r\n";
            $part .= "Content-Transfer-Encoding: binary\r\n";
            $part .= "\r\n";
            $part .= "HTTP/1.1 {$statusCode} {$statusText}\r\n";
            $part .= "Content-Type: application/json\r\n";
            $part .= "\r\n";
            $part .= $json."\r\n";

            $parts[] = $part;
        }

        $body = '--'.$boundary."\r\n";
        $body .= implode('--'.$boundary."\r\n", $parts);
        $body .= '--'.$boundary."--\r\n";

        return $body;
    }

    /**
     * Get HTTP status text for status code
     */
    protected function getStatusText(int $statusCode): string
    {
        return match ($statusCode) {
            200 => 'OK',
            201 => 'Created',
            204 => 'No Content',
            400 => 'Bad Request',
            404 => 'Not Found',
            500 => 'Internal Server Error',
            default => 'OK',
        };
    }
}
