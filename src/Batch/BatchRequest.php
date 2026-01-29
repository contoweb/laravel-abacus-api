<?php

namespace Contoweb\AbacusApi\Batch;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\DataTransferObjects\BatchResponseDto;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;

class BatchRequest
{
    /**
     * @var AbacusODataClient
     */
    private AbacusODataClient $client;
    /**
     * @var BatchRequestItem[]
     */
    public array $requests = [];

    /**
     * @param AbacusODataClient $client
     * @param BatchRequestItem ...$requests
     */
    public function __construct(AbacusODataClient $client, BatchRequestItem ...$requests)
    {
        $this->client = $client;
        $this->requests = $requests;
    }

    /**
     * Send the batch request and return parsed results
     *
     * @return Collection<int, BatchResponseDto>
     * @throws ConnectionException
     * @throws RequestException
     */
    public function send(): Collection
    {
        /* Encode requests into multipart/mixed format */
        $body = MultipartEncoder::encode($this->requests);
        $path = $this->client->batchPath();

        $response =  $this->client->sendBatch($path, $body);

        $contentType = $response->getHeader('Content-Type');

        preg_match('/boundary=(.+)$/', $contentType[0], $matches);
        $boundary = trim($matches[1]);

        /* Decode multipart response */
        $results = MultipartDecoder::decode($response->body(), $boundary);

        return collect($results)->map(fn($result) => BatchResponseDto::fromArray($result));
    }
}
