<?php

namespace Contoweb\AbacusApi;

use Closure;
use Contoweb\AbacusApi\Credentials\AbacusCredentialsProvider;
use Contoweb\AbacusApi\Events\AbacusRequestSent;
use Contoweb\AbacusApi\Exceptions\AbacusAuthenticationException;
use Contoweb\AbacusApi\Exceptions\AbacusBadRequestException;
use Contoweb\AbacusApi\Exceptions\AbacusForbiddenException;
use Contoweb\AbacusApi\Exceptions\AbacusRateLimitException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

abstract class AbacusClient
{
    const CACHE_NAMESPACE = 'abacus_access_token:';

    protected string $baseUrl;

    protected string $mandate;

    protected string $clientId;

    protected string $clientSecret;

    protected string $apiVersion;

    public function __construct(
        AbacusCredentialsProvider $credentialsProvider,
    ) {
        $credentials = $credentialsProvider->getCredentials();

        $this->baseUrl = $credentials->baseUrl;
        $this->mandate = $credentials->mandate;
        $this->clientId = $credentials->clientId;
        $this->clientSecret = $credentials->clientSecret;
        $this->apiVersion = $credentials->apiVersion;
    }

    /**
     * HTTP client with OAuth2 bearer token
     *
     * @throws ConnectionException
     * @throws AbacusAuthenticationException
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

    /**
     * Base URL with https:// prefix
     */
    protected function getBaseUrl(): string
    {
        $url = $this->baseUrl;

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            $url = 'https://'.$url;
        }

        return $url;
    }

    /**
     * Generate a unique cache key for the access token
     */
    protected function getCacheKey(): string
    {
        return self::CACHE_NAMESPACE.md5($this->baseUrl.$this->clientId.$this->mandate);
    }

    /**
     * Get a cached access token or fetch a new one if not available
     *
     * @throws AbacusAuthenticationException
     * @throws ConnectionException
     */
    protected function getAccessToken(): string
    {
        $cacheKey = $this->getCacheKey();
        $encryptedToken = Cache::get($cacheKey);

        if ($encryptedToken !== null) {
            return $this->decryptToken($encryptedToken);
        }

        return $this->fetchFreshAccessToken();
    }

    /**
     * Get the OAuth token endpoint path
     */
    protected function getTokenEndpoint(): string
    {
        return "/oauth/oauth2/{$this->apiVersion}/token";
    }

    /**
     * Force fetch a fresh access token and update cache
     *
     * @throws AbacusAuthenticationException
     * @throws ConnectionException
     */
    protected function fetchFreshAccessToken(): string
    {
        $response = Http::asForm()
            ->post($this->getBaseUrl().$this->getTokenEndpoint(), [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

        if ($response->failed() || $response->json('access_token') === null) {
            throw new AbacusAuthenticationException('Cannot fetch access token from API.');
        }

        $accessToken = $response->json('access_token');
        $tokenLifetime = (int) $response->json('expires_in');

        /* Cache token with 10 second buffer before expiration */
        Cache::put(
            $this->getCacheKey(),
            $this->encryptToken($accessToken),
            $tokenLifetime - 10
        );

        return $accessToken;
    }

    /**
     * Retries the HTTP call if the response status is 401
     * This can happen if the token became invalid
     */
    protected function callWithTokenRefresh(callable $callback): Response
    {
        $response = $callback();

        if (! ($response instanceof Response)) {
            throw new \TypeError('Callback function must return an instance of '.Response::class);
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
     * @throws RequestException
     * @throws ConnectionException
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     */
    public function get(string $path, $queryString = []): Response
    {
        return $this->callWithTokenRefresh(function () use ($path, $queryString) {

            if (! empty($queryString)) {
                $path .= '?'.$this->buildQueryString($queryString);
            }

            event(new AbacusRequestSent(Request::METHOD_GET, $path));

            return $this->client()->get($path, $queryString);
        })->throw($this->toException());
    }

    /**
     * POST Request
     *
     * @throws RequestException
     * @throws ConnectionException
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     */
    public function post(string $path, array $data = [], $queryString = []): Response
    {
        return $this->callWithTokenRefresh(function () use ($path, $data, $queryString) {

            if (! empty($queryString)) {
                $path .= '?'.$this->buildQueryString($queryString);
            }

            event(new AbacusRequestSent(Request::METHOD_POST, $path, $data));

            return $this->client()->post($path, empty($data) ? new stdClass : $data);
        })->throw($this->toException());
    }

    /**
     * PATCH Request
     *
     * @throws RequestException
     * @throws ConnectionException
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     */
    public function patch(string $path, array $data = [], $queryString = []): Response
    {
        return $this->callWithTokenRefresh(function () use ($path, $data, $queryString) {

            if (! empty($queryString)) {
                $path .= '?'.$this->buildQueryString($queryString);
            }

            event(new AbacusRequestSent(Request::METHOD_PATCH, $path, $data));

            return $this->client()->patch($path, $data);
        })->throw($this->toException());
    }

    /**
     * PUT Request
     *
     * @throws RequestException
     * @throws ConnectionException
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     */
    public function put(string $path, array $data = [], $queryString = []): Response
    {
        return $this->callWithTokenRefresh(function () use ($path, $data, $queryString) {

            if (! empty($queryString)) {
                $path .= '?'.$this->buildQueryString($queryString);
            }

            event(new AbacusRequestSent(Request::METHOD_PUT, $path, $data));

            return $this->client()->put($path, $data);
        })->throw($this->toException());
    }

    /**
     * DELETE Request
     *
     * @throws RequestException
     * @throws ConnectionException
     * @throws AbacusAuthenticationException
     * @throws AbacusRateLimitException
     */
    public function delete(string $path): Response
    {
        return $this->callWithTokenRefresh(function () use ($path) {

            event(new AbacusRequestSent(Request::METHOD_DELETE, $path));

            return $this->client()->delete($path);
        })->throw($this->toException());
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
     * Build query string from parameters
     */
    public function buildQueryString(array $params): string
    {
        $queryParts = [];
        foreach ($params as $key => $value) {
            $encodedValue = str_replace('+', '%20', urlencode((string) $value));
            $queryParts[] = $key.'='.$encodedValue;
        }

        return implode('&', $queryParts);
    }

    /**
     * Encrypt access token for caching
     */
    private function encryptToken(string $token): string
    {
        return encrypt($token);
    }

    /**
     * Decrypt access token from cache
     */
    private function decryptToken(string $encryptedToken): string
    {
        return decrypt($encryptedToken);
    }

    /**
     * Handels the failed response.
     */
    protected function toException(): Closure
    {
        return function (Response $response, RequestException $e) {
            if ($response->tooManyRequests()) {
                throw new AbacusRateLimitException($response);
            }

            if ($response->badRequest()) {
                throw new AbacusBadRequestException($response);
            }

            if ($response->forbidden()) {
                throw new AbacusForbiddenException($response);
            }

            throw $e;
        };
    }
}
