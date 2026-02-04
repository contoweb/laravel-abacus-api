<?php

namespace Contoweb\AbacusApi\Tests\Feature;

use Contoweb\AbacusApi\AbacusService;
use Contoweb\AbacusApi\Tests\Fixtures\TestSubject;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class BatchRequestIntegrationTest extends TestCase
{
    protected AbacusService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AbacusService::class);
    }

    #[Test]
    public function it_sends_batch_request_with_model_operations(): void
    {
        Http::fake([
            '*/oauth/oauth2/*/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/$batch' => Http::response(
                $this->createBatchResponse([
                    ['value' => [['Id' => 1, 'FirstName' => 'Alice', 'ProductNumber' => 200]]],
                    ['value' => [['Id' => 2, 'FirstName' => 'Bob', 'OrderNumber' => 2222]]],
                ]),
                200,
                ['Content-Type' => 'multipart/mixed; boundary=batch_boundary']
            ),
        ]);

        $results = $this->service->batch(function () {
            return [
                TestSubject::where('ProductNumber', 'ge', 200)->get(),
                TestSubject::where('Id', 'ge', 2222)->get(),
            ];
        })->send();

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertTrue($results[1]->isSuccess());
        $this->assertEquals(200, $results[0]->status);
        $this->assertEquals(200, $results[1]->status);
        $this->assertCount(1, $results[0]->getValue());
        $this->assertEquals(200, $results[0]->body['value'][0]['ProductNumber']);
    }

    #[Test]
    public function it_sends_batch_request_with_create_operations(): void
    {
        Http::fake([
            '*/oauth/oauth2/*/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/$batch' => Http::response(
                $this->createBatchResponse([
                    ['Id' => 100, 'FirstName' => 'Alice', 'LastName' => 'Smith'],
                    ['Id' => 101, 'FirstName' => 'Bob', 'LastName' => 'Jones'],
                    ['Id' => 102, 'FirstName' => 'Charlie', 'LastName' => 'Brown'],
                ]),
                200,
                ['Content-Type' => 'multipart/mixed; boundary=batch_boundary']
            ),
        ]);

        $results = $this->service->batch(function () {
            return [
                TestSubject::create(['FirstName' => 'Alice', 'LastName' => 'Smith']),
                TestSubject::create(['FirstName' => 'Bob', 'LastName' => 'Jones']),
                TestSubject::create(['FirstName' => 'Charlie', 'LastName' => 'Brown']),
            ];
        })->send();

        $this->assertCount(3, $results);

        foreach ($results as $i => $result) {
            $this->assertTrue($result->isSuccess());
            $this->assertEquals(200, $result->status);
            $this->assertEquals(100 + $i, $result->body['Id']);
        }
    }

    #[Test]
    public function it_sends_batch_request_with_update_operations(): void
    {
        Http::fake([
            '*/oauth/oauth2/*/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/$batch' => Http::response(
                $this->createBatchResponse([
                    ['Id' => 50, 'FirstName' => 'Updated Alice'],
                    ['Id' => 51, 'FirstName' => 'Updated Bob'],
                ]),
                200,
                ['Content-Type' => 'multipart/mixed; boundary=batch_boundary']
            ),
        ]);

        $results = $this->service->batch(function () {
            return [
                TestSubject::update(50, ['FirstName' => 'Updated Alice']),
                TestSubject::update(51, ['FirstName' => 'Updated Bob']),
            ];
        })->send();

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertTrue($results[1]->isSuccess());
        $this->assertEquals('Updated Alice', $results[0]->body['FirstName']);
        $this->assertEquals('Updated Bob', $results[1]->body['FirstName']);
    }

    #[Test]
    public function it_sends_batch_request_with_delete_operations(): void
    {
        Http::fake([
            '*/oauth/oauth2/*/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/$batch' => Http::response(
                $this->createBatchResponse([
                    null, // DELETE returns no body
                    null,
                ], 204),
                200,
                ['Content-Type' => 'multipart/mixed; boundary=batch_boundary']
            ),
        ]);

        $results = $this->service->batch(function () {
            return [
                TestSubject::delete(100),
                TestSubject::delete(101),
            ];
        })->send();

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertTrue($results[1]->isSuccess());
        $this->assertEquals(204, $results[0]->status);
        $this->assertEquals(204, $results[1]->status);
    }

    #[Test]
    public function it_sends_mixed_batch_operations(): void
    {
        Http::fake([
            '*/oauth/oauth2/*/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/$batch' => Http::response(
                $this->createBatchResponse([
                    ['value' => [['Id' => 1, 'FirstName' => 'Query Result']]],
                    ['Id' => 200, 'FirstName' => 'New Subject'],
                    ['Id' => 50, 'FirstName' => 'Updated Subject'],
                    null, // DELETE
                ], [200, 201, 200, 204]),
                200,
                ['Content-Type' => 'multipart/mixed; boundary=batch_boundary']
            ),
        ]);

        $results = $this->service->batch(function () {
            return [
                TestSubject::where('Id', 'eq', 1)->get(),                     // GET
                TestSubject::create(['FirstName' => 'New Subject']),          // POST
                TestSubject::update(50, ['FirstName' => 'Updated Subject']),  // PATCH
                TestSubject::delete(100),                                     // DELETE
            ];
        })->send();

        $this->assertCount(4, $results);

        /* GET */
        $this->assertTrue($results[0]->isSuccess());
        $this->assertEquals(200, $results[0]->status);
        $this->assertArrayHasKey('value', $results[0]->body);

        /* POST */
        $this->assertTrue($results[1]->isSuccess());
        $this->assertEquals(201, $results[1]->status);
        $this->assertEquals('New Subject', $results[1]->body['FirstName']);

        /* PATCH */
        $this->assertTrue($results[2]->isSuccess());
        $this->assertEquals(200, $results[2]->status);
        $this->assertEquals('Updated Subject', $results[2]->body['FirstName']);

        /* DELETE */
        $this->assertTrue($results[3]->isSuccess());
        $this->assertEquals(204, $results[3]->status);
    }

    #[Test]
    public function it_handles_batch_with_errors(): void
    {
        Http::fake([
            '*/oauth/oauth2/*/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/$batch' => Http::response(
                $this->createBatchResponseWithErrors([
                    ['success' => true, 'status' => 200, 'body' => ['Id' => 1, 'FirstName' => 'Success']],
                    ['success' => false, 'status' => 400, 'body' => ['error' => 'Invalid data'], 'error' => 'Bad Request'],
                    ['success' => false, 'status' => 404, 'body' => ['error' => 'Not found'], 'error' => 'Not Found'],
                    ['success' => true, 'status' => 200, 'body' => ['Id' => 4, 'FirstName' => 'Success 2']],
                ]),
                200,
                ['Content-Type' => 'multipart/mixed; boundary=batch_boundary']
            ),
        ]);

        $results = $this->service->batch(function () {
            return [
                TestSubject::find(1),
                TestSubject::create(['invalid' => 'data']),
                TestSubject::find(999),
                TestSubject::find(4),
            ];
        })->send();

        $this->assertCount(4, $results);

        /* First: Success */
        $this->assertTrue($results[0]->isSuccess());
        $this->assertEquals(200, $results[0]->status);

        /* Second: Error */
        $this->assertFalse($results[1]->isSuccess());
        $this->assertEquals(400, $results[1]->status);
        $this->assertEquals('Bad Request', $results[1]->error);

        /* Third: Error */
        $this->assertFalse($results[2]->isSuccess());
        $this->assertEquals(404, $results[2]->status);

        /* Fourth: Success */
        $this->assertTrue($results[3]->isSuccess());
        $this->assertEquals(200, $results[3]->status);
    }

    #[Test]
    public function it_sends_batch_with_composite_keys(): void
    {
        Http::fake([
            '*/oauth/oauth2/*/token' => Http::response([
                'access_token' => 'fake-token',
                'expires_in' => 3600,
            ]),
            '*/$batch' => Http::response(
                $this->createBatchResponse([
                    ['BatchNumber' => '123', 'ProductId' => 456, 'Quantity' => 100],
                    ['BatchNumber' => '456', 'ProductId' => 789, 'Quantity' => 50],
                ]),
                200,
                ['Content-Type' => 'multipart/mixed; boundary=batch_boundary']
            ),
        ]);

        $results = $this->service->batch(function () {
            return [
                TestSubject::find(['BatchNumber' => '123', 'ProductId' => 456]),
                TestSubject::find(['BatchNumber' => '456', 'ProductId' => 789]),
            ];
        })->send();

        $this->assertCount(2, $results);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertTrue($results[1]->isSuccess());
        $this->assertEquals('123', $results[0]->body['BatchNumber']);
        $this->assertEquals('456', $results[1]->body['BatchNumber']);
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
