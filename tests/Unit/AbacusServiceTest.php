<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\Tests\TestCase;
use Contoweb\AbacusApi\AbacusClient;
use Contoweb\AbacusApi\AbacusService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class AbacusServiceTest extends TestCase
{
    protected AbacusService $service;
    protected AbacusClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->client = new AbacusClient();
        $this->service = new AbacusService($this->client);
    }

    #[Test]
    public function it_queries_entities_with_odata_parameters(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects*' => Http::response([
                'value' => [
                    ['Id' => 1, 'Name' => 'Subject 1'],
                    ['Id' => 2, 'Name' => 'Subject 2'],
                ],
            ], 200),
        ]);

        $result = $this->service->query('Subjects', ['$top' => 10, '$filter' => "Name eq 'Test'"]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('value', $result);
        $this->assertCount(2, $result['value']);
    }

    #[Test]
    public function it_queries_entities_without_parameters(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Invoices' => Http::response([
                'value' => [
                    ['Id' => 100, 'Amount' => 250.00],
                ],
            ], 200),
        ]);

        $result = $this->service->query('Invoices');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('value', $result);
    }

    #[Test]
    public function it_queries_with_metadata_response(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                '@odata.context' => 'https://api.example.com/$metadata#Subjects',
                '@odata.nextLink' => 'https://api.example.com/next-page',
                'value' => [
                    ['Id' => 1, 'Name' => 'Test'],
                ],
            ], 200),
        ]);

        $result = $this->service->queryWithMetadata('Subjects');

        $this->assertArrayHasKey('@odata.context', $result);
        $this->assertArrayHasKey('@odata.nextLink', $result);
        $this->assertArrayHasKey('value', $result);
    }

    #[Test]
    public function it_fetches_next_page_via_next_link(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            'https://api.example.com/next-page*' => Http::response([
                'value' => [
                    ['Id' => 101, 'Name' => 'Next Page Item'],
                ],
            ], 200),
        ]);

        $result = $this->service->getNextPage('https://api.example.com/next-page?$skip=100');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('value', $result);
        $this->assertEquals('Next Page Item', $result['value'][0]['Name']);
    }

    #[Test]
    public function it_finds_entity_by_id(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(42)' => Http::response([
                'Id' => 42,
                'Name' => 'John Doe',
                'Email' => 'john@example.com',
            ], 200),
        ]);

        $result = $this->service->find('Subjects', 42);

        $this->assertIsArray($result);
        $this->assertEquals(42, $result['Id']);
        $this->assertEquals('John Doe', $result['Name']);
    }

    #[Test]
    public function it_finds_entity_with_string_id(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Documents(guid-123)*' => Http::response([
                'Id' => 'guid-123',
                'Title' => 'Document Title',
            ], 200),
        ]);

        $result = $this->service->find('Documents', 'guid-123');

        $this->assertEquals('guid-123', $result['Id']);
        $this->assertEquals('Document Title', $result['Title']);
    }

    #[Test]
    public function it_finds_entity_property(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(42)/LastName' => Http::response([
                'value' => 'Doe',
            ], 200),
        ]);

        $result = $this->service->findProperty('Subjects', 42, 'LastName');

        $this->assertEquals(['value' => 'Doe'], $result);
    }

    #[Test]
    public function it_creates_entity(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects' => Http::response([
                'Id' => 100,
                'Name' => 'New Subject',
                'Email' => 'new@example.com',
            ], 201),
        ]);

        $data = [
            'Name' => 'New Subject',
            'Email' => 'new@example.com',
        ];

        $result = $this->service->create('Subjects', $data);

        $this->assertIsArray($result);
        $this->assertEquals(100, $result['Id']);
        $this->assertEquals('New Subject', $result['Name']);
    }

    #[Test]
    public function it_updates_entity_with_patch(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(42)' => Http::response([
                'Id' => 42,
                'Name' => 'Updated Name',
                'Email' => 'updated@example.com',
            ], 200),
        ]);

        $data = ['Name' => 'Updated Name'];

        $result = $this->service->update('Subjects', 42, $data);

        $this->assertEquals('Updated Name', $result['Name']);
    }

    #[Test]
    public function it_replaces_entity_with_put(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(42)' => Http::response([
                'Id' => 42,
                'Name' => 'Completely New',
                'Email' => 'new@example.com',
            ], 200),
        ]);

        $data = [
            'Name' => 'Completely New',
            'Email' => 'new@example.com',
        ];

        $result = $this->service->replace('Subjects', 42, $data);

        $this->assertEquals('Completely New', $result['Name']);
    }

    #[Test]
    public function it_deletes_entity(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(42)' => Http::response(null, 204),
        ]);

        $result = $this->service->delete('Subjects', 42);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_lists_entity_ids(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/' => Http::response([
                'value' => [
                    ['name' => 'Subjects', 'url' => 'Subjects'],
                    ['name' => 'Invoices', 'url' => 'Invoices'],
                ],
            ], 200),
        ]);

        $result = $this->service->listEntityIds();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('value', $result);
        $this->assertCount(2, $result['value']);
    }

    #[Test]
    public function it_fetches_metadata(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/$metadata' => Http::response(
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
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/$metadata' => Http::response(
                '<metadata>cached</metadata>',
                200
            ),
        ]);

        /* First call - fetches from API */
        $result1 = $this->service->metadata();

        /* Second call - should use cache */
        $result2 = $this->service->metadata();

        $this->assertEquals($result1, $result2);

        /* Only one API call should be made (plus token request) */
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_uses_correct_cache_key_for_metadata(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response('<metadata>test</metadata>', 200),
        ]);

        $this->service->metadata();

        $cacheKey = 'abacus_metadata_test-mandate';
        $this->assertTrue(Cache::has($cacheKey));
    }

    #[Test]
    public function it_handles_empty_query_parameters(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response(['value' => []], 200),
        ]);

        $result = $this->service->query('Subjects', []);

        $this->assertIsArray($result);
        $this->assertEquals(['value' => []], $result);
    }
}
