<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;

class RequestLoggingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function createClientWithMockLogger(): array
    {
        $logger = $this->createMock(LoggerInterface::class);
        $client = new AbacusODataClient(logger: $logger);

        return [$client, $logger];
    }

    protected function mockTokenAndApiResponse(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response(['value' => []], 200),
        ]);
    }

    #[Test]
    public function it_logs_get_request(): void
    {
        $this->mockTokenAndApiResponse();

        [$client, $logger] = $this->createClientWithMockLogger();

        $logger->expects($this->once())->method('info');

        $client->get('/api/entities');
    }

    #[Test]
    public function it_logs_post_request(): void
    {
        $this->mockTokenAndApiResponse();

        [$client, $logger] = $this->createClientWithMockLogger();

        $logger->expects($this->once())->method('info');

        $client->post('/api/entities', ['Name' => 'Test']);
    }

    #[Test]
    public function it_logs_patch_request(): void
    {
        $this->mockTokenAndApiResponse();

        [$client, $logger] = $this->createClientWithMockLogger();

        $logger->expects($this->once())->method('info');

        $client->patch('/api/entities/1', ['Name' => 'Updated']);
    }

    #[Test]
    public function it_logs_put_request(): void
    {
        $this->mockTokenAndApiResponse();

        [$client, $logger] = $this->createClientWithMockLogger();

        $logger->expects($this->once())->method('info');

        $client->put('/api/entities/1', ['Name' => 'Replaced']);
    }

    #[Test]
    public function it_logs_delete_request(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response(null, 204),
        ]);

        [$client, $logger] = $this->createClientWithMockLogger();

        $logger->expects($this->once())->method('info');

        $client->delete('/api/entities/1');
    }

    #[Test]
    public function it_logs_batch_request(): void
    {
        $this->mockTokenAndApiResponse();

        [$client, $logger] = $this->createClientWithMockLogger();

        $logger->expects($this->once())->method('info');

        $client->sendBatch($client->batchPath(), 'batch-body-content');
    }

    #[Test]
    public function it_does_not_log_when_using_null_logger(): void
    {
        $this->mockTokenAndApiResponse();

        /* Client without logger uses NullLogger by default */
        $client = new AbacusODataClient();

        /* This should not throw - NullLogger silently ignores all log calls */
        $response = $client->get('/api/entities');

        $this->assertEquals(200, $response->status());
    }
}
