<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Batch\PendingBatchRequest;
use Contoweb\AbacusApi\Credentials\AbacusCredentialsProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;

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
     * Metadata abrufen (mit Caching)
     * GET /api/entity/v1/mandants/{mandate}/$metadata
     */
    public function metadata(): string
    {
        $mandate = $this->client->getMandate();

        return Cache::remember("abacus_metadata_{$mandate}", 3600, function () {
            $path = $this->client->metadataPath();

            return $this->client
                ->get($path)
                ->body();
        });
    }

    /**
     * Create a new fluent batch builder.
     *
     * @param  string|null  $name  Optional name for debugging/logging
     *
     * @example
     * ```php
     * $batch = Abacus::newBatch();
     * $batch->add(Customer::batch()->find(123));
     * $results = $batch->send();
     * ```
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
     *
     * @example
     * ```php
     * [$customer, $products] = Abacus::batch(function() {
     *     return [
     *         Customer::find(123),
     *         Product::where('Price', 'gt', 100)->get(),
     *     ];
     * })->send();
     * ```
     */
    public function batch(callable $callback): PendingBatchRequest
    {
        return $this->newBatch()->capture($callback);
    }
}
