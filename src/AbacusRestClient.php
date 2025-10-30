<?php

namespace Contoweb\AbacusRestOdata;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class AbacusRestClient
{
    private string $baseUrl;
    private string $mandate;
    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $this->baseUrl = config('abacus-rest-odata.rest_api.url');
        $this->mandate = config('abacus-rest-odata.rest_api.mandate');
        $this->clientId = config('abacus-rest-odata.rest_api.client_id');
        $this->clientSecret = config('abacus-rest-odata.rest_api.client_secret');
    }

    /**
     * HTTP Client mit konfigurierten Headers erstellen
     */
    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->getBaseUrl())
                   ->withHeaders([
                       'X-Abacus-Client-Id' => $this->clientId,
                       'X-Abacus-Client-Secret' => $this->clientSecret,
                       'Accept' => 'application/json',
                   ])
                   ->timeout(30);
    }

    /**
     * Base URL mit https:// Präfix
     */
    protected function getBaseUrl(): string
    {
        $url = $this->baseUrl;

        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    /**
     * GET Request
     */
    public function get(string $path, array $query = []): Response
    {
        return $this->client()
                    ->get($path, $query)
                    ->throw();
    }

    /**
     * POST Request
     */
    public function post(string $path, array $data = []): Response
    {
        return $this->client()
                    ->post($path, $data)
                    ->throw();
    }

    /**
     * PATCH Request
     */
    public function patch(string $path, array $data = []): Response
    {
        return $this->client()
                    ->patch($path, $data)
                    ->throw();
    }

    /**
     * PUT Request
     */
    public function put(string $path, array $data = []): Response
    {
        return $this->client()
                    ->put($path, $data)
                    ->throw();
    }

    /**
     * DELETE Request
     */
    public function delete(string $path): Response
    {
        return $this->client()
                    ->delete($path)
                    ->throw();
    }

    /**
     * Mandate ID abrufen
     */
    public function getMandate(): string
    {
        return $this->mandate;
    }

    /**
     * Vollständigen Entity-Pfad erstellen
     */
    public function entityPath(string $resource): string
    {
        return "/api/entity/v1/mandants/{$this->mandate}/{$resource}";
    }

    /**
     * Pfad für einzelne Entity mit ID
     */
    public function entityPathWithId(string $resource, mixed $id): string
    {
        return "/api/entity/v1/mandants/{$this->mandate}/{$resource}({$id})";
    }

    /**
     * Pfad für Entity-Property
     */
    public function entityPropertyPath(string $resource, mixed $id, string $property): string
    {
        return "/api/entity/v1/mandants/{$this->mandate}/{$resource}({$id})/{$property}";
    }

    /**
     * Metadata-Pfad
     */
    public function metadataPath(): string
    {
        return "/api/entity/v1/mandants/{$this->mandate}/\$metadata";
    }

    /**
     * Entity-Liste-Pfad
     */
    public function entitiesPath(): string
    {
        return "/api/entity/v1/mandants/{$this->mandate}/";
    }
}