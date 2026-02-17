<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\Events\AbacusRequestSent;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class RequestEventTest extends TestCase
{
    protected AbacusODataClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->client = new AbacusODataClient($this->makeCredentialsProvider());
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
    public function it_dispatches_event_on_get_request(): void
    {
        $this->mockTokenAndApiResponse();
        Event::fake([AbacusRequestSent::class]);

        $this->client->get('/api/entities');

        Event::assertDispatched(AbacusRequestSent::class, function (AbacusRequestSent $event) {
            return $event->method === 'GET' && str_contains($event->path, '/api/entities');
        });
    }

    #[Test]
    public function it_dispatches_event_on_post_request(): void
    {
        $this->mockTokenAndApiResponse();
        Event::fake([AbacusRequestSent::class]);

        $this->client->post('/api/entities', ['Name' => 'Test']);

        Event::assertDispatched(AbacusRequestSent::class, function (AbacusRequestSent $event) {
            return $event->method === 'POST'
                && str_contains($event->path, '/api/entities')
                && $event->body === ['Name' => 'Test'];
        });
    }

    #[Test]
    public function it_dispatches_event_on_patch_request(): void
    {
        $this->mockTokenAndApiResponse();
        Event::fake([AbacusRequestSent::class]);

        $this->client->patch('/api/entities/1', ['Name' => 'Updated']);

        Event::assertDispatched(AbacusRequestSent::class, function (AbacusRequestSent $event) {
            return $event->method === 'PATCH'
                && str_contains($event->path, '/api/entities/1')
                && $event->body === ['Name' => 'Updated'];
        });
    }

    #[Test]
    public function it_dispatches_event_on_put_request(): void
    {
        $this->mockTokenAndApiResponse();
        Event::fake([AbacusRequestSent::class]);

        $this->client->put('/api/entities/1', ['Name' => 'Replaced']);

        Event::assertDispatched(AbacusRequestSent::class, function (AbacusRequestSent $event) {
            return $event->method === 'PUT'
                && str_contains($event->path, '/api/entities/1')
                && $event->body === ['Name' => 'Replaced'];
        });
    }

    #[Test]
    public function it_dispatches_event_on_delete_request(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*' => Http::response(null, 204),
        ]);
        Event::fake([AbacusRequestSent::class]);

        $this->client->delete('/api/entities/1');

        Event::assertDispatched(AbacusRequestSent::class, function (AbacusRequestSent $event) {
            return $event->method === 'DELETE'
                && str_contains($event->path, '/api/entities/1');
        });
    }

    #[Test]
    public function it_dispatches_event_on_batch_request(): void
    {
        $this->mockTokenAndApiResponse();
        Event::fake([AbacusRequestSent::class]);

        $this->client->sendBatch($this->client->batchPath(), 'batch-body-content');

        Event::assertDispatched(AbacusRequestSent::class, function (AbacusRequestSent $event) {
            return $event->method === 'POST'
                && $event->body === ['batch-body-content'];
        });
    }
}
