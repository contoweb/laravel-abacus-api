<?php

namespace Contoweb\AbacusApi\Tests\Integration;

use Contoweb\AbacusApi\AbacusService;
use Contoweb\AbacusApi\Tests\Fixtures\TestSubject;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class AbacusServiceIntegrationTest extends TestCase
{
    protected AbacusService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AbacusService::class);
    }

    #[Test]
    public function it_performs_complete_crud_workflow_with_models(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            /* Create */
            '*/api/entity/v1/mandants/1212/Subjects' => Http::response([
                'Id' => 100,
                'FirstName' => 'John',
                'LastName' => 'Doe',
                'Email' => 'john@example.com',
            ], 201),
            /* Read, Update, Delete - use sequence for same URL */
            '*/api/entity/v1/mandants/1212/Subjects(100)*' => Http::sequence()
                ->push(['Id' => 100, 'FirstName' => 'John', 'LastName' => 'Doe'], 200)     /* Read */
                ->push(['Id' => 100, 'FirstName' => 'Jane', 'LastName' => 'Doe'], 200)     /* Update */
                ->push(null, 204),                                                            /* Delete */
        ]);

        /* Create */
        $created = TestSubject::create([
            'FirstName' => 'John',
            'LastName' => 'Doe',
            'Email' => 'john@example.com',
        ]);
        $this->assertEquals(100, $created->Id);
        $this->assertEquals('John', $created->FirstName);

        /* Read */
        $found = TestSubject::find(100);
        $this->assertEquals('John', $found->FirstName);
        $this->assertEquals('Doe', $found->LastName);

        /* Update */
        $updated = TestSubject::update(100, ['FirstName' => 'Jane']);
        $this->assertEquals('Jane', $updated->FirstName);

        /* Delete */
        TestSubject::delete(100);
        $this->assertTrue(true); // Delete returns void, just verify no exception
    }

    #[Test]
    public function it_performs_complex_query_workflow_with_models(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 1, 'FirstName' => 'Alice', 'LastName' => 'Smith', 'Age' => 30],
                    ['Id' => 2, 'FirstName' => 'Bob', 'LastName' => 'Jones', 'Age' => 35],
                ],
            ], 200),
        ]);

        $results = TestSubject::where('Age', 'gt', 25)
            ->select(['Id', 'FirstName', 'LastName', 'Age'])
            ->orderBy('FirstName', 'asc')
            ->paginate(10);

        $this->assertCount(2, $results->items());
        $this->assertEquals('Alice', $results->items()[0]->FirstName);
        $this->assertEquals(30, $results->items()[0]->Age);
        $this->assertEquals('Bob', $results->items()[1]->FirstName);
    }

    #[Test]
    public function it_handles_batch_operations_workflow(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/1212/$batch' => Http::response(
                $this->createBatchResponse([
                    ['Id' => 1, 'FirstName' => 'Alice'],
                    ['Id' => 2, 'FirstName' => 'Bob'],
                    ['value' => [['Id' => 1, 'FirstName' => 'Alice']]],
                ]),
                200,
                ['Content-Type' => 'multipart/mixed; boundary=batch_response']
            ),
        ]);

        $batch = $this->service->newBatch();
        $batch->capture(function () {
            TestSubject::create(['FirstName' => 'Alice', 'LastName' => 'Smith']);
            TestSubject::create(['FirstName' => 'Bob', 'LastName' => 'Jones']);
            TestSubject::paginate();
        });

        $results = $batch->send();

        $this->assertCount(3, $results);
        $this->assertTrue($results[0]->isSuccess());
        $this->assertEquals('Alice', $results[0]->body['FirstName']);
        $this->assertTrue($results[1]->isSuccess());
        $this->assertEquals('Bob', $results[1]->body['FirstName']);
    }

    #[Test]
    public function it_lists_available_entities(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/1212/' => Http::response([
                'value' => [
                    ['name' => 'Subjects', 'url' => 'Subjects'],
                    ['name' => 'Products', 'url' => 'Products'],
                    ['name' => 'Invoices', 'url' => 'Invoices'],
                ],
            ], 200),
        ]);

        $entities = $this->service->listEntityIds();

        $this->assertIsArray($entities);
        $this->assertArrayHasKey('value', $entities);
        $this->assertCount(3, $entities['value']);
        $this->assertEquals('Subjects', $entities['value'][0]['name']);
    }

    #[Test]
    public function it_fetches_and_caches_metadata(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/1212/$metadata' => Http::response(
                '<?xml version="1.0"?><edmx:Edmx xmlns:edmx="http://docs.oasis-open.org/odata/ns/edmx" Version="4.0"></edmx:Edmx>',
                200
            ),
        ]);

        /* First call */
        $metadata1 = $this->service->metadata();

        /* Second call - should use cache */
        $metadata2 = $this->service->metadata();

        /* Third call - should still use cache */
        $metadata3 = $this->service->metadata();

        $this->assertEquals($metadata1, $metadata2);
        $this->assertEquals($metadata2, $metadata3);

        /* Should only make one metadata request (plus one token request) */
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_handles_token_refresh_during_operations(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::sequence()
                ->push(['access_token' => 'token-1', 'expires_in' => 3600], 200)  /* Initial token */
                ->push(['access_token' => 'token-2', 'expires_in' => 3600], 200), /* Refreshed token */
            '*/api/entity/v1/mandants/1212/Subjects*' => Http::sequence()
                ->push(['value' => [['Id' => 1, 'FirstName' => 'Test']]], 200)    /* First query succeeds */
                ->push(['error' => 'Unauthorized'], 401)                           /* Second query returns 401 */
                ->push(['value' => [['Id' => 1, 'FirstName' => 'Test']]], 200),   /* Retry after token refresh */
        ]);

        /* First query */
        $result1 = TestSubject::paginate();
        $this->assertCount(1, $result1->items());

        /* Second query - will get 401 and refresh token */
        $result2 = TestSubject::paginate();
        $this->assertCount(1, $result2->items());

        /* Should have called token endpoint twice */
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/oauth/oauth2/v1/token');
        }, 2);
    }

    #[Test]
    public function it_handles_expand_relationships(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    [
                        'Id' => 1,
                        'FirstName' => 'John',
                        'Addresses' => [
                            ['Street' => 'Main St', 'City' => 'NYC'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $results = TestSubject::expand('Addresses')
            ->select(['Id', 'FirstName'])
            ->paginate();

        $this->assertCount(1, $results->items());
        $this->assertEquals('John', $results->items()[0]->FirstName);
        $this->assertIsArray($results->items()[0]->Addresses);
    }

    #[Test]
    public function it_handles_composite_key_operations(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            "*/Subjects(BatchNumber='123',ProductId=456)*" => Http::response([
                'BatchNumber' => '123',
                'ProductId' => 456,
                'Quantity' => 100,
            ], 200),
        ]);

        $compositeKey = [
            'BatchNumber' => '123',
            'ProductId' => 456,
        ];

        $result = TestSubject::find($compositeKey);

        $this->assertEquals('123', $result->BatchNumber);
        $this->assertEquals(456, $result->ProductId);
        $this->assertEquals(100, $result->Quantity);
    }

    /**
     * Helper to create a multipart batch response
     */
    protected function createBatchResponse(array $responses): string
    {
        $boundary = 'batch_response';
        $parts = [];

        foreach ($responses as $index => $responseData) {
            $json = json_encode($responseData);
            $statusCode = 200;

            $part = "Content-Type: application/http\r\n";
            $part .= "Content-Transfer-Encoding: binary\r\n";
            $part .= "\r\n";
            $part .= "HTTP/1.1 {$statusCode} OK\r\n";
            $part .= "Content-Type: application/json\r\n";
            $part .= "\r\n";
            $part .= $json;

            $parts[] = $part;
        }

        $body = '--'.$boundary."\r\n";
        $body .= implode("\r\n--".$boundary."\r\n", $parts);
        $body .= "\r\n--".$boundary."--\r\n";

        return $body;
    }
}
