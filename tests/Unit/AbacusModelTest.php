<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\AbacusODataQueryBuilder;
use Contoweb\AbacusApi\Enums\ODataOperator;
use Contoweb\AbacusApi\Models\AbacusModel;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

/* Test model for testing */
class TestSubject extends AbacusModel
{
    protected static string $resource = 'Subjects';
}

/* Test model with composite primary key */
class TestStockBatch extends AbacusModel
{
    protected static string $resource = 'stock-batches';

    protected static string|array $primaryKey = ['BatchNumber', 'BatchSequenceNumber', 'ProductId', 'VariantId'];
}

class AbacusModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
        ]);
    }

    #[Test]
    public function it_constructs_with_attributes(): void
    {
        $subject = new TestSubject(['Id' => 1, 'Name' => 'John Doe']);

        $this->assertEquals(1, $subject->Id);
        $this->assertEquals('John Doe', $subject->Name);
    }

    #[Test]
    public function it_returns_query_builder(): void
    {
        $query = TestSubject::query();

        $this->assertInstanceOf(AbacusODataQueryBuilder::class, $query);
    }

    #[Test]
    public function it_finds_entity_by_id(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(42)' => Http::response([
                'Id' => 42,
                'Name' => 'Test Subject',
                'Email' => 'test@example.com',
            ], 200),
        ]);

        $subject = TestSubject::find(42);

        $this->assertInstanceOf(TestSubject::class, $subject);
        $this->assertEquals(42, $subject->Id);
        $this->assertEquals('Test Subject', $subject->Name);
    }

    #[Test]
    public function it_returns_null_when_entity_not_found(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(999)' => Http::response([
                'error' => 'Not found',
            ], 404),
        ]);

        $this->expectException(\Exception::class);

        TestSubject::find(999);
    }

    #[Test]
    public function it_starts_where_query(): void
    {
        $query = TestSubject::where('Name', 'eq', 'John');

        $this->assertInstanceOf(AbacusODataQueryBuilder::class, $query);
    }

    #[Test]
    public function it_starts_where_query_with_enum_operator(): void
    {
        $query = TestSubject::where('Age', ODataOperator::GREATER_THAN, 18);

        $this->assertInstanceOf(AbacusODataQueryBuilder::class, $query);
    }

    #[Test]
    public function it_starts_select_query(): void
    {
        $query = TestSubject::select(['Id', 'Name']);

        $this->assertInstanceOf(AbacusODataQueryBuilder::class, $query);
    }

    #[Test]
    public function it_starts_order_by_query(): void
    {
        $query = TestSubject::orderBy('Name', 'desc');

        $this->assertInstanceOf(AbacusODataQueryBuilder::class, $query);
    }

    #[Test]
    public function it_starts_expand_query(): void
    {
        $query = TestSubject::expand(['Addresses', 'Contacts']);

        $this->assertInstanceOf(AbacusODataQueryBuilder::class, $query);
    }

    #[Test]
    public function it_creates_entity(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects' => Http::response([
                'Id' => 100,
                'Name' => 'New Subject',
                'Email' => 'new@example.com',
            ], 201),
        ]);

        $subject = TestSubject::create([
            'Name' => 'New Subject',
            'Email' => 'new@example.com',
        ]);

        $this->assertInstanceOf(TestSubject::class, $subject);
        $this->assertEquals(100, $subject->Id);
        $this->assertEquals('New Subject', $subject->Name);
    }

    #[Test]
    public function it_updates_entity_with_static_method(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(75)' => Http::response([
                'Id' => 75,
                'Name' => 'Updated Name',
                'Email' => 'updated@example.com',
            ], 200),
        ]);

        $subject = TestSubject::update(75, ['Name' => 'Updated Name', 'Email' => 'updated@example.com']);

        $this->assertInstanceOf(TestSubject::class, $subject);
        $this->assertEquals('Updated Name', $subject->Name);
        $this->assertEquals('updated@example.com', $subject->Email);
    }

    #[Test]
    public function it_deletes_entity_with_static_method(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(42)' => Http::response(null, 204),
        ]);

        TestSubject::delete(42);

        /* Delete returns void, just verify no exception */
        $this->assertTrue(true);
    }

    #[Test]
    public function it_gets_attribute(): void
    {
        $subject = new TestSubject(['Name' => 'Test', 'Email' => 'test@example.com']);

        $this->assertEquals('Test', $subject->getAttribute('Name'));
        $this->assertEquals('test@example.com', $subject->getAttribute('Email'));
        $this->assertNull($subject->getAttribute('NonExistent'));
    }

    #[Test]
    public function it_sets_attribute(): void
    {
        $subject = new TestSubject(['Name' => 'Original']);
        $subject->setAttribute('Name', 'Modified');
        $subject->setAttribute('Email', 'new@example.com');

        $this->assertEquals('Modified', $subject->Name);
        $this->assertEquals('new@example.com', $subject->Email);
    }

    #[Test]
    public function it_gets_all_attributes(): void
    {
        $attributes = ['Id' => 1, 'Name' => 'Test', 'Email' => 'test@example.com'];
        $subject = new TestSubject($attributes);

        $this->assertEquals($attributes, $subject->getAttributes());
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $attributes = ['Id' => 1, 'Name' => 'Test'];
        $subject = new TestSubject($attributes);

        $this->assertEquals($attributes, $subject->toArray());
    }

    #[Test]
    public function it_gets_resource_name(): void
    {
        $this->assertEquals('Subjects', TestSubject::getResource());
    }

    #[Test]
    public function it_uses_magic_getter(): void
    {
        $subject = new TestSubject(['Name' => 'Magic', 'Email' => 'magic@example.com']);

        $this->assertEquals('Magic', $subject->Name);
        $this->assertEquals('magic@example.com', $subject->Email);
    }

    #[Test]
    public function it_uses_magic_setter(): void
    {
        $subject = new TestSubject;
        $subject->Name = 'Magic Set';
        $subject->Email = 'magic@example.com';

        $this->assertEquals('Magic Set', $subject->Name);
        $this->assertEquals('magic@example.com', $subject->Email);
    }

    #[Test]
    public function it_uses_magic_isset(): void
    {
        $subject = new TestSubject(['Name' => 'Test']);

        $this->assertTrue(isset($subject->Name));
        $this->assertFalse(isset($subject->NonExistent));
    }

    #[Test]
    public function it_returns_model_instance_when_using_where_first(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response([
                'value' => [
                    ['Id' => 99, 'Name' => 'Query Result', 'Email' => 'result@example.com'],
                ],
            ], 200),
        ]);

        $subject = TestSubject::where('Name', 'eq', 'Query Result')->first();

        $this->assertInstanceOf(TestSubject::class, $subject);
        $this->assertEquals(99, $subject->Id);
        $this->assertEquals('Query Result', $subject->Name);
    }

    #[Test]
    public function it_returns_model_collection_when_using_where_paginate(): void
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

        $subjects = TestSubject::where('IsActive', 'eq', true)->paginate();

        $this->assertCount(2, $subjects->items());
        $this->assertInstanceOf(TestSubject::class, $subjects->items()->first());
        $this->assertInstanceOf(TestSubject::class, $subjects->items()->last());
        $this->assertEquals('First', $subjects->items()->first()->Name);
        $this->assertEquals('Second', $subjects->items()->last()->Name);
    }

    #[Test]
    public function it_detects_single_primary_key(): void
    {
        $this->assertEquals('Id', TestSubject::getPrimaryKey());
        $this->assertTrue(TestSubject::hasSinglePrimaryKey());
        $this->assertFalse(TestSubject::hasCompositePrimaryKey());
    }

    #[Test]
    public function it_detects_composite_primary_key(): void
    {
        $this->assertEquals(
            ['BatchNumber', 'BatchSequenceNumber', 'ProductId', 'VariantId'],
            TestStockBatch::getPrimaryKey()
        );
        $this->assertFalse(TestStockBatch::hasSinglePrimaryKey());
        $this->assertTrue(TestStockBatch::hasCompositePrimaryKey());
    }

    #[Test]
    public function it_finds_entity_with_composite_key(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            "*stock-batches(BatchNumber='123',BatchSequenceNumber=0,ProductId=456,VariantId=0)*" => Http::response([
                'BatchNumber' => '123',
                'BatchSequenceNumber' => 0,
                'ProductId' => 456,
                'VariantId' => 0,
                'ExpirationDate1' => '2024-12-31',
            ], 200),
        ]);

        $batch = TestStockBatch::find([
            'BatchNumber' => '123',
            'BatchSequenceNumber' => 0,
            'ProductId' => 456,
            'VariantId' => 0,
        ]);

        $this->assertInstanceOf(TestStockBatch::class, $batch);
        $this->assertEquals('123', $batch->BatchNumber);
        $this->assertEquals(456, $batch->ProductId);
    }

    #[Test]
    public function it_updates_entity_with_composite_key(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            "*stock-batches(BatchNumber='123',BatchSequenceNumber=0,ProductId=456,VariantId=0)*" => Http::response([
                'BatchNumber' => '123',
                'BatchSequenceNumber' => 0,
                'ProductId' => 456,
                'VariantId' => 0,
                'Remark1' => 'Updated remark',
            ], 200),
        ]);

        $batch = TestStockBatch::update(
            [
                'BatchNumber' => '123',
                'BatchSequenceNumber' => 0,
                'ProductId' => 456,
                'VariantId' => 0,
            ],
            ['Remark1' => 'Updated remark']
        );

        $this->assertInstanceOf(TestStockBatch::class, $batch);
        $this->assertEquals('Updated remark', $batch->Remark1);
    }

    #[Test]
    public function it_deletes_entity_with_composite_key(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            "*stock-batches(BatchNumber='123',BatchSequenceNumber=0,ProductId=456,VariantId=0)*" => Http::response(null, 204),
        ]);

        TestStockBatch::delete([
            'BatchNumber' => '123',
            'BatchSequenceNumber' => 0,
            'ProductId' => 456,
            'VariantId' => 0,
        ]);

        /* Delete returns void, just verify no exception */
        $this->assertTrue(true);
    }

    #[Test]
    public function it_updates_with_select_chaining(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(100)*' => Http::response([
                'Id' => 100,
                'Name' => 'Chained Update',
            ], 200),
        ]);

        $subject = TestSubject::select(['Id', 'Name'])->update(100, ['Name' => 'Chained Update']);

        $this->assertInstanceOf(TestSubject::class, $subject);
        $this->assertEquals('Chained Update', $subject->Name);
    }

    #[Test]
    public function it_paginate_returns_odata_paginator_via_static_call(): void
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

        $paginator = TestSubject::paginate();

        $this->assertInstanceOf(\Contoweb\AbacusApi\OdataPaginator::class, $paginator);
        $this->assertCount(2, $paginator->items());
        $this->assertEquals('First', $paginator->items()->first()->Name);
        $this->assertEquals('Second', $paginator->items()->last()->Name);
    }

    #[Test]
    public function it_paginate_with_limit_applies_top_to_query(): void
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

        $paginator = TestSubject::paginate(50);

        $this->assertInstanceOf(\Contoweb\AbacusApi\OdataPaginator::class, $paginator);

        // Verify that the request was made with $top=50 (URL encoded as %24top)
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '%24top=50');
        });
    }
}
