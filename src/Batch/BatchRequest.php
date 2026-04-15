<?php

namespace Contoweb\AbacusApi\Batch;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\DataTransferObjects\BatchResponseDto;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;

class BatchRequest
{
    private AbacusODataClient $client;

    /**
     * @var BatchRequestItem[]
     */
    public array $requests = [];

    public function __construct(AbacusODataClient $client, BatchRequestItem ...$requests)
    {
        $this->client = $client;
        $this->requests = $requests;
    }

    /**
     * Send the batch request and return parsed results.
     *
     * @return Collection<int|string, BatchResponseDto>
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function send(): Collection
    {
        /* Encode requests into multipart/mixed format */
        $body = MultipartEncoder::encode($this->requests);
        $path = $this->client->batchPath();

        $response = $this->client->sendBatch($path, $body);

        $contentType = $response->header('Content-Type');

        preg_match('/boundary=(.+)$/', $contentType, $matches);
        $boundary = trim($matches[1]);

        /* Decode multipart response */
        $results = MultipartDecoder::decode($response->body(), $boundary);

        return collect($results)->map(function ($result, $index) {
            $modelClass = $this->requests[$index]->modelClass;

            return BatchResponseDto::fromArray($result, $modelClass);
        });
    }
}
