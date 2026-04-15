<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\AbacusODataQueryBuilder;
use Contoweb\AbacusApi\AbacusService;
use Contoweb\AbacusApi\Batch\BatchContext;
use Contoweb\AbacusApi\Batch\BatchRequestItem;
use Contoweb\AbacusApi\Batch\PendingBatchRequest;
use Contoweb\AbacusApi\Enums\ODataOperator;
use Contoweb\AbacusApi\OdataPaginator;
use Contoweb\AbacusApi\Tests\Fixtures\TestSubject;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class AbacusODataQueryBuilderTest extends TestCase
{
    protected AbacusService $service;

    protected AbacusODataClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new AbacusODataClient($this->makeCredentialsProvider());
        $this->service = new AbacusService($this->client);
    }

    protected function tearDown(): void
    {
        BatchContext::clear();
        parent::tearDown();
    }

    #[Test]
    public function it_builds_simple_where_clause(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->where('LastName', 'eq', 'Müller');

        $query = $builder->toODataQuery();

        $this->assertEquals("LastName eq 'Müller'", $query['$filter']);
    }

    #[Test]
    public function it_builds_where_with_enum_operator(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->where('Age', ODataOperator::GREATER_THAN, 18);

        $query = $builder->toODataQuery();

        $this->assertEquals('Age gt 18', $query['$filter']);
    }

    #[Test]
    public function it_builds_multiple_where_clauses_with_and(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->where('LastName', 'eq', 'Müller')
            ->where('Age', 'gt', 18);

        $query = $builder->toODataQuery();

        $this->assertEquals("LastName eq 'Müller' and Age gt 18", $query['$filter']);
    }

    #[Test]
    public function it_builds_where_equals_convenience_method(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->whereEquals('Status', 'Active');

        $query = $builder->toODataQuery();

        $this->assertEquals("Status eq 'Active'", $query['$filter']);
    }

    #[Test]
    public function it_formats_string_values_with_quotes(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->where('Name', 'eq', 'Test String');

        $query = $builder->toODataQuery();

        $this->assertStringContainsString("'Test String'", $query['$filter']);
    }

    #[Test]
    public function it_escapes_single_quotes_in_string_values(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->where('Name', 'eq', "O'Brien");

        $query = $builder->toODataQuery();

        $this->assertStringContainsString("'O''Brien'", $query['$filter']);
    }

    #[Test]
    public function it_formats_boolean_values(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->where('IsActive', 'eq', true)
            ->where('IsDeleted', 'eq', false);

        $query = $builder->toODataQuery();

        $this->assertStringContainsString('IsActive eq true', $query['$filter']);
        $this->assertStringContainsString('IsDeleted eq false', $query['$filter']);
    }

    #[Test]
    public function it_formats_null_values(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->where('MiddleName', 'eq', null);

        $query = $builder->toODataQuery();

        $this->assertStringContainsString('MiddleName eq null', $query['$filter']);
    }

    #[Test]
    public function it_formats_numeric_values(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->where('Age', 'eq', 42);

        $query = $builder->toODataQuery();

        $this->assertEquals('Age eq 42', $query['$filter']);
    }

    #[Test]
    public function it_formats_datetime_values(): void
    {
        $date = new \DateTime('2024-01-15 14:30:00');
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->where('CreatedAt', 'gt', $date);

        $query = $builder->toODataQuery();

        $this->assertStringContainsString('2024-01-15T14:30:00Z', $query['$filter']);
    }

    #[Test]
    public function it_throws_exception_for_invalid_operator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Operator 'invalid' not supported");

        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->where('Name', 'invalid', 'Test');
    }

    #[Test]
    public function it_supports_all_valid_operators(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);

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
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->select(['Id', 'Name', 'Email']);

        $query = $builder->toODataQuery();

        $this->assertEquals('Id,Name,Email', $query['$select']);
    }

    #[Test]
    public function it_builds_select_query_with_varargs(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->select('Id', 'Name', 'Email');

        $query = $builder->toODataQuery();

        $this->assertEquals('Id,Name,Email', $query['$select']);
    }

    #[Test]
    public function it_merges_multiple_select_calls(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->select('Id', 'Name')
            ->select('Email');

        $query = $builder->toODataQuery();

        $this->assertEquals('Id,Name,Email', $query['$select']);
    }

    #[Test]
    public function it_builds_order_by_ascending(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->orderBy('LastName', 'asc');

        $query = $builder->toODataQuery();

        $this->assertEquals('LastName asc', $query['$orderby']);
    }

    #[Test]
    public function it_builds_order_by_descending(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->orderBy('CreatedAt', 'desc');

        $query = $builder->toODataQuery();

        $this->assertEquals('CreatedAt desc', $query['$orderby']);
    }

    #[Test]
    public function it_defaults_order_by_to_ascending(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->orderBy('Name');

        $query = $builder->toODataQuery();

        $this->assertEquals('Name asc', $query['$orderby']);
    }

    #[Test]
    public function it_throws_exception_for_invalid_order_direction(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Direction must be 'asc' or 'desc'");

        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->orderBy('Name', 'invalid');
    }

    #[Test]
    public function it_overrides_previous_order_by(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->orderBy('FirstName', 'asc')
            ->orderBy('LastName', 'desc');

        $query = $builder->toODataQuery();

        $this->assertEquals('LastName desc', $query['$orderby']);
    }

    #[Test]
    public function it_builds_expand_query_with_array(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->expand(['Addresses', 'Contacts']);

        $query = $builder->toODataQuery();

        $this->assertEquals('Addresses,Contacts', $query['$expand']);
    }

    #[Test]
    public function it_builds_expand_query_with_varargs(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->expand('Addresses', 'Contacts');

        $query = $builder->toODataQuery();

        $this->assertEquals('Addresses,Contacts', $query['$expand']);
    }

    #[Test]
    public function it_merges_multiple_expand_calls(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->expand('Addresses')
            ->expand('Contacts', 'Orders');

        $query = $builder->toODataQuery();

        $this->assertEquals('Addresses,Contacts,Orders', $query['$expand']);
    }

    #[Test]
    public function it_executes_first(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 1, 'Name' => 'First Item'],
                ],
            ], 200),
        ]);

        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $result = $builder->where('IsActive', 'eq', true)->first();

        $this->assertEquals('First Item', $result->Name);
    }

    #[Test]
    public function it_executes_find(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(42)' => Http::response([
                'Id' => 42,
                'Name' => 'Found Item',
            ], 200),
        ]);

        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $result = $builder->find(42);

        $this->assertEquals(42, $result->Id);
        $this->assertEquals('Found Item', $result->Name);
    }

    #[Test]
    public function it_executes_find_property(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(42)/Name' => Http::response([
                'value' => 'John Doe',
            ], 200),
        ]);

        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $result = $builder->findProperty(42, 'Name');

        $this->assertEquals(['value' => 'John Doe'], $result);
    }

    #[Test]
    public function it_chains_multiple_methods_fluently(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);

        $result = $builder->where('IsActive', 'eq', true)
            ->select('Id', 'Name')
            ->orderBy('Name', 'asc')
            ->expand(['Addresses', 'Contacts']);

        $this->assertInstanceOf(AbacusODataQueryBuilder::class, $result);
    }

    #[Test]
    public function it_returns_batch_request_item_for_get_in_batch_context(): void
    {
        $batch = new PendingBatchRequest($this->client);
        BatchContext::set($batch);

        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $result = $builder->where('IsActive', 'eq', true)->select(['Id', 'Name'])->paginate();

        $this->assertInstanceOf(BatchRequestItem::class, $result);
        $this->assertEquals('GET', $result->method);
        $this->assertStringContainsString('Subjects', $result->path);
        $this->assertStringContainsString('$filter=', $result->path);
        $this->assertStringContainsString('$select=', $result->path);
        $this->assertNull($result->body);
        $this->assertEquals(1, $batch->count());
    }

    #[Test]
    public function it_returns_batch_request_item_for_find_in_batch_context(): void
    {
        $batch = new PendingBatchRequest($this->client);
        BatchContext::set($batch);

        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $result = $builder->find(42);

        $this->assertInstanceOf(BatchRequestItem::class, $result);
        $this->assertEquals('GET', $result->method);
        $this->assertStringContainsString('Subjects(42)', $result->path);
        $this->assertNull($result->body);
        $this->assertEquals(1, $batch->count());
    }

    #[Test]
    public function it_returns_batch_request_item_for_create_in_batch_context(): void
    {
        $batch = new PendingBatchRequest($this->client);
        BatchContext::set($batch);

        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $result = $builder->create(['FirstName' => 'Test']);

        $this->assertInstanceOf(BatchRequestItem::class, $result);
        $this->assertEquals('POST', $result->method);
        $this->assertStringContainsString('Subjects', $result->path);
        $this->assertEquals(['FirstName' => 'Test'], $result->body);
        $this->assertEquals(1, $batch->count());
    }

    #[Test]
    public function it_returns_batch_request_item_for_update_in_batch_context(): void
    {
        $batch = new PendingBatchRequest($this->client);
        BatchContext::set($batch);

        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $result = $builder->update(42, ['FirstName' => 'Updated']);

        $this->assertInstanceOf(BatchRequestItem::class, $result);
        $this->assertEquals('PATCH', $result->method);
        $this->assertStringContainsString('Subjects(42)', $result->path);
        $this->assertEquals(['FirstName' => 'Updated'], $result->body);
        $this->assertEquals(1, $batch->count());
    }

    #[Test]
    public function it_returns_batch_request_item_for_delete_in_batch_context(): void
    {
        $batch = new PendingBatchRequest($this->client);
        BatchContext::set($batch);

        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $result = $builder->delete(42);

        $this->assertInstanceOf(BatchRequestItem::class, $result);
        $this->assertEquals('DELETE', $result->method);
        $this->assertStringContainsString('Subjects(42)', $result->path);
        $this->assertNull($result->body);
        $this->assertEquals(1, $batch->count());
    }

    #[Test]
    public function it_paginate_returns_odata_paginator(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 1, 'Name' => 'First'],
                    ['Id' => 2, 'Name' => 'Second'],
                ],
            ], 200),
        ]);

        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $result = $builder->paginate();

        $this->assertInstanceOf(OdataPaginator::class, $result);
        $this->assertCount(2, $result->items());
    }

    #[Test]
    public function it_paginate_applies_top_when_limit_given(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 1, 'Name' => 'First'],
                ],
            ], 200),
        ]);

        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $result = $builder->paginate(10);

        $this->assertInstanceOf(OdataPaginator::class, $result);

        // Verify that the request was made with $top=10 (URL encoded as %24top)
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '%24top=10');
        });
    }

    #[Test]
    public function it_paginate_throws_on_zero_limit(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit should be greater than 0');

        $builder->paginate(0);
    }

    #[Test]
    public function it_paginate_throws_on_negative_limit(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit should be greater than 0');

        $builder->paginate(-1);
    }

    #[Test]
    public function it_paginate_sets_has_more_pages_true_when_next_link_in_response(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 1, 'Name' => 'First'],
                ],
                '@odata.nextLink' => 'https://example.com/Subjects?$skiptoken=abc123',
            ], 200),
        ]);

        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $result = $builder->paginate();

        $this->assertTrue($result->hasMorePages());
    }

    #[Test]
    public function it_paginate_sets_has_more_pages_false_when_no_next_link(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 1, 'Name' => 'First'],
                ],
            ], 200),
        ]);

        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $result = $builder->paginate();

        $this->assertFalse($result->hasMorePages());
    }

    #[Test]
    public function it_formats_uuid_values_without_quotes(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->where('DocumentId', 'eq', $uuid);

        $query = $builder->toODataQuery();

        $this->assertEquals("DocumentId eq {$uuid}", $query['$filter']);
    }

    #[Test]
    public function it_formats_non_uuid_strings_with_quotes(): void
    {
        $value = '550e8400-e29b-41d4-a716';
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->where('DocumentId', 'eq', $value);

        $query = $builder->toODataQuery();

        $this->assertStringContainsString("'{$value}'", $query['$filter']);
    }

    #[Test]
    public function it_quotes_uuid_values_when_uuid_escaping_is_enabled(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->withUUIDEscaping()->where('DocumentId', 'eq', $uuid);

        $query = $builder->toODataQuery();

        $this->assertStringContainsString("'{$uuid}'", $query['$filter']);
    }

    #[Test]
    public function it_still_escapes_regular_strings_when_uuid_escaping_is_enabled(): void
    {
        $builder = new AbacusODataQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->withUUIDEscaping()->where('Name', 'eq', "O'Brien");

        $query = $builder->toODataQuery();

        $this->assertStringContainsString("'O''Brien'", $query['$filter']);
    }
}
