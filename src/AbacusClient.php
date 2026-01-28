<?php

namespace Contoweb\AbacusApi;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class AbacusClient extends BaseAbacusClient
{
    /**
     * Follow OData nextLink for pagination
     * Accepts full URL from @odata.nextLink
     *
     * @throws RequestException|ConnectionException
     */
    public function getNextLink(string $url): Response
    {
        return $this->callWithTokenRefresh(function () use ($url) {
            return Http::withToken($this->getAccessToken())
                       ->withHeaders(['Accept' => 'application/json'])
                       ->timeout(30)
                       ->get($url);
        })->throw();
    }

    /**
     * Get the base path for entity API
     */
    protected function getEntityBasePath(): string
    {
        return "/api/entity/{$this->apiVersion}/mandants";
    }

    /**
     * Create complete entity path
     */
    public function entityPath(string $resource): string
    {
        return $this->getEntityBasePath() . "/{$this->mandate}/{$resource}";
    }

    /**
     * Path for single entity with ID
     */
    public function entityPathWithId(string $resource, mixed $id): string
    {
        return $this->getEntityBasePath() . "/{$this->mandate}/{$resource}({$id})";
    }

    /**
     * Path for entity property
     */
    public function entityPropertyPath(string $resource, mixed $id, string $property): string
    {
        return $this->getEntityBasePath() . "/{$this->mandate}/{$resource}({$id})/{$property}";
    }

    /**
     * Metadata path
     */
    public function metadataPath(): string
    {
        return $this->getEntityBasePath() . "/{$this->mandate}/\$metadata";
    }

    /**
     * Entity list path
     */
    public function entitiesPath(): string
    {
        return $this->getEntityBasePath() . "/{$this->mandate}/";
    }

    public function batchPath(): string
    {
        return $this->getEntityBasePath() . "/{$this->mandate}/\$batch";
    }
}