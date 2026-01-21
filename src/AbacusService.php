<?php

namespace Contoweb\AbacusApi;

use Illuminate\Support\Facades\Cache;

class AbacusService
{
    protected AbacusClient $client;

    public function __construct(AbacusClient $client)
    {
        $this->client = $client;
    }

    /**
     * List of entities with query parameters
     * GET /api/entity/v1/mandants/{mandate}/Subjects
     */
    public function query(string $resource, array $odataParams = []): array
    {
        $path = $this->client->entityPath($resource);

        return $this->client
            ->get($path, $odataParams)
            ->json();
    }

    /**
     * Query with complete response including metadata (@odata.nextLink, etc.)
     * Useful for pagination
     */
    public function queryWithMetadata(string $resource, array $odataParams = []): array
    {
        $path = $this->client->entityPath($resource);

        return $this->client
            ->get($path, $odataParams)
            ->json();
    }

    /**
     * Fetch next page via @odata.nextLink
     */
    public function getNextPage(string $nextLink): array
    {
        return $this->client
            ->getNextLink($nextLink)
            ->json();
    }

    /**
     * Specific entity via primary key
     * GET /api/entity/v1/mandants/{mandate}/Subjects(2)
     */
    public function find(string $resource, mixed $id): array
    {
        $path = $this->client->entityPathWithId($resource, $id);

        return $this->client
            ->get($path)
            ->json();
    }

    /**
     * Specific property of an entity
     * GET /api/entity/v1/mandants/{mandate}/Subjects(2)/LastName
     */
    public function findProperty(string $resource, mixed $id, string $property): mixed
    {
        $path = $this->client->entityPropertyPath($resource, $id, $property);

        return $this->client
            ->get($path)
            ->json();
    }

    /**
     * Create entity
     * POST /api/entity/v1/mandants/{mandate}/Subjects
     */
    public function create(string $resource, array $data): array
    {
        $path = $this->client->entityPath($resource);

        return $this->client
            ->post($path, $data)
            ->json();
    }

    /**
     * Update entity (PATCH)
     * PATCH /api/entity/v1/mandants/{mandate}/Subjects(2)
     */
    public function update(string $resource, mixed $id, array $data): array
    {
        $path = $this->client->entityPathWithId($resource, $id);

        return $this->client
            ->patch($path, $data)
            ->json();
    }

    /**
     * Replace entity completely (PUT)
     * PUT /api/entity/v1/mandants/{mandate}/Subjects(2)
     */
    public function replace(string $resource, mixed $id, array $data): array
    {
        $path = $this->client->entityPathWithId($resource, $id);

        return $this->client
            ->put($path, $data)
            ->json();
    }

    /**
     * Delete entity
     * DELETE /api/entity/v1/mandants/{mandate}/Subjects(2)
     */
    public function delete(string $resource, mixed $id): bool
    {
        $path = $this->client->entityPathWithId($resource, $id);

        $this->client->delete($path);

        return true;
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
     * Get the underlying client instance
     */
    public function getClient(): AbacusClient
    {
        return $this->client;
    }

    /**
     * Create a new batch request
     */
    public function batch(): BatchRequest
    {
        return new BatchRequest($this->client);
    }
}