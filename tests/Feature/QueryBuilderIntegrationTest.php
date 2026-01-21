<?php

namespace Contoweb\AbacusApi\Tests\Feature;

use Contoweb\AbacusApi\Tests\TestCase;
use Contoweb\AbacusApi\AbacusClient;
use Contoweb\AbacusApi\AbacusQueryBuilder;
use Contoweb\AbacusApi\AbacusService;
use Contoweb\AbacusApi\Enums\ODataOperator;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class QueryBuilderIntegrationTest extends TestCase
{
    protected AbacusService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $client = new AbacusClient();
        $this->service = new AbacusService($client);
    }

    #[Test]
    public function it_executes_complex_filtered_query(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token'=> Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 1, 'Name' => 'John Doe', 'Age' => 25, 'IsActive' => true],
                    ['Id' => 2, 'Name' => 'Jane Smith', 'Age' => 30, 'IsActive' => true],
                ],
            ], 200),
        ]);

        $builder = new AbacusQueryBuilder($this->service, 'Subjects');
        $results = $builder
            ->where('IsActive', ODataOperator::EQUALS, true)
            ->where('Age', ODataOperator::GREATER_THAN, 18)
            ->select('Id', 'Name', 'Age')
            ->orderBy('Name', 'asc')
            ->top(10)
            ->getFirstPage();

        $this->assertCount(2, $results);
        $this->assertEquals('John Doe', $results[0]['Name']);
    }

    #[Test]
    public function it_follows_pagination_automatically(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token'=> Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects*' => Http::response([
                'value' => [
                    ['Id' => 1, 'Name' => 'Item 1'],
                    ['Id' => 2, 'Name' => 'Item 2'],
                ],
                '@odata.nextLink' => 'https://api.example.com/page2',
            ], 200),
            'https://api.example.com/page2' => Http::response([
                'value' => [
                    ['Id' => 3, 'Name' => 'Item 3'],
                    ['Id' => 4, 'Name' => 'Item 4'],
                ],
                '@odata.nextLink' => 'https://api.example.com/page3',
            ], 200),
            'https://api.example.com/page3' => Http::response([
                'value' => [
                    ['Id' => 5, 'Name' => 'Item 5'],
                ],
            ], 200),
        ]);

        $builder = new AbacusQueryBuilder($this->service, 'Subjects');
        $results = $builder->get();

        /* Should automatically fetch all 3 pages */
        $this->assertCount(5, $results);
        $this->assertEquals('Item 1', $results[0]['Name']);
        $this->assertEquals('Item 5', $results[4]['Name']);
    }

    #[Test]
    public function it_executes_query_with_expand(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token'=> Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    [
                        'Id' => 1,
                        'Name' => 'Subject',
                        'Addresses' => [
                            ['Street' => '123 Main St', 'City' => 'Springfield'],
                        ],
                        'Contacts' => [
                            ['Type' => 'Email', 'Value' => 'test@example.com'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $builder = new AbacusQueryBuilder($this->service, 'Subjects');
        $results = $builder
            ->expand('Addresses', 'Contacts')
            ->getFirstPage();

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('Addresses', $results[0]);
        $this->assertArrayHasKey('Contacts', $results[0]);
    }

    #[Test]
    public function it_chains_multiple_conditions(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token'=> Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 1, 'Name' => 'Match', 'Age' => 25, 'Status' => 'Active', 'Score' => 85],
                ],
            ], 200),
        ]);

        $builder = new AbacusQueryBuilder($this->service, 'Subjects');
        $results = $builder
            ->where('Status', 'eq', 'Active')
            ->where('Age', 'ge', 21)
            ->where('Age', 'le', 65)
            ->where('Score', 'gt', 80)
            ->select('Id', 'Name', 'Age', 'Status', 'Score')
            ->orderBy('Score', 'desc')
            ->top(5)
            ->getFirstPage();

        $this->assertCount(1, $results);
        $this->assertEquals('Match', $results[0]['Name']);

        Http::assertSent(function ($request) {
            $url = $request->url();
            /* URL parameters are encoded, so check for the filter parameter */
            return str_contains($url, '%24filter') &&
                   str_contains($url, 'Status') &&
                   str_contains($url, 'Age') &&
                   str_contains($url, 'Score');
        });
    }

    #[Test]
    public function it_handles_first_result(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token'=> Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 42, 'Name' => 'First Item'],
                ],
            ], 200),
        ]);

        $builder = new AbacusQueryBuilder($this->service, 'Subjects');
        $result = $builder
            ->where('IsActive', 'eq', true)
            ->orderBy('CreatedAt', 'desc')
            ->first();

        $this->assertIsArray($result);
        $this->assertEquals('First Item', $result['Name']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '%24top=1');
        });
    }

    #[Test]
    public function it_handles_find_by_id(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token'=> Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(123)' => Http::response([
                'Id' => 123,
                'Name' => 'Found by ID',
                'Email' => 'found@example.com',
            ], 200),
        ]);

        $builder = new AbacusQueryBuilder($this->service, 'Subjects');
        $result = $builder->find(123);

        $this->assertEquals(123, $result['Id']);
        $this->assertEquals('Found by ID', $result['Name']);
    }

    #[Test]
    public function it_handles_different_output_formats(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token'=> Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [['Id' => 1]],
            ], 200),
        ]);

        $builder = new AbacusQueryBuilder($this->service, 'Subjects');
        $results = $builder
            ->format('xml')
            ->getFirstPage();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '%24format=xml');
        });
    }

    #[Test]
    public function it_builds_query_without_execution(): void
    {
        $builder = new AbacusQueryBuilder($this->service, 'Subjects');
        $query = $builder
            ->where('Status', 'eq', 'Active')
            ->where('Age', 'gt', 18)
            ->select('Id', 'Name', 'Email')
            ->orderBy('Name', 'asc')
            ->top(50)
            ->expand('Addresses')
            ->toODataQuery();

        $this->assertArrayHasKey('$filter', $query);
        $this->assertArrayHasKey('$select', $query);
        $this->assertArrayHasKey('$orderby', $query);
        $this->assertArrayHasKey('$top', $query);
        $this->assertArrayHasKey('$expand', $query);

        $this->assertStringContainsString("Status eq 'Active'", $query['$filter']);
        $this->assertEquals('Id,Name,Email', $query['$select']);
        $this->assertEquals('Name asc', $query['$orderby']);
        $this->assertEquals(50, $query['$top']);
        $this->assertEquals('Addresses', $query['$expand']);
    }

    #[Test]
    public function it_handles_special_characters_in_values(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token'=> Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 1, 'Name' => "O'Brien"],
                ],
            ], 200),
        ]);

        $builder = new AbacusQueryBuilder($this->service, 'Subjects');
        $results = $builder
            ->where('Name', 'eq', "O'Brien")
            ->getFirstPage();

        Http::assertSent(function ($request) {
            /* Single quotes should be escaped as double single quotes and URL encoded */
            return str_contains($request->url(), "O%27%27Brien");
        });
    }

    #[Test]
    public function it_handles_multiple_select_and_expand_calls(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token'=> Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response(['value' => []], 200),
        ]);

        $builder = new AbacusQueryBuilder($this->service, 'Subjects');
        $builder
            ->select('Id', 'Name')
            ->select('Email')
            ->expand('Addresses')
            ->expand('Contacts', 'Orders')
            ->getFirstPage();

        Http::assertSent(function ($request) {
            $url = $request->url();
            return str_contains($url, '%24select=Id') &&
                   str_contains($url, 'Name') &&
                   str_contains($url, 'Email') &&
                   str_contains($url, '%24expand=Addresses') &&
                   str_contains($url, 'Contacts') &&
                   str_contains($url, 'Orders');
        });
    }
}
