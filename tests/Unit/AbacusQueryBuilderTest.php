<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\Tests\TestCase;
use Contoweb\AbacusApi\AbacusClient;
use Contoweb\AbacusApi\AbacusQueryBuilder;
use Contoweb\AbacusApi\AbacusService;
use Contoweb\AbacusApi\Enums\ODataOperator;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class AbacusQueryBuilderTest extends TestCase
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
    public function it_builds_simple_where_clause(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->where('LastName', 'eq', 'Müller');

        $query = $builder->toODataQuery();

        $this->assertEquals("LastName eq 'Müller'", $query['$filter']);
    }

    #[Test]
    public function it_builds_where_with_enum_operator(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->where('Age', ODataOperator::GREATER_THAN, 18);

        $query = $builder->toODataQuery();

        $this->assertEquals('Age gt 18', $query['$filter']);
    }

    #[Test]
    public function it_builds_multiple_where_clauses_with_and(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->where('LastName', 'eq', 'Müller')
                ->where('Age', 'gt', 18);

        $query = $builder->toODataQuery();

        $this->assertEquals("LastName eq 'Müller' and Age gt 18", $query['$filter']);
    }

    #[Test]
    public function it_builds_where_equals_convenience_method(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->whereEquals('Status', 'Active');

        $query = $builder->toODataQuery();

        $this->assertEquals("Status eq 'Active'", $query['$filter']);
    }

    #[Test]
    public function it_formats_string_values_with_quotes(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->where('Name', 'eq', 'Test String');

        $query = $builder->toODataQuery();

        $this->assertStringContainsString("'Test String'", $query['$filter']);
    }

    #[Test]
    public function it_escapes_single_quotes_in_string_values(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->where('Name', 'eq', "O'Brien");

        $query = $builder->toODataQuery();

        $this->assertStringContainsString("'O''Brien'", $query['$filter']);
    }

    #[Test]
    public function it_formats_boolean_values(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->where('IsActive', 'eq', true)
                ->where('IsDeleted', 'eq', false);

        $query = $builder->toODataQuery();

        $this->assertStringContainsString('IsActive eq true', $query['$filter']);
        $this->assertStringContainsString('IsDeleted eq false', $query['$filter']);
    }

    #[Test]
    public function it_formats_null_values(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->where('MiddleName', 'eq', null);

        $query = $builder->toODataQuery();

        $this->assertStringContainsString('MiddleName eq null', $query['$filter']);
    }

    #[Test]
    public function it_formats_numeric_values(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->where('Age', 'eq', 42);

        $query = $builder->toODataQuery();

        $this->assertEquals('Age eq 42', $query['$filter']);
    }

    #[Test]
    public function it_formats_datetime_values(): void
    {
        $date = new \DateTime('2024-01-15 14:30:00');
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->where('CreatedAt', 'gt', $date);

        $query = $builder->toODataQuery();

        $this->assertStringContainsString('2024-01-15T14:30:00Z', $query['$filter']);
    }

    #[Test]
    public function it_throws_exception_for_invalid_operator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Operator 'invalid' not supported");

        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->where('Name', 'invalid', 'Test');
    }

    #[Test]
    public function it_supports_all_valid_operators(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);

        $builder->where('Field1', 'eq', 1)
                ->where('Field2', 'lt', 2)
                ->where('Field3', 'gt', 3)
                ->where('Field4', 'le', 4)
                ->where('Field5', 'ge', 5);

        $query = $builder->toODataQuery();

        $this->assertStringContainsString('Field1 eq 1', $query['$filter']);
        $this->assertStringContainsString('Field2 lt 2', $query['$filter']);
        $this->assertStringContainsString('Field3 gt 3', $query['$filter']);
        $this->assertStringContainsString('Field4 le 4', $query['$filter']);
        $this->assertStringContainsString('Field5 ge 5', $query['$filter']);
    }

    #[Test]
    public function it_builds_select_query_with_array(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->select(['Id', 'Name', 'Email']);

        $query = $builder->toODataQuery();

        $this->assertEquals('Id,Name,Email', $query['$select']);
    }

    #[Test]
    public function it_builds_select_query_with_varargs(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->select('Id', 'Name', 'Email');

        $query = $builder->toODataQuery();

        $this->assertEquals('Id,Name,Email', $query['$select']);
    }

    #[Test]
    public function it_merges_multiple_select_calls(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->select('Id', 'Name')
                ->select('Email');

        $query = $builder->toODataQuery();

        $this->assertEquals('Id,Name,Email', $query['$select']);
    }

    #[Test]
    public function it_builds_top_query(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->top(10);

        $query = $builder->toODataQuery();

        $this->assertEquals(10, $query['$top']);
    }

    #[Test]
    public function it_builds_limit_query_as_alias_for_top(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->limit(5);

        $query = $builder->toODataQuery();

        $this->assertEquals(5, $query['$top']);
    }

    #[Test]
    public function it_builds_take_query_as_alias_for_top(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->take(20);

        $query = $builder->toODataQuery();

        $this->assertEquals(20, $query['$top']);
    }

    #[Test]
    public function it_builds_order_by_ascending(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->orderBy('LastName', 'asc');

        $query = $builder->toODataQuery();

        $this->assertEquals('LastName asc', $query['$orderby']);
    }

    #[Test]
    public function it_builds_order_by_descending(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->orderBy('CreatedAt', 'desc');

        $query = $builder->toODataQuery();

        $this->assertEquals('CreatedAt desc', $query['$orderby']);
    }

    #[Test]
    public function it_defaults_order_by_to_ascending(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->orderBy('Name');

        $query = $builder->toODataQuery();

        $this->assertEquals('Name asc', $query['$orderby']);
    }

    #[Test]
    public function it_throws_exception_for_invalid_order_direction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Direction must be 'asc' or 'desc'");

        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->orderBy('Name', 'invalid');
    }

    #[Test]
    public function it_overrides_previous_order_by(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->orderBy('FirstName', 'asc')
                ->orderBy('LastName', 'desc');

        $query = $builder->toODataQuery();

        $this->assertEquals('LastName desc', $query['$orderby']);
    }

    #[Test]
    public function it_builds_expand_query_with_array(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->expand(['Addresses', 'Contacts']);

        $query = $builder->toODataQuery();

        $this->assertEquals('Addresses,Contacts', $query['$expand']);
    }

    #[Test]
    public function it_builds_expand_query_with_varargs(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->expand('Addresses', 'Contacts');

        $query = $builder->toODataQuery();

        $this->assertEquals('Addresses,Contacts', $query['$expand']);
    }

    #[Test]
    public function it_merges_multiple_expand_calls(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $builder->expand('Addresses')
                ->expand('Contacts', 'Orders');

        $query = $builder->toODataQuery();

        $this->assertEquals('Addresses,Contacts,Orders', $query['$expand']);
    }

    #[Test]
    public function it_executes_first(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token'=> Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 1, 'Name' => 'First Item'],
                ],
            ], 200),
        ]);

        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $result = $builder->where('IsActive', 'eq', true)->first();

        $this->assertEquals('First Item', $result->Name);
    }

    #[Test]
    public function it_executes_find(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token'=> Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(42)' => Http::response([
                'Id' => 42,
                'Name' => 'Found Item',
            ], 200),
        ]);

        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $result = $builder->find(42);

        $this->assertEquals(42, $result->Id);
        $this->assertEquals('Found Item', $result->Name);
    }

    #[Test]
    public function it_executes_find_property(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token'=> Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(42)/Name' => Http::response([
                'value' => 'John Doe',
            ], 200),
        ]);

        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $result = $builder->findProperty(42, 'Name');

        $this->assertEquals(['value' => 'John Doe'], $result);
    }

    #[Test]
    public function it_chains_multiple_methods_fluently(): void
    {
        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);

        $result = $builder->where('IsActive', 'eq', true)
                          ->select('Id', 'Name')
                          ->orderBy('Name', 'asc')
                          ->top(10);

        $this->assertInstanceOf(AbacusQueryBuilder::class, $result);
    }

    #[Test]
    public function it_calls_callback_for_each_page_during_cursor_pagination(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
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
                ],
                '@odata.nextLink' => 'https://api.example.com/page3',
            ], 200),
            'https://api.example.com/page3' => Http::response([
                'value' => [
                    ['Id' => 4, 'Name' => 'Item 4'],
                ],
            ], 200),
        ]);

        $callbackCalls = [];

        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $results = $builder
            ->pages(10)
            ->cursorWithCallback(function ($items, $pageNumber) use (&$callbackCalls) {
                $callbackCalls[] = [
                    'page' => $pageNumber,
                    'count' => $items->count(),
                    'items' => $items->pluck('Name')->toArray(),
                ];
            })
            ->get();

        /* Assert callback was called 3 times (one for each page) */
        $this->assertCount(3, $callbackCalls);

        /* Assert page 1 */
        $this->assertEquals(1, $callbackCalls[0]['page']);
        $this->assertEquals(2, $callbackCalls[0]['count']);
        $this->assertEquals(['Item 1', 'Item 2'], $callbackCalls[0]['items']);

        /* Assert page 2 */
        $this->assertEquals(2, $callbackCalls[1]['page']);
        $this->assertEquals(1, $callbackCalls[1]['count']);
        $this->assertEquals(['Item 3'], $callbackCalls[1]['items']);

        /* Assert page 3 */
        $this->assertEquals(3, $callbackCalls[2]['page']);
        $this->assertEquals(1, $callbackCalls[2]['count']);
        $this->assertEquals(['Item 4'], $callbackCalls[2]['items']);

        /* Assert all items are still returned */
        $this->assertCount(4, $results);
    }

    #[Test]
    public function it_calls_callback_without_pagination_for_single_page(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 1, 'Name' => 'Single Item'],
                ],
            ], 200),
        ]);

        $callbackCalls = [];

        $builder = new AbacusQueryBuilder($this->client, 'Subjects', \Contoweb\AbacusApi\Tests\Fixtures\TestSubject::class);
        $results = $builder
            ->cursorWithCallback(function ($items, $pageNumber) use (&$callbackCalls) {
                $callbackCalls[] = [
                    'page' => $pageNumber,
                    'count' => $items->count(),
                ];
            })
            ->get();

        /* Assert callback was called once */
        $this->assertCount(1, $callbackCalls);
        $this->assertEquals(1, $callbackCalls[0]['page']);
        $this->assertEquals(1, $callbackCalls[0]['count']);

        /* Assert result */
        $this->assertCount(1, $results);
    }
}
