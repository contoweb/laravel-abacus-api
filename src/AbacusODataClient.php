<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Batch\MultipartEncoder;
use Contoweb\AbacusApi\Events\AbacusRequestSend;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AbacusODataClient extends AbacusClient
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
        return $this->getEntityBasePath()."/{$this->mandate}/{$resource}";
    }

    /**
     * Path for single entity with ID
     */
    public function entityPathWithId(string $resource, mixed $id): string
    {
        return $this->getEntityBasePath()."/{$this->mandate}/{$resource}({$id})";
    }

    /**
     * Path for entity property
     */
    public function entityPropertyPath(string $resource, mixed $id, string $property): string
    {
        return $this->getEntityBasePath()."/{$this->mandate}/{$resource}({$id})/{$property}";
    }

    /**
     * Metadata path
     */
    public function metadataPath(): string
    {
        return $this->getEntityBasePath()."/{$this->mandate}/\$metadata";
    }

    /**
     * Entity list path
     */
    public function entitiesPath(): string
    {
        return $this->getEntityBasePath()."/{$this->mandate}/";
    }

    /**
     * Batch path
     */
    public function batchPath(): string
    {
        return $this->getEntityBasePath()."/{$this->mandate}/\$batch";
    }

    /**
     * Send batch request with multiple operations
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function sendBatch(string $path, string $body): Response
    {

        event(new AbacusRequestSend('POST', $path, [$body]));

        return $this->callWithTokenRefresh(function () use ($body, $path) {
            return $this->client()
                ->withBody($body, MultipartEncoder::getContentType())
                ->post($path);
        })->throw();
    }
}
