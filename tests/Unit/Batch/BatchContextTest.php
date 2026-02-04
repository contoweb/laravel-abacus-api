<?php

namespace Contoweb\AbacusApi\Tests\Unit\Batch;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\Batch\BatchContext;
use Contoweb\AbacusApi\Batch\PendingBatchRequest;
use Contoweb\AbacusApi\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class BatchContextTest extends TestCase
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
    public function it_can_set_and_get_active_batch(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);
        $batch = new PendingBatchRequest($client);

        BatchContext::set($batch);

        $this->assertSame($batch, BatchContext::get());
    }

    #[Test]
    public function it_returns_null_when_no_batch_is_set(): void
    {
        $this->assertNull(BatchContext::get());
    }

    #[Test]
    public function it_can_clear_the_active_batch(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);
        $batch = new PendingBatchRequest($client);

        BatchContext::set($batch);
        $this->assertTrue(BatchContext::has());

        BatchContext::clear();
        $this->assertFalse(BatchContext::has());
        $this->assertNull(BatchContext::get());
    }

    #[Test]
    public function has_returns_true_when_batch_is_set(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);
        $batch = new PendingBatchRequest($client);

        BatchContext::set($batch);
        $this->assertTrue(BatchContext::has());
    }

    #[Test]
    public function has_returns_false_when_no_batch_is_set(): void
    {
        $this->assertFalse(BatchContext::has());
    }

    #[Test]
    public function in_batch_mode_is_alias_for_has(): void
    {
        $this->assertFalse(BatchContext::inBatchMode());

        $client = Mockery::mock(AbacusODataClient::class);
        $batch = new PendingBatchRequest($client);

        BatchContext::set($batch);
        $this->assertTrue(BatchContext::inBatchMode());
    }

    #[Test]
    public function it_can_handle_multiple_set_clear_cycles(): void
    {
        $client = Mockery::mock(AbacusODataClient::class);

        $batch1 = new PendingBatchRequest($client);
        BatchContext::set($batch1);
        $this->assertSame($batch1, BatchContext::get());

        BatchContext::clear();
        $this->assertNull(BatchContext::get());

        $batch2 = new PendingBatchRequest($client);
        BatchContext::set($batch2);
        $this->assertSame($batch2, BatchContext::get());

        BatchContext::clear();
        $this->assertNull(BatchContext::get());
    }
}
