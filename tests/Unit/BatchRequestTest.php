<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\Batch\BatchRequest;
use Contoweb\AbacusApi\Batch\BatchRequestItem;
use Contoweb\AbacusApi\Tests\Fixtures\TestSubject;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class BatchRequestTest extends TestCase
{
    protected AbacusODataClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new AbacusODataClient($this->makeCredentialsProvider());
    }

    #[Test]
    public function it_can_be_instantiated_with_no_requests(): void
    {
        $batch = new BatchRequest($this->client);

        $this->assertInstanceOf(BatchRequest::class, $batch);
        $this->assertCount(0, $batch->requests);
    }

    #[Test]
    public function it_can_be_instantiated_with_single_request(): void
    {
        $item = new BatchRequestItem(
            TestSubject::class,
            'GET',
            '/api/entity/v1/mandants/test-mandate/Subjects',
            null
        );

        $batch = new BatchRequest($this->client, $item);

        $this->assertCount(1, $batch->requests);
        $this->assertSame($item, $batch->requests[0]);
    }

    #[Test]
    public function it_can_be_instantiated_with_multiple_requests(): void
    {
        $item1 = new BatchRequestItem(TestSubject::class, 'GET', '/path1', null);
        $item2 = new BatchRequestItem(TestSubject::class, 'POST', '/path2', ['data' => 'test']);
        $item3 = new BatchRequestItem(TestSubject::class, 'PATCH', '/path3', ['update' => 'value']);

        $batch = new BatchRequest($this->client, $item1, $item2, $item3);

        $this->assertCount(3, $batch->requests);
        $this->assertEquals('GET', $batch->requests[0]->method);
        $this->assertEquals('POST', $batch->requests[1]->method);
        $this->assertEquals('PATCH', $batch->requests[2]->method);
    }

    #[Test]
    public function it_stores_requests_as_public_property(): void
    {
        $item = new BatchRequestItem(TestSubject::class, 'GET', '/test', null);
        $batch = new BatchRequest($this->client, $item);

        $this->assertIsArray($batch->requests);
        $this->assertContainsOnlyInstancesOf(BatchRequestItem::class, $batch->requests);
    }

    #[Test]
    public function it_sends_batch_request_successfully(): void
    {
        Http::fake([
            '*/oauth/oauth2/*/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/$batch' => Http::response(
                $this->createBatchResponse([
                    ['Id' => 123, 'FirstName' => 'Test'],
                ]),
                200,
                ['Content-Type' => 'multipart/mixed; boundary=batch_boundary']
            ),
        ]);

        $item = new BatchRequestItem(
            TestSubject::class,
            'GET',
            '/api/entity/v1/mandants/test-mandate/Subjects',
            null
        );

        $batch = new BatchRequest($this->client, $item);
        $results = $batch->send();

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertEquals(200, $results[0]->status);
        $this->assertEquals(['Id' => 123, 'FirstName' => 'Test'], $results[0]->body);
    }

    #[Test]
    public function it_sends_multiple_requests_in_batch(): void
    {
        Http::fake([
            '*/oauth/oauth2/*/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/$batch' => Http::response(
                $this->createBatchResponse([
                    ['Id' => 1, 'FirstName' => 'First'],
                    ['Id' => 2, 'FirstName' => 'Second'],
                    ['Id' => 3, 'FirstName' => 'Third'],
                ]),
                200,
                ['Content-Type' => 'multipart/mixed; boundary=batch_boundary']
            ),
        ]);

        $item1 = new BatchRequestItem(TestSubject::class, 'GET', '/subjects/1', null);
        $item2 = new BatchRequestItem(TestSubject::class, 'GET', '/subjects/2', null);
        $item3 = new BatchRequestItem(TestSubject::class, 'GET', '/subjects/3', null);

        $batch = new BatchRequest($this->client, $item1, $item2, $item3);
        $results = $batch->send();

        $this->assertCount(3, $results);
        $this->assertEquals('First', $results[0]->body['FirstName']);
        $this->assertEquals('Second', $results[1]->body['FirstName']);
        $this->assertEquals('Third', $results[2]->body['FirstName']);
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
                $this->createBatchResponseWithErrors([
                    ['success' => true, 'status' => 200, 'body' => ['Id' => 1]],
                    ['success' => false, 'status' => 400, 'body' => ['error' => 'Bad Request'], 'error' => 'Bad Request'],
                    ['success' => true, 'status' => 201, 'body' => ['Id' => 2]],
                ]),
                200,
                ['Content-Type' => 'multipart/mixed; boundary=batch_boundary']
            ),
        ]);

        $item1 = new BatchRequestItem(TestSubject::class, 'GET', '/path1', null);
        $item2 = new BatchRequestItem(TestSubject::class, 'POST', '/path2', ['invalid' => 'data']);
        $item3 = new BatchRequestItem(TestSubject::class, 'POST', '/path3', ['valid' => 'data']);

        $batch = new BatchRequest($this->client, $item1, $item2, $item3);
        $results = $batch->send();

        $this->assertCount(3, $results);

        /* First: Success */
        $this->assertTrue($results[0]->isSuccess());
        $this->assertEquals(200, $results[0]->status);

        /* Second: Error */
        $this->assertFalse($results[1]->isSuccess());
        $this->assertEquals(400, $results[1]->status);
        $this->assertEquals('Bad Request', $results[1]->error);

        /* Third: Success */
        $this->assertTrue($results[2]->isSuccess());
        $this->assertEquals(201, $results[2]->status);
    }

    #[Test]
    public function it_handles_empty_body_responses(): void
    {
        Http::fake([
            '*/oauth/oauth2/*/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/$batch' => Http::response(
                $this->createBatchResponse([null], 204),
                200,
                ['Content-Type' => 'multipart/mixed; boundary=batch_boundary']
            ),
        ]);

        $item = new BatchRequestItem(TestSubject::class, 'DELETE', '/subjects/123', null);
        $batch = new BatchRequest($this->client, $item);
        $results = $batch->send();

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertEquals(204, $results[0]->status);
        $this->assertNull($results[0]->body);
    }

    #[Test]
    public function it_returns_collection_of_batch_response_dtos(): void
    {
        Http::fake([
            '*/oauth/oauth2/*/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/$batch' => Http::response(
                $this->createBatchResponse([
                    ['Id' => 1],
                    ['Id' => 2],
                ]),
                200,
                ['Content-Type' => 'multipart/mixed; boundary=batch_boundary']
            ),
        ]);

        $item1 = new BatchRequestItem(TestSubject::class, 'GET', '/path1', null);
        $item2 = new BatchRequestItem(TestSubject::class, 'GET', '/path2', null);

        $batch = new BatchRequest($this->client, $item1, $item2);
        $results = $batch->send();

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $results);
        $this->assertCount(2, $results);
        $this->assertContainsOnlyInstancesOf(\Contoweb\AbacusApi\DataTransferObjects\BatchResponseDto::class, $results);
    }

    #[Test]
    public function it_handles_batch_responses_with_value_property(): void
    {
        Http::fake([
            '*/oauth/oauth2/*/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/$batch' => Http::response(
                $this->createBatchResponse([
                    ['value' => [['Id' => 1], ['Id' => 2]]],
                ]),
                200,
                ['Content-Type' => 'multipart/mixed; boundary=batch_boundary']
            ),
        ]);

        $item = new BatchRequestItem(TestSubject::class, 'GET', '/subjects', null);
        $batch = new BatchRequest($this->client, $item);
        $results = $batch->send();

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('value', $results[0]->body);
        $this->assertCount(2, $results[0]->getValue());
    }

    #[Test]
    public function it_preserves_request_order_in_response(): void
    {
        Http::fake([
            '*/oauth/oauth2/*/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/$batch' => Http::response(
                $this->createBatchResponse([
                    ['Id' => 100, 'Order' => 1],
                    ['Id' => 200, 'Order' => 2],
                    ['Id' => 300, 'Order' => 3],
                ]),
                200,
                ['Content-Type' => 'multipart/mixed; boundary=batch_boundary']
            ),
        ]);

        $item1 = new BatchRequestItem(TestSubject::class, 'GET', '/1', null);
        $item2 = new BatchRequestItem(TestSubject::class, 'GET', '/2', null);
        $item3 = new BatchRequestItem(TestSubject::class, 'GET', '/3', null);

        $batch = new BatchRequest($this->client, $item1, $item2, $item3);
        $results = $batch->send();

        $this->assertEquals(1, $results[0]->body['Order']);
        $this->assertEquals(2, $results[1]->body['Order']);
        $this->assertEquals(3, $results[2]->body['Order']);
    }

    #[Test]
    public function it_works_with_model_batch_methods(): void
    {
        Http::fake([
            '*/oauth/oauth2/*/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/$batch' => Http::response(
                $this->createBatchResponse([
                    ['Id' => 100, 'FirstName' => 'Created'],
                ]),
                200,
                ['Content-Type' => 'multipart/mixed; boundary=batch_boundary']
            ),
        ]);

        $item = new BatchRequestItem(
            TestSubject::class,
            'POST',
            '/api/entity/v1/mandants/test-mandate/Subjects',
            ['FirstName' => 'Created']
        );

        $batch = new BatchRequest($this->client, $item);

        $results = $batch->send();

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertEquals('Created', $results[0]->body['FirstName']);
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
     * Helper to create batch response with specific error states
     */
    protected function createBatchResponseWithErrors(array $responses): string
    {
        $boundary = 'batch_boundary';
        $parts = [];

        foreach ($responses as $response) {
            $statusCode = $response['status'];
            $statusText = $response['error'] ?? $this->getStatusText($statusCode);
            $json = json_encode($response['body']);

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
