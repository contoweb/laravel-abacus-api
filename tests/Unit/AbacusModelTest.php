<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Carbon\Carbon;
use Contoweb\AbacusApi\AbacusODataQueryBuilder;
use Contoweb\AbacusApi\Enums\ODataOperator;
use Contoweb\AbacusApi\Models\AbacusComponent;
use Contoweb\AbacusApi\Models\AbacusModel;
use Contoweb\AbacusApi\OdataPaginator;
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

/* Test backed enum - matches Abacus ProductType */
enum TestProductType: string
{
    case ARTICLE = 'Article';
    case SERVICE = 'Service';
    case SURCHARGE = 'Surcharge';
    case MISCELLANEOUS = 'Miscellaneous';
}

/* Test status enum - matches Abacus ActiveStatus */
enum TestActiveStatus: string
{
    case ACTIVE = 'Active';
    case INACTIVE = 'Inactive';
}

/* Test component - matches Abacus Measurements schema */
class TestMeasurements extends AbacusComponent
{
    // Properties from ch.abacus.orde.Measurements:
    // Length, Width, Height, VolumeOrArea, Diameter, UnitId
}

/* Test component - matches Abacus Weights schema */
class TestWeights extends AbacusComponent
{
    // Properties from ch.abacus.orde.Weights:
    // Net, Tare, SpecificWeight, UnitId
}

/* Test model with casts - reflects actual Abacus Product API structure */
class TestProduct extends AbacusModel
{
    protected static string $resource = 'Products';

