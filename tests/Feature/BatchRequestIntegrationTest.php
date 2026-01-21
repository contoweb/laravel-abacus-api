<?php

namespace Contoweb\AbacusApi\Tests\Feature;

use Contoweb\AbacusApi\AbacusClient;
use Contoweb\AbacusApi\AbacusQueryBuilder;
use Contoweb\AbacusApi\AbacusService;
use Contoweb\AbacusApi\Facades\Abacus;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class BatchRequestIntegrationTest extends TestCase
{
    protected AbacusService $service;
    protected AbacusClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new AbacusClient();
        $this->service = new AbacusService($this->client);
    }

    #[Test]
    public function it_sends_batch_request_with_query_builder(): void
    {
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
                '{"value":[{"Id":1,"ProductNumber":200}]}' . "\r\n" .
                "--batch_boundary\r\n" .
                "Content-Type: application/http\r\n" .
                "Content-Transfer-Encoding: binary\r\n" .
                "\r\n" .
                "HTTP/1.1 200 OK\r\n" .
                "Content-Type: application/json\r\n" .
                "\r\n" .
                '{"value":[{"Id":2,"OrderNumber":2222}]}' . "\r\n" .
                "--batch_boundary--\r\n"
            ),
        ]);

        $productQuery = new AbacusQueryBuilder($this->service, 'Products');
        $productQuery->where('ProductNumber', 'ge', 200);

        $orderQuery = new AbacusQueryBuilder($this->service, 'SalesOrders');
        $orderQuery->where('Id', 'ge', 2222);

        $results = $this->service->batch()
            ->addRequest($productQuery->prepareForBatch())
            ->addRequest($orderQuery->prepareForBatch())
            ->send();

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertTrue($results[1]['success']);
        $this->assertEquals(200, $results[0]['status']);
        $this->assertEquals(200, $results[1]['status']);
    }

    #[Test]
    public function it_sends_batch_request_with_raw_arrays(): void
    {
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
                '{"ProductPricingResponse":{"TotalPrice":129.90}}' . "\r\n" .
                "--batch_boundary--\r\n"
            ),
        ]);

        $results = $this->service->batch()
            ->addRequest([
                'method' => 'POST',
                'path' => '/api/entity/v1/mandants/9055/FindProductPrice',
                'body' => [
                    'ProductPricingRequest' => [
                        'RequestKey' => 'test-123',
                        'Currency' => 'CHF',
                        'CustomerNumber' => 29517,
                    ],
                ],
            ])
            ->send();

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertEquals(129.90, $results[0]['body']['ProductPricingResponse']['TotalPrice']);
    }

    #[Test]
    public function it_handles_mixed_success_and_error_responses(): void
    {
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
                "--batch_boundary\r\n" .
                "Content-Type: application/http\r\n" .
                "Content-Transfer-Encoding: binary\r\n" .
                "\r\n" .
                "HTTP/1.1 400 Bad Request\r\n" .
                "Content-Type: application/json\r\n" .
                "\r\n" .
                '{"error":"Invalid ProductId"}' . "\r\n" .
                "--batch_boundary--\r\n"
            ),
        ]);

        $results = $this->service->batch()
            ->addRequest(['method' => 'GET', 'path' => '/test1', 'body' => null])
            ->addRequest(['method' => 'POST', 'path' => '/test2', 'body' => ['invalid' => 'data']])
            ->send();

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertFalse($results[1]['success']);
        $this->assertEquals(200, $results[0]['status']);
        $this->assertEquals(400, $results[1]['status']);
        $this->assertEquals('Bad Request', $results[1]['error']);
    }

    #[Test]
    public function it_sends_batch_request_using_facade(): void
    {
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
                '{"Id":999}' . "\r\n" .
                "--batch_boundary--\r\n"
            ),
        ]);

        $results = Abacus::batch()
            ->addRequest(['method' => 'GET', 'path' => '/test', 'body' => null])
            ->send();

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
    }
}
