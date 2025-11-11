<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\Tests\TestCase;
use Contoweb\AbacusApi\AbacusQueryBuilder;
use Contoweb\AbacusApi\Enums\ODataOperator;
use Contoweb\AbacusApi\Models\AbacusModel;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

/* Test model for testing */
class TestSubject extends AbacusModel
{
    protected static string $resource = 'Subjects';
}

class AbacusModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/oauth/token' => Http::response([
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

        $this->assertInstanceOf(AbacusQueryBuilder::class, $query);
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
            '*/oauth/token' => Http::response([
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

        $this->assertInstanceOf(AbacusQueryBuilder::class, $query);
    }

    #[Test]
    public function it_starts_where_query_with_enum_operator(): void
    {
        $query = TestSubject::where('Age', ODataOperator::GREATER_THAN, 18);

        $this->assertInstanceOf(AbacusQueryBuilder::class, $query);
    }

    #[Test]
    public function it_starts_select_query(): void
    {
        $query = TestSubject::select(['Id', 'Name']);

        $this->assertInstanceOf(AbacusQueryBuilder::class, $query);
    }

    #[Test]
    public function it_starts_top_query(): void
    {
        $query = TestSubject::top(10);

        $this->assertInstanceOf(AbacusQueryBuilder::class, $query);
    }

    #[Test]
    public function it_starts_order_by_query(): void
    {
        $query = TestSubject::orderBy('Name', 'desc');

        $this->assertInstanceOf(AbacusQueryBuilder::class, $query);
    }

    #[Test]
    public function it_starts_expand_query(): void
    {
        $query = TestSubject::expand(['Addresses', 'Contacts']);

        $this->assertInstanceOf(AbacusQueryBuilder::class, $query);
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

        $subject = TestSubject::create([
            'Name' => 'New Subject',
            'Email' => 'new@example.com',
        ]);

        $this->assertInstanceOf(TestSubject::class, $subject);
        $this->assertEquals(100, $subject->Id);
        $this->assertEquals('New Subject', $subject->Name);
    }

    #[Test]
    public function it_saves_new_entity(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects' => Http::response([
                'Id' => 200,
                'Name' => 'Saved Subject',
            ], 201),
        ]);

        $subject = new TestSubject(['Name' => 'Saved Subject']);
        $result = $subject->save();

        $this->assertInstanceOf(TestSubject::class, $result);
        $this->assertEquals(200, $subject->Id);
        $this->assertFalse($subject->isDirty());
    }

    #[Test]
    public function it_saves_existing_entity_with_updates(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(50)' => Http::response([
                'Id' => 50,
                'Name' => 'Updated Name',
                'Email' => 'old@example.com',
            ], 200),
        ]);

        $subject = new TestSubject([
            'Id' => 50,
            'Name' => 'Old Name',
            'Email' => 'old@example.com',
        ]);

        $subject->Name = 'Updated Name';
        $subject->save();

        $this->assertEquals('Updated Name', $subject->Name);
        $this->assertFalse($subject->isDirty());
    }

    #[Test]
    public function it_updates_entity_with_array(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(75)' => Http::response([
                'Id' => 75,
                'Name' => 'Array Updated',
                'Email' => 'array@example.com',
            ], 200),
        ]);

        $subject = new TestSubject(['Id' => 75, 'Name' => 'Original']);
        $subject->update(['Name' => 'Array Updated', 'Email' => 'array@example.com']);

        $this->assertEquals('Array Updated', $subject->Name);
        $this->assertEquals('array@example.com', $subject->Email);
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

        $subject = new TestSubject(['Id' => 42, 'Name' => 'To Delete']);
        $result = $subject->delete();

        $this->assertTrue($result);
    }

    #[Test]
    public function it_returns_false_when_deleting_without_id(): void
    {
        $subject = new TestSubject(['Name' => 'No ID']);
        $result = $subject->delete();

        $this->assertFalse($result);
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
    public function it_converts_to_json(): void
    {
        $subject = new TestSubject(['Id' => 1, 'Name' => 'Test']);
        $json = $subject->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertEquals(1, $decoded['Id']);
        $this->assertEquals('Test', $decoded['Name']);
    }

    #[Test]
    public function it_detects_dirty_state(): void
    {
        $subject = new TestSubject(['Name' => 'Original']);

        $this->assertFalse($subject->isDirty());

        $subject->Name = 'Modified';

        $this->assertTrue($subject->isDirty());
        $this->assertTrue($subject->isDirty('Name'));
    }

    #[Test]
    public function it_detects_dirty_specific_attribute(): void
    {
        $subject = new TestSubject(['Name' => 'Original', 'Email' => 'test@example.com']);

        $subject->Name = 'Modified';

        $this->assertTrue($subject->isDirty('Name'));
        $this->assertFalse($subject->isDirty('Email'));
    }

    #[Test]
    public function it_gets_dirty_attributes(): void
    {
        $subject = new TestSubject([
            'Id' => 1,
            'Name' => 'Original',
            'Email' => 'original@example.com',
        ]);

        $subject->Name = 'Modified';
        $subject->Email = 'modified@example.com';

        $dirty = $subject->getDirty();

        $this->assertArrayHasKey('Name', $dirty);
        $this->assertArrayHasKey('Email', $dirty);
        $this->assertArrayNotHasKey('Id', $dirty);
        $this->assertEquals('Modified', $dirty['Name']);
        $this->assertEquals('modified@example.com', $dirty['Email']);
    }

    #[Test]
    public function it_refreshes_entity_from_api(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(42)' => Http::response([
                'Id' => 42,
                'Name' => 'Fresh Data',
                'Email' => 'fresh@example.com',
            ], 200),
        ]);

        $subject = new TestSubject(['Id' => 42, 'Name' => 'Stale']);
        $fresh = $subject->fresh();

        $this->assertInstanceOf(TestSubject::class, $fresh);
        $this->assertEquals('Fresh Data', $fresh->Name);
    }

    #[Test]
    public function it_returns_null_when_refreshing_without_id(): void
    {
        $subject = new TestSubject(['Name' => 'No ID']);
        $fresh = $subject->fresh();

        $this->assertNull($fresh);
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
        $subject = new TestSubject();
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
    public function it_gets_resource_name(): void
    {
        $this->assertEquals('Subjects', TestSubject::getResource());
    }

    #[Test]
    public function it_saves_only_dirty_attributes(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/entity/v1/mandants/test-mandate/Subjects(10)' => Http::response([
                'Id' => 10,
                'Name' => 'Updated',
                'Email' => 'unchanged@example.com',
            ], 200),
        ]);

        $subject = new TestSubject([
            'Id' => 10,
            'Name' => 'Original',
            'Email' => 'unchanged@example.com',
        ]);

        $subject->Name = 'Updated';

        /* Only Name should be sent to API */
        $subject->save();

        Http::assertSent(function ($request) {
            $data = $request->data();
            return isset($data['Name']) &&
                   $data['Name'] === 'Updated' &&
                   !isset($data['Email']); /* Email shouldn't be sent as it's not dirty */
        });
    }

    #[Test]
    public function it_returns_model_instance_when_using_where_first(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
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
    public function it_returns_model_collection_when_using_where_get(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response([
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

        $subjects = TestSubject::where('IsActive', 'eq', true)->get();

        $this->assertCount(2, $subjects);
        $this->assertInstanceOf(TestSubject::class, $subjects->first());
        $this->assertInstanceOf(TestSubject::class, $subjects->last());
        $this->assertEquals('First', $subjects->first()->Name);
        $this->assertEquals('Second', $subjects->last()->Name);
    }
}
