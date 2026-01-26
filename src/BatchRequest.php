<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\DataTransferObjects\BatchResponseDto;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;

class BatchRequest
{
    protected BaseAbacusClient $client;
    protected array $requests = [];

    public function __construct(BaseAbacusClient $client)
    {
        $this->client = $client;
    }

    /**
     * Add a request to the batch
     *
     * @param array $request Array with keys: 'method', 'path', 'body'
     * @return $this
     */
    public function addRequest(array $request): self
    {
        /* Validate request structure */
        if (!isset($request['method']) || !isset($request['path'])) {
            throw new \InvalidArgumentException('Request must contain "method" and "path" keys');
        }

        /* Ensure body key exists (can be null) */
        if (!array_key_exists('body', $request)) {
            $request['body'] = null;
        }

        $this->requests[] = $request;

        return $this;
    }

    /**
     * Send the batch request and return results
     *
     * @return Collection<int, BatchResponseDto>
     * @throws ConnectionException
     * @throws RequestException
     */
    public function send(): Collection
    {
        if (empty($this->requests)) {
            throw new \RuntimeException('No requests added to batch');
        }

        return $this->client->sendBatch($this->requests);
    }

    /**
     * Get the requests array (useful for testing)
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    /**
     * Get the count of requests in the batch
     */
    public function count(): int
    {
        return count($this->requests);
    }
}
