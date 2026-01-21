<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\AbacusClient;
use Contoweb\AbacusApi\BatchRequest;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class BatchRequestTest extends TestCase
{
    protected AbacusClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new AbacusClient();
    }

    #[Test]
    public function it_adds_single_request(): void
    {
        $batch = new BatchRequest($this->client);

        $batch->addRequest([
            'method' => 'GET',
            'path' => '/api/entity/v1/mandants/9055/Products',
            'body' => null,
        ]);

        $this->assertEquals(1, $batch->count());
    }

    #[Test]
    public function it_adds_multiple_requests(): void
    {
        $batch = new BatchRequest($this->client);

        $batch
            ->addRequest(['method' => 'GET', 'path' => '/test1', 'body' => null])
            ->addRequest(['method' => 'POST', 'path' => '/test2', 'body' => ['data' => 'test']]);

        $this->assertEquals(2, $batch->count());
    }

    #[Test]
    public function it_throws_exception_when_method_is_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Request must contain "method" and "path" keys');

        $batch = new BatchRequest($this->client);
        $batch->addRequest(['path' => '/test']);
    }

    #[Test]
    public function it_throws_exception_when_path_is_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Request must contain "method" and "path" keys');

        $batch = new BatchRequest($this->client);
        $batch->addRequest(['method' => 'GET']);
    }

    #[Test]
    public function it_automatically_adds_body_key_if_missing(): void
    {
        $batch = new BatchRequest($this->client);
        $batch->addRequest(['method' => 'GET', 'path' => '/test']);

        $requests = $batch->getRequests();
        $this->assertArrayHasKey('body', $requests[0]);
        $this->assertNull($requests[0]['body']);
    }

    #[Test]
    public function it_throws_exception_when_sending_empty_batch(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No requests added to batch');

        $batch = new BatchRequest($this->client);
        $batch->send();
    }

    #[Test]
    public function it_sends_batch_request_successfully(): void
    {
        /* Mock the batch response */
        Http::fake([
            '*/oauth/oauth2/*/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/$batch' => Http::response(
                "--batch_boundary\r\n" .
                "Content-Type: application/http\r\n" .
                "Content-Transfer-Encoding: binary\r\n" .
                "\r\n" .
                "HTTP/1.1 200 OK\r\n" .
                "Content-Type: application/json\r\n" .
                "\r\n" .
                '{"Id":123}' . "\r\n" .
                "--batch_boundary--\r\n"
            ),
        ]);

        $batch = new BatchRequest($this->client);
        $batch->addRequest([
            'method' => 'GET',
            'path' => '/api/entity/v1/mandants/9055/Products',
            'body' => null,
        ]);

        $results = $batch->send();

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertEquals(200, $results[0]['status']);
        $this->assertEquals(['Id' => 123], $results[0]['body']);
    }

    #[Test]
    public function it_returns_requests_array(): void
    {
        $batch = new BatchRequest($this->client);
        $batch->addRequest(['method' => 'GET', 'path' => '/test', 'body' => null]);

        $requests = $batch->getRequests();

        $this->assertIsArray($requests);
        $this->assertCount(1, $requests);
        $this->assertEquals('GET', $requests[0]['method']);
        $this->assertEquals('/test', $requests[0]['path']);
    }
}
