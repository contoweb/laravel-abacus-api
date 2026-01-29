<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\AbacusODataBatchQueryBuilder;
use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\Batch\BatchRequestItem;
use Contoweb\AbacusApi\Tests\Fixtures\TestSubject;
use Contoweb\AbacusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AbacusODataBatchQueryBuilderTest extends TestCase
{
    protected AbacusODataClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new AbacusODataClient();
    }

    #[Test]
    public function it_creates_get_batch_request_item(): void
    {
        $builder = new AbacusODataBatchQueryBuilder($this->client, 'Subjects', TestSubject::class);

        $item = $builder->get();

        $this->assertInstanceOf(BatchRequestItem::class, $item);
        $this->assertEquals('GET', $item->method);
        $this->assertEquals(TestSubject::class, $item->modelClass);
        $this->assertStringContainsString('Subjects', $item->path);
        $this->assertNull($item->body);
    }

    #[Test]
    public function it_creates_get_batch_request_item_with_query_parameters(): void
    {
        $builder = new AbacusODataBatchQueryBuilder($this->client, 'Subjects', TestSubject::class);

        $item = $builder
            ->where('IsActive', 'eq', true)
            ->select(['Id', 'Name'])
            ->top(10)
            ->get();

        $this->assertEquals('GET', $item->method);
        $this->assertStringContainsString('$filter=', $item->path);
        $this->assertStringContainsString('$select=', $item->path);
        $this->assertStringContainsString('$top=', $item->path);
    }

    #[Test]
    public function it_creates_find_batch_request_item_with_simple_id(): void
    {
        $builder = new AbacusODataBatchQueryBuilder($this->client, 'Subjects', TestSubject::class);

        $item = $builder->find(42);

        $this->assertInstanceOf(BatchRequestItem::class, $item);
        $this->assertEquals('GET', $item->method);
        $this->assertStringContainsString('Subjects(42)', $item->path);
        $this->assertNull($item->body);
    }

    #[Test]
    public function it_creates_find_batch_request_item_with_composite_key(): void
    {
        $builder = new AbacusODataBatchQueryBuilder($this->client, 'Products', TestSubject::class);

        $item = $builder->find([
            'BatchNumber' => '5436',
            'ProductId' => 12276,
            'VariantId' => 0,
        ]);

        $this->assertEquals('GET', $item->method);
        $this->assertStringContainsString("BatchNumber='5436'", $item->path);
        $this->assertStringContainsString('ProductId=12276', $item->path);
        $this->assertStringContainsString('VariantId=0', $item->path);
    }

    #[Test]
    public function it_creates_find_batch_request_item_with_select(): void
    {
        $builder = new AbacusODataBatchQueryBuilder($this->client, 'Subjects', TestSubject::class);

        $item = $builder
            ->select(['Id', 'Name'])
            ->find(42);

        $this->assertStringContainsString('Subjects(42)', $item->path);
        $this->assertStringContainsString('$select=', $item->path);
    }

    #[Test]
    public function it_creates_create_batch_request_item(): void
    {
        $builder = new AbacusODataBatchQueryBuilder($this->client, 'Subjects', TestSubject::class);

        $data = ['Name' => 'Test', 'Email' => 'test@example.com'];
        $item = $builder->create($data);

        $this->assertInstanceOf(BatchRequestItem::class, $item);
        $this->assertEquals('POST', $item->method);
        $this->assertStringContainsString('Subjects', $item->path);
        $this->assertEquals($data, $item->body);
    }

    #[Test]
    public function it_creates_delete_batch_request_item_with_simple_id(): void
    {
        $builder = new AbacusODataBatchQueryBuilder($this->client, 'Subjects', TestSubject::class);

        $item = $builder->delete(42);

        $this->assertInstanceOf(BatchRequestItem::class, $item);
        $this->assertEquals('DELETE', $item->method);
        $this->assertStringContainsString('Subjects(42)', $item->path);
        $this->assertNull($item->body);
    }

    #[Test]
    public function it_creates_delete_batch_request_item_with_composite_key(): void
    {
        $builder = new AbacusODataBatchQueryBuilder($this->client, 'Products', TestSubject::class);

        $item = $builder->delete([
            'BatchNumber' => '5436',
            'ProductId' => 12276,
        ]);

        $this->assertEquals('DELETE', $item->method);
        $this->assertStringContainsString("BatchNumber='5436'", $item->path);
        $this->assertStringContainsString('ProductId=12276', $item->path);
    }

    #[Test]
    public function it_creates_update_batch_request_item_with_simple_id(): void
    {
        $builder = new AbacusODataBatchQueryBuilder($this->client, 'Subjects', TestSubject::class);

        $data = ['Name' => 'Updated'];
        $item = $builder->update(42, $data);

        $this->assertInstanceOf(BatchRequestItem::class, $item);
        $this->assertEquals('PATCH', $item->method);
        $this->assertStringContainsString('Subjects(42)', $item->path);
        $this->assertEquals($data, $item->body);
    }

    #[Test]
    public function it_creates_update_batch_request_item_with_composite_key(): void
    {
        $builder = new AbacusODataBatchQueryBuilder($this->client, 'Products', TestSubject::class);

        $data = ['Name' => 'Updated'];
        $item = $builder->update([
            'BatchNumber' => '5436',
            'ProductId' => 12276,
        ], $data);

        $this->assertEquals('PATCH', $item->method);
        $this->assertStringContainsString("BatchNumber='5436'", $item->path);
        $this->assertStringContainsString('ProductId=12276', $item->path);
        $this->assertEquals($data, $item->body);
    }

    #[Test]
    public function it_supports_fluent_interface_for_query_methods(): void
    {
        $builder = new AbacusODataBatchQueryBuilder($this->client, 'Subjects', TestSubject::class);

        $result = $builder
            ->where('IsActive', 'eq', true)
            ->select(['Id', 'Name'])
            ->orderBy('Name', 'asc')
            ->top(10)
            ->expand('Addresses');

        $this->assertInstanceOf(AbacusODataBatchQueryBuilder::class, $result);
    }

    #[Test]
    public function it_exposes_to_odata_query_via_trait(): void
    {
        $builder = new AbacusODataBatchQueryBuilder($this->client, 'Subjects', TestSubject::class);
        $builder->where('Status', 'eq', 'Active')
                ->select(['Id', 'Name']);

        $query = $builder->toODataQuery();

        $this->assertEquals("Status eq 'Active'", $query['$filter']);
        $this->assertEquals('Id,Name', $query['$select']);
    }
}
