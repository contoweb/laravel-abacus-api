<?php

namespace Contoweb\AbacusApi\Tests\Feature;

use Contoweb\AbacusApi\Tests\TestCase;
use Contoweb\AbacusApi\AbacusClient;
use Contoweb\AbacusApi\AbacusService;
use Illuminate\Support\Facades\Http;

class AbacusServiceIntegrationTest extends TestCase
{
    protected AbacusService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $client = new AbacusClient();
        $this->service = new AbacusService($client);
    }

    /** @test */
    public function it_performs_complete_crud_workflow(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            /* Create */
            '*/api/entity/v1/mandants/test-mandate/Subjects' => Http::response([
                'Id' => 100,
                'Name' => 'Integration Test',
                'Email' => 'integration@test.com',
            ], 201),
            /* Read, Update, Delete - use sequence for same URL */
            '*/api/entity/v1/mandants/test-mandate/Subjects(100)' => Http::sequence()
                ->push(['Id' => 100, 'Name' => 'Integration Test', 'Email' => 'integration@test.com'], 200) /* Read */
                ->push(['Id' => 100, 'Name' => 'Updated Name', 'Email' => 'integration@test.com'], 200)      /* Update */
                ->push(null, 204),                                                                              /* Delete */
        ]);

        /* Create */
        $created = $this->service->create('Subjects', [
            'Name' => 'Integration Test',
            'Email' => 'integration@test.com',
        ]);
        $this->assertEquals(100, $created['Id']);

        /* Read */
        $found = $this->service->find('Subjects', 100);
        $this->assertEquals('Integration Test', $found['Name']);

        /* Update */
        $updated = $this->service->update('Subjects', 100, ['Name' => 'Updated Name']);
        $this->assertEquals('Updated Name', $updated['Name']);

        /* Delete */
        $deleted = $this->service->delete('Subjects', 100);
        $this->assertTrue($deleted);
    }

    /** @test */
    public function it_handles_token_refresh_during_workflow(): void
    {
        Http::fake([
            '*/oauth/token' => Http::sequence()
                ->push(['access_token' => 'token-1', 'expires_in' => 3600], 200)  /* Initial token */
                ->push(['access_token' => 'token-2', 'expires_in' => 3600], 200), /* Refreshed token */
            '*/api/entity/v1/mandants/test-mandate/Subjects*' => Http::sequence()
                ->push(['value' => [['Id' => 1, 'Name' => 'Test']]], 200)  /* First query succeeds */
                ->push(['error' => 'Unauthorized'], 401)                    /* Second query returns 401 */
                ->push(['value' => [['Id' => 1, 'Name' => 'Test']]], 200), /* Retry after token refresh */
        ]);

        /* First query */
        $result1 = $this->service->query('Subjects');
        $this->assertIsArray($result1);

        /* Second query - will get 401 and refresh token */
        $result2 = $this->service->query('Subjects');
        $this->assertIsArray($result2);
    }

    /** @test */
    public function it_performs_complex_query_workflow(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 1, 'Name' => 'Active User 1', 'Status' => 'Active'],
                    ['Id' => 2, 'Name' => 'Active User 2', 'Status' => 'Active'],
                ],
            ], 200),
        ]);

        $result = $this->service->query('Subjects', [
            '$filter' => "Status eq 'Active'",
            '$select' => 'Id,Name,Status',
            '$orderby' => 'Name asc',
            '$top' => 10,
        ]);

        $this->assertArrayHasKey('value', $result);
        $this->assertCount(2, $result['value']);
        $this->assertEquals('Active', $result['value'][0]['Status']);
    }

    /** @test */
    public function it_handles_pagination_workflow(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects*' => Http::response([
                'value' => [
                    ['Id' => 1, 'Name' => 'Page 1 Item 1'],
                ],
                '@odata.nextLink' => 'https://api.example.com/page2',
            ], 200),
            'https://api.example.com/page2' => Http::response([
                'value' => [
                    ['Id' => 2, 'Name' => 'Page 2 Item 1'],
                ],
                '@odata.nextLink' => 'https://api.example.com/page3',
            ], 200),
            'https://api.example.com/page3' => Http::response([
                'value' => [
                    ['Id' => 3, 'Name' => 'Page 3 Item 1'],
                ],
            ], 200),
        ]);

        /* Get first page */
        $page1 = $this->service->queryWithMetadata('Subjects');
        $this->assertEquals(1, $page1['value'][0]['Id']);
        $this->assertArrayHasKey('@odata.nextLink', $page1);

        /* Get second page */
        $page2 = $this->service->getNextPage($page1['@odata.nextLink']);
        $this->assertEquals(2, $page2['value'][0]['Id']);

        /* Get third page */
        $page3 = $this->service->getNextPage($page2['@odata.nextLink']);
        $this->assertEquals(3, $page3['value'][0]['Id']);
        $this->assertArrayNotHasKey('@odata.nextLink', $page3);
    }

    /** @test */
    public function it_handles_property_access_workflow(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(42)/Name' => Http::response([
                'value' => 'John Doe',
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(42)/Email' => Http::response([
                'value' => 'john@example.com',
            ], 200),
        ]);

        $name = $this->service->findProperty('Subjects', 42, 'Name');
        $email = $this->service->findProperty('Subjects', 42, 'Email');

        $this->assertEquals(['value' => 'John Doe'], $name);
        $this->assertEquals(['value' => 'john@example.com'], $email);
    }

    /** @test */
    public function it_handles_metadata_caching_workflow(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/$metadata' => Http::response(
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

    /** @test */
    public function it_handles_batch_operations(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects' => Http::sequence()
                ->push(['Id' => 1, 'Name' => 'Subject 1'], 201)
                ->push(['Id' => 2, 'Name' => 'Subject 2'], 201)
                ->push(['Id' => 3, 'Name' => 'Subject 3'], 201),
        ]);

        $created = [];
        for ($i = 1; $i <= 3; $i++) {
            $created[] = $this->service->create('Subjects', ['Name' => "Subject {$i}"]);
        }

        $this->assertCount(3, $created);
        $this->assertEquals('Subject 1', $created[0]['Name']);
        $this->assertEquals('Subject 3', $created[2]['Name']);
    }
}
