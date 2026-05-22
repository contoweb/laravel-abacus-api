<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Batch\PendingBatchRequest;
use Contoweb\AbacusApi\Credentials\AbacusCredentialsProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class AbacusService
{
    protected AbacusODataClient $client;

    public function __construct(AbacusODataClient $client)
    {
        $this->client = $client;
    }

    /**
     * Create a new AbacusODataClient instance with custom options.
     * Useful for multi-tenant scenarios where credentials vary per request.
     */
    public static function client(AbacusCredentialsProvider $credentialsProvider): AbacusODataClient
    {
        return new AbacusODataClient($credentialsProvider);
    }

    /**
     * List of all available entity IDs
     * GET /api/entity/v1/mandants/{mandate}/
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    public function listEntityIds(): array
    {
        $path = $this->client->entitiesPath();

        return $this->client
            ->get($path)
            ->json();
    }

    /**
     * Fetch the OData metadata containing all entities, complex types and enums.
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    public function metadata(): array
    {
        $path = $this->client->metadataPath();

        return $this->client
            ->get($path)
            ->json();
    }

    /**
     * Create a new fluent batch builder.
     *
     * @param  string|null  $name  Optional name for debugging/logging
     */
    public function newBatch(?string $name = null): PendingBatchRequest
    {
        return new PendingBatchRequest($this->client, $name);
    }

    /**
     * Create a batch with closure (convenience method).
     *
     * This is a shorthand for creating a batch and immediately
     * calling capture() on it. The closure should return an array
     * of batch items for destructuring.
     *
     * @param  callable  $callback  Closure that executes queries
     */
    public function batch(callable $callback): PendingBatchRequest
    {
        return $this->newBatch()->capture($callback);
    }
}
