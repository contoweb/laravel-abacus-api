<?php

namespace Contoweb\AbacusApi\Tests\Unit\Batch;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\Batch\BatchContext;
use Contoweb\AbacusApi\Batch\BatchRequestItem;
use Contoweb\AbacusApi\Batch\BatchResponseCollection;
use Contoweb\AbacusApi\Batch\PendingBatchRequest;
use Contoweb\AbacusApi\Tests\TestCase;
use Exception;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class PendingBatchRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BatchContext::clear();
    }

    protected function tearDown(): void
    {
        BatchContext::clear();
        parent::tearDown();
    }

    #[Test]
    public function it_can_add_a_single_item(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);
        $batch = new PendingBatchRequest($client);

        $item = new BatchRequestItem('TestModel', 'GET', '/test', null);
        $batch->add($item);

        $this->assertEquals(1, $batch->count());
        $this->assertFalse($batch->isEmpty());
    }

    #[Test]
    public function it_can_add_multiple_items(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);
        $batch = new PendingBatchRequest($client);

        $item1 = new BatchRequestItem('TestModel', 'GET', '/test1', null);
        $item2 = new BatchRequestItem('TestModel', 'GET', '/test2', null);

        $batch->add($item1)->add($item2);

        $this->assertEquals(2, $batch->count());
    }

    #[Test]
    public function it_can_add_items_with_named_keys(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);
        $batch = new PendingBatchRequest($client);

        $item1 = new BatchRequestItem('TestModel', 'GET', '/test1', null);
        $item2 = new BatchRequestItem('TestModel', 'GET', '/test2', null);

        $batch->add($item1, 'first')->add($item2, 'second');

        $items = $batch->items();
        $this->assertArrayHasKey('first', $items);
        $this->assertArrayHasKey('second', $items);
        $this->assertSame($item1, $items['first']);
        $this->assertSame($item2, $items['second']);
    }

    #[Test]
    public function it_can_add_many_items_at_once(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);
        $batch = new PendingBatchRequest($client);

        $items = [
            new BatchRequestItem('TestModel', 'GET', '/test1', null),
            new BatchRequestItem('TestModel', 'GET', '/test2', null),
            new BatchRequestItem('TestModel', 'GET', '/test3', null),
        ];

        $batch->addMany($items);

        $this->assertEquals(3, $batch->count());
    }

    #[Test]
    public function it_can_add_many_items_with_named_keys(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);
        $batch = new PendingBatchRequest($client);

        $items = [
            'first' => new BatchRequestItem('TestModel', 'GET', '/test1', null),
            'second' => new BatchRequestItem('TestModel', 'GET', '/test2', null),
        ];

        $batch->addMany($items);

        $batchItems = $batch->items();
        $this->assertArrayHasKey('first', $batchItems);
        $this->assertArrayHasKey('second', $batchItems);
    }

    #[Test]
    public function it_can_clear_all_items(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);
        $batch = new PendingBatchRequest($client);

        $item = new BatchRequestItem('TestModel', 'GET', '/test', null);
        $batch->add($item);

        $this->assertEquals(1, $batch->count());

        $batch->clear();

        $this->assertEquals(0, $batch->count());
        $this->assertTrue($batch->isEmpty());
    }

    #[Test]
    public function it_starts_empty(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);
        $batch = new PendingBatchRequest($client);

        $this->assertTrue($batch->isEmpty());
        $this->assertEquals(0, $batch->count());
    }

    #[Test]
    public function it_can_get_all_items(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);
        $batch = new PendingBatchRequest($client);

        $item1 = new BatchRequestItem('TestModel', 'GET', '/test1', null);
        $item2 = new BatchRequestItem('TestModel', 'GET', '/test2', null);

        $batch->add($item1)->add($item2);

        $items = $batch->items();

        $this->assertIsArray($items);
        $this->assertCount(2, $items);
    }

    #[Test]
    public function it_can_set_batch_context_with_capture(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);
        $batch = new PendingBatchRequest($client);

        $this->assertFalse(BatchContext::has());

        $batch->capture(function () {
            $this->assertTrue(BatchContext::has());
        });

        // Context should be cleared after capture
        $this->assertFalse(BatchContext::has());
    }

    #[Test]
    public function it_clears_batch_context_even_if_callback_throws(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);
        $batch = new PendingBatchRequest($client);

        try {
            $batch->capture(function () {
                throw new Exception('Test exception');
            });
            $this->fail('Expected exception was not thrown');
        } catch (Exception $e) {
            // Expected
        }

        // Context should still be cleared
        $this->assertFalse(BatchContext::has());
    }

    #[Test]
    public function it_throws_exception_on_nested_capture(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nested batch captures are not supported');

        $client = Mockery::mock(AbacusODataClient::class);
        $batch1 = new PendingBatchRequest($client);
        $batch2 = new PendingBatchRequest($client);

        $batch1->capture(function () use ($batch2) {
            // This should throw because we're already in a batch context
            $batch2->capture(function () {
                // Should not reach here
            });
        });
    }

    #[Test]
    public function it_can_have_optional_name(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);
        $batch = new PendingBatchRequest($client, 'test-batch');

        $this->assertEquals('test-batch', $batch->getName());
    }

    #[Test]
    public function it_has_null_name_by_default(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);
        $batch = new PendingBatchRequest($client);

        $this->assertNull($batch->getName());
    }

    #[Test]
    public function it_returns_empty_collection_when_sending_empty_batch(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);
        $batch = new PendingBatchRequest($client);

        $results = $batch->send();

        $this->assertInstanceOf(BatchResponseCollection::class, $results);
        $this->assertTrue($results->isEmpty());
    }

    #[Test]
    public function it_supports_multiple_independent_batch_instances(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);

        $batch1 = new PendingBatchRequest($client);
        $batch2 = new PendingBatchRequest($client);

        $item1 = new BatchRequestItem('TestModel', 'GET', '/test1', null);
        $item2 = new BatchRequestItem('TestModel', 'GET', '/test2', null);

        $batch1->add($item1);
        $batch2->add($item2);

        $this->assertEquals(1, $batch1->count());
        $this->assertEquals(1, $batch2->count());
        $this->assertNotSame($batch1->items(), $batch2->items());
    }
}
