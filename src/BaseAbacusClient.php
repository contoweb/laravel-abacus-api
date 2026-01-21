<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Batch\MultipartDecoder;
use Contoweb\AbacusApi\Batch\MultipartEncoder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;

abstract class BaseAbacusClient
{
    const CACHE_NAMESPACE = 'abacus_access_token:';

    protected string $baseUrl;
    protected string $mandate;
    protected string $clientId;
    protected string $clientSecret;
    protected string $apiVersion;

    public function __construct(
        ?string $baseUrl = null,
        ?string $mandate = null,
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $apiVersion = null
    ) {
        $this->baseUrl      = $baseUrl      ?? config('abacus-api.rest_api.url');
        $this->mandate      = $mandate      ?? config('abacus-api.rest_api.mandate');
        $this->clientId     = $clientId     ?? config('abacus-api.rest_api.client_id');
        $this->clientSecret = $clientSecret ?? config('abacus-api.rest_api.client_secret');
        $this->apiVersion   = $apiVersion   ?? config('abacus-api.rest_api.version');
    }

    /*
     * HTTP client with OAuth2 bearer token
     */
    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->getBaseUrl())
                   ->withToken($this->getAccessToken())
                   ->withHeaders([
                       'Accept' => 'application/json',
                   ])
                   ->timeout(30);
    }

    /*
     * Base URL with https:// prefix
     */
    protected function getBaseUrl(): string
    {
        $url = $this->baseUrl;

        if ( ! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'https://' . $url;
        }

        return $url;
    }

    /*
     * Generate a unique cache key for the access token
     */
    protected function getCacheKey(): string
    {
        return self::CACHE_NAMESPACE . md5($this->baseUrl . $this->clientId . $this->mandate);
    }

    /*
     * Get a cached access token or fetch a new one if not available
     */
    protected function getAccessToken(): string
    {
        $cachedToken = Cache::get($this->getCacheKey());

        if ($cachedToken !== null) {
            return $cachedToken;
        }

        return $this->fetchFreshAccessToken();
    }

    /*
     * Get the OAuth token endpoint path
     */
    protected function getTokenEndpoint(): string
    {
        return "/oauth/oauth2/{$this->apiVersion}/token";
    }

    /*
     * Force fetch a fresh access token and update cache
     */
    protected function fetchFreshAccessToken(): string
    {
        $response = Http::asForm()
                        ->post($this->getBaseUrl() . $this->getTokenEndpoint(), [
                            'grant_type'    => 'client_credentials',
                            'client_id'     => $this->clientId,
                            'client_secret' => $this->clientSecret,
                        ]);

        if ($response->failed() || $response->json('access_token') === null) {
            throw new \RuntimeException('Cannot fetch access token from API.');
        }

        $accessToken   = $response->json('access_token');
        $tokenLifetime = (int)$response->json('expires_in');

        /* Cache token with 10 second buffer before expiration */
        Cache::put($this->getCacheKey(), $accessToken, $tokenLifetime - 10);

        return $accessToken;
    }

    /*
     * Retries the HTTP call if the response status is 401
     * This can happen if the token became invalid
     */
    protected function callWithTokenRefresh(callable $callback): Response
    {
        $response = $callback();

        if ( ! ($response instanceof Response)) {
            throw new \TypeError('Callback function must return an instance of ' . Response::class);
        }

        if ($response->status() === 401) {
            Cache::forget($this->getCacheKey());
            $response = $callback();
        }

        return $response;
    }

    /**
     * GET Request
     *
     * @throws RequestException|ConnectionException
     */
    public function get(string $path, array $query = []): Response
    {
        return $this->callWithTokenRefresh(function () use ($path, $query) {
            return $this->client()->get($path, $query);
        })->throw();
    }

    /**
     * POST Request
     *
     * @throws RequestException|ConnectionException
     */
    public function post(string $path, array $data = []): Response
    {
        return $this->callWithTokenRefresh(function () use ($path, $data) {
            return $this->client()->post($path, $data);
        })->throw();
    }

    /**
     * PATCH Request
     *
     * @throws RequestException|ConnectionException
     */
    public function patch(string $path, array $data = []): Response
    {
        return $this->callWithTokenRefresh(function () use ($path, $data) {
            return $this->client()->patch($path, $data);
        })->throw();
    }

    /**
     * PUT Request
     *
     * @throws RequestException|ConnectionException
     */
    public function put(string $path, array $data = []): Response
    {
        return $this->callWithTokenRefresh(function () use ($path, $data) {
            return $this->client()->put($path, $data);
        })->throw();
    }

    /**
     * DELETE Request
     *
     * @throws RequestException|ConnectionException
     */
    public function delete(string $path): Response
    {
        return $this->callWithTokenRefresh(function () use ($path) {
            return $this->client()->delete($path);
        })->throw();
    }

    /**
     * Get mandate ID
     */
    public function getMandate(): string
    {
        return $this->mandate;
    }

    /**
     * Get base URL for raw access
     */
    public function getUrl(): string
    {
        return $this->getBaseUrl();
    }

    /**
     * Send batch request with multiple operations
     *
     * @param array $requests Array of ['method' => string, 'path' => string, 'body' => array|null]
     * @return array Array of response results
     * @throws RequestException|ConnectionException
     */
    public function sendBatch(array $requests): array
    {
        /* Encode requests into multipart/mixed format */
        $body = MultipartEncoder::encode($requests);

        /* Send batch request */
        $batchPath = $this->getEntityBasePath() . '/' . $this->mandate . '/$batch';

        $response = $this->callWithTokenRefresh(function () use ($body, $batchPath) {
            return Http::withToken($this->getAccessToken())
                ->timeout(30)
                ->withBody($body, MultipartEncoder::getContentType())
                ->post($this->getBaseUrl() . $batchPath);
        })->throw();

        /* Decode multipart response */
        return MultipartDecoder::decode($response->body(), MultipartEncoder::getBoundary());
    }
}
