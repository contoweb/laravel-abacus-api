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
