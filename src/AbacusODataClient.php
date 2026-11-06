<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Batch\MultipartEncoder;
use Contoweb\AbacusApi\Events\AbacusRequestSent;
use Contoweb\AbacusApi\Exceptions\AbacusAuthenticationException;
use Contoweb\AbacusApi\Exceptions\AbacusRateLimitException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Request;

class AbacusODataClient extends AbacusClient
{
    /**
     * Follow OData nextLink for pagination
     * Accepts full URL from @odata.nextLink
     *
     * @throws RequestException
     * @throws ConnectionException
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     */
    public function getNextLink(string $url): Response
    {
        return $this->callWithTokenRefresh(function () use ($url) {

            event(new AbacusRequestSent(Request::METHOD_GET, $url));

            return Http::withToken($this->getAccessToken())
                ->withHeaders(['Accept' => 'application/json'])
                ->timeout(30)
                ->get($url);
        })->throw($this->toException());
    }

    /**
     * Upload raw content to the temporary file store
     *
     * @param  string  $content  The raw file content
     * @param  string  $contentType  Content type of the upload (Abacus detects the file type itself)
     *
     * @throws RequestException
     * @throws ConnectionException
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     */
    public function fileUpload(string $content, string $contentType = 'application/octet-stream'): Response
    {
        return $this->callWithTokenRefresh(function () use ($content, $contentType) {
            $path = $this->fileStorePath();
            event(new AbacusRequestSent(Request::METHOD_POST, $path));

            return $this
                ->client()
                ->withBody($content, $contentType)
                ->post($path);
        })->throw($this->toException());
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
     * Path of the temporary file store endpoint.
     */
    public function fileStorePath(): string
    {
        return '/api/file-store/v1/user';
    }

    /**
     * Send batch request with multiple operations
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws AbacusAuthenticationException
     */
    public function sendBatch(string $path, string $body): Response
    {
        return $this->callWithTokenRefresh(function () use ($body, $path) {

            event(new AbacusRequestSent(Request::METHOD_POST, $path, [$body]));

            return $this->client()
                ->withBody($body, MultipartEncoder::getContentType())
                ->post($path);
        })->throw($this->toException());
    }
}