    protected array $casts = [
        // Real Abacus Product fields
        'Id' => 'int',
        'Type' => TestProductType::class,
        'DivisionId' => 'int',
        'FieldSetNumber' => 'int',
        'VariantStatus' => TestActiveStatus::class,
        'Measurements' => TestMeasurements::class,
        'Weights' => TestWeights::class,

        // Additional test fields for comprehensive casting tests
        'price' => 'float',
        'is_active' => 'bool',
        'config' => 'json',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'launch_date' => 'datetime:Y-m-d',
    ];
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
            '*/api/entity/v1/mandants/1212/Subjects(42)' => Http::response([
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
            '*/api/entity/v1/mandants/1212/Subjects(999)' => Http::response([
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
            '*/api/entity/v1/mandants/1212/Subjects' => Http::response([
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
            '*/api/entity/v1/mandants/1212/Subjects(75)' => Http::response([
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
            '*/api/entity/v1/mandants/1212/Subjects(42)' => Http::response(null, 204),
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
    public function it_casts_primitive_types(): void
    {
        $product = new TestProduct([
            'Id' => '123',
            'DivisionId' => '5',
            'price' => '99.99',
            'is_active' => '1',
            'ProductNumber' => 'PROD-001',
        ]);

        $this->assertSame(123, $product->Id);
        $this->assertSame(5, $product->DivisionId);
        $this->assertSame(99.99, $product->price);
        $this->assertTrue($product->is_active);
        $this->assertSame('PROD-001', $product->ProductNumber);
    }

    #[Test]
    public function it_casts_datetime_to_carbon(): void
    {
        $product = new TestProduct([
            'created_at' => '2024-01-15 10:30:00',
        ]);

        $this->assertInstanceOf(Carbon::class, $product->created_at);
        $this->assertEquals('2024-01-15', $product->created_at->format('Y-m-d'));
        $this->assertEquals('10:30:00', $product->created_at->format('H:i:s'));
    }

    #[Test]
    public function it_casts_datetime_with_custom_format_in_array(): void
    {
        $product = new TestProduct([
            'launch_date' => '2024-03-20 14:25:30',
        ]);

        // Should return Carbon instance when accessed
        $this->assertInstanceOf(Carbon::class, $product->launch_date);

        // Should serialize with custom format in toArray()
        $array = $product->toArray();
        $this->assertEquals('2024-03-20', $array['launch_date']);
    }

    #[Test]
    public function it_casts_backed_enums(): void
    {
        // Test enum from string value - using real Abacus fields
        $product = new TestProduct([
            'Type' => 'Article',
            'VariantStatus' => 'Active',
        ]);

        $this->assertInstanceOf(TestProductType::class, $product->Type);
        $this->assertSame(TestProductType::ARTICLE, $product->Type);
        $this->assertInstanceOf(TestActiveStatus::class, $product->VariantStatus);
        $this->assertSame(TestActiveStatus::ACTIVE, $product->VariantStatus);

        // Test setting enum instance
        $product->Type = TestProductType::SERVICE;
        $this->assertSame(TestProductType::SERVICE, $product->Type);

        // Test serialization in toArray()
        $array = $product->toArray();
        $this->assertEquals('Service', $array['Type']);
        $this->assertEquals('Active', $array['VariantStatus']);
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
            '*/api/entity/v1/mandants/1212/Subjects(100)*' => Http::response([
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

        $this->assertInstanceOf(OdataPaginator::class, $paginator);
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

        $this->assertInstanceOf(OdataPaginator::class, $paginator);

        // Verify that the request was made with $top=50 (URL encoded as %24top)
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '%24top=50');
        });
    }

    #[Test]
    public function it_casts_nested_component_to_object(): void
    {
        // Test with real Abacus Measurements component structure
        $product = new TestProduct([
            'Id' => 1,
            'ProductNumber' => 'PROD-001',
            'Measurements' => [
                'Length' => 10.5,
                'Width' => 5.2,
                'Height' => 3.1,
                'VolumeOrArea' => 168.84,
                'Diameter' => 2.5,
                'UnitId' => 1,
            ],
            'Weights' => [
                'Net' => 2.5,
                'Tare' => 0.3,
                'SpecificWeight' => 0.85,
                'UnitId' => 2,
            ],
        ]);

        // Test Measurements component
        $this->assertInstanceOf(TestMeasurements::class, $product->Measurements);
        $this->assertEquals(10.5, $product->Measurements->Length);
        $this->assertEquals(5.2, $product->Measurements->Width);
        $this->assertEquals(3.1, $product->Measurements->Height);
        $this->assertEquals(168.84, $product->Measurements->VolumeOrArea);
        $this->assertEquals(2.5, $product->Measurements->Diameter);
        $this->assertEquals(1, $product->Measurements->UnitId);

        // Test Weights component
        $this->assertInstanceOf(TestWeights::class, $product->Weights);
        $this->assertEquals(2.5, $product->Weights->Net);
        $this->assertEquals(0.3, $product->Weights->Tare);
        $this->assertEquals(0.85, $product->Weights->SpecificWeight);
        $this->assertEquals(2, $product->Weights->UnitId);
    }

    #[Test]
    public function it_supports_array_access_on_components(): void
    {
        $product = new TestProduct([
            'Measurements' => [
                'Length' => 10.5,
                'Width' => 5.2,
                'Diameter' => 2.5,
            ],
        ]);

        // Should support array access for backward compatibility
        $this->assertEquals(10.5, $product->Measurements['Length']);
        $this->assertEquals(5.2, $product->Measurements['Width']);
        $this->assertEquals(2.5, $product->Measurements['Diameter']);
    }

    #[Test]
    public function it_allows_setting_component_properties(): void
    {
        $product = new TestProduct([
            'Measurements' => [
                'Length' => 10.5,
            ],
        ]);

        // Modify component properties - real Abacus fields
        $product->Measurements->Width = 5.2;
        $product->Measurements->Height = 3.1;
        $product->Measurements->Diameter = 2.5;

        $this->assertEquals(5.2, $product->Measurements->Width);
        $this->assertEquals(3.1, $product->Measurements->Height);
        $this->assertEquals(2.5, $product->Measurements->Diameter);
    }

    #[Test]
    public function it_serializes_component_to_array(): void
    {
        $product = new TestProduct([
            'Id' => 1,
            'ProductNumber' => 'PROD-001',
            'Measurements' => [
                'Length' => 10.5,
                'Width' => 5.2,
                'Height' => 3.1,
                'VolumeOrArea' => 168.84,
            ],
            'Weights' => [
                'Net' => 2.5,
                'Tare' => 0.3,
            ],
        ]);

        $array = $product->toArray();

        // Test Measurements serialization
        $this->assertIsArray($array['Measurements']);
        $this->assertEquals(10.5, $array['Measurements']['Length']);
        $this->assertEquals(5.2, $array['Measurements']['Width']);
        $this->assertEquals(3.1, $array['Measurements']['Height']);
        $this->assertEquals(168.84, $array['Measurements']['VolumeOrArea']);

        // Test Weights serialization
        $this->assertIsArray($array['Weights']);
        $this->assertEquals(2.5, $array['Weights']['Net']);
        $this->assertEquals(0.3, $array['Weights']['Tare']);
    }

    #[Test]
    public function it_allows_setting_entire_component(): void
    {
        $product = new TestProduct([
            'Id' => 1,
            'ProductNumber' => 'PROD-001',
        ]);

        // Create and set new components with real Abacus structure
        $measurements = new TestMeasurements([
            'Length' => 15.0,
            'Width' => 8.0,
            'Height' => 5.0,
            'VolumeOrArea' => 600.0,
        ]);

        $weights = new TestWeights([
            'Net' => 5.5,
            'Tare' => 0.5,
            'SpecificWeight' => 1.2,
        ]);

        $product->Measurements = $measurements;
        $product->Weights = $weights;

        $this->assertInstanceOf(TestMeasurements::class, $product->Measurements);
        $this->assertEquals(15.0, $product->Measurements->Length);
        $this->assertEquals(8.0, $product->Measurements->Width);
        $this->assertEquals(5.0, $product->Measurements->Height);

        $this->assertInstanceOf(TestWeights::class, $product->Weights);
        $this->assertEquals(5.5, $product->Weights->Net);
        $this->assertEquals(0.5, $product->Weights->Tare);
    }

    #[Test]
    public function it_converts_component_to_array_when_set(): void
    {
        $product = new TestProduct([
            'Id' => 1,
            'ProductNumber' => 'PROD-001',
        ]);

        $measurements = new TestMeasurements([
            'Length' => 20.0,
            'Width' => 10.0,
            'Diameter' => 3.5,
        ]);

        $product->Measurements = $measurements;

        // Verify it serializes properly
        $array = $product->toArray();
        $this->assertIsArray($array['Measurements']);
        $this->assertEquals(20.0, $array['Measurements']['Length']);
        $this->assertEquals(10.0, $array['Measurements']['Width']);
        $this->assertEquals(3.5, $array['Measurements']['Diameter']);
    }

    #[Test]
    public function it_handles_null_component_values(): void
    {
        $product = new TestProduct([
            'Id' => 1,
            'Measurements' => null,
        ]);

        $this->assertNull($product->Measurements);
    }

    #[Test]
    public function it_handles_empty_component_arrays(): void
    {
        $product = new TestProduct([
            'Id' => 1,
            'Measurements' => [],
        ]);

        $this->assertInstanceOf(TestMeasurements::class, $product->Measurements);
        $this->assertNull($product->Measurements->Length);
    }
}
