<?php

namespace Contoweb\AbacusRestOdata;

use Illuminate\Support\Facades\Cache;

class AbacusRestService
{
    protected AbacusRestClient $client;

    public function __construct(AbacusRestClient $client)
    {
        $this->client = $client;
    }

    /**
     * Liste von Entities mit Query-Parametern
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
     * Spezifisches Entity via Primary Key
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
     * Spezifische Property eines Entities
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
     * Entity erstellen
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
     * Entity aktualisieren (PATCH)
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
     * Entity vollständig ersetzen (PUT)
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
     * Entity löschen
     * DELETE /api/entity/v1/mandants/{mandate}/Subjects(2)
     */
    public function delete(string $resource, mixed $id): bool
    {
        $path = $this->client->entityPathWithId($resource, $id);

        $this->client->delete($path);

        return true;
    }

    /**
     * Liste aller verfügbaren Entity-IDs
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
}