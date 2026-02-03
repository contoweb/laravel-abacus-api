<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Batch\BatchRequest;
use Contoweb\AbacusApi\Batch\BatchRequestItem;
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
     *
     * @param  array{
     *     base_url?: string,
     *     mandate?: string,
     *     client_id?: string,
     *     client_secret?: string,
     *     api_version?: string
     * }  $options
     */
    public static function client(array $options = []): AbacusODataClient
    {
        return new AbacusODataClient(
            baseUrl: $options['base_url'] ?? null,
            mandate: $options['mandate'] ?? null,
            clientId: $options['client_id'] ?? null,
            clientSecret: $options['client_secret'] ?? null,
            apiVersion: $options['api_version'] ?? null,
            logger: app('abacus.logger') ?? null,
        );
    }

    /**
     * List of all available entity IDs
     * GET /api/entity/v1/mandants/{mandate}/
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
     * Create a new batch request
     */
    public function batch(BatchRequestItem ...$requests): BatchRequest
    {
        return new BatchRequest($this->client, ...$requests);
    }
}
