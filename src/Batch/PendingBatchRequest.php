<?php

namespace Contoweb\AbacusApi\Batch;

use Contoweb\AbacusApi\BatchRequest;
use Contoweb\AbacusApi\DataTransferObjects\BatchResponseDto;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;

/**
 * Represents a batch request that has been prepared but not yet sent.
 *
 * This class is returned by Abacus::batch(callback) and provides the send()
 * method to execute all collected queries as a single batch request.
 */
class PendingBatchRequest
{
    /**
     * @param BatchRequest $batchRequest The underlying batch request
     * @param array $queryMeta Metadata about each query (modelClass, etc.)
     */
    public function __construct(
        protected BatchRequest $batchRequest,
        protected array $queryMeta
    ) {}

    /**
     * Send the batch request and return parsed results
     *
     * @return Collection<int, BatchResponseDto>
     * @throws ConnectionException
     * @throws RequestException
     */
    public function send(): Collection
    {
        return $this->batchRequest->send();

        // return new BatchResult($rawResults, $this->queryMeta);
    }

    /**
     * Get the number of queries in this batch
     */
    public function count(): int
    {
        return $this->batchRequest->count();
    }

    /**
     * Get the underlying batch request (useful for debugging)
     */
    public function getBatchRequest(): BatchRequest
    {
        return $this->batchRequest;
    }

    /**
     * Get the query metadata (useful for debugging)
     */
    public function getQueryMeta(): array
    {
        return $this->queryMeta;
    }
}
