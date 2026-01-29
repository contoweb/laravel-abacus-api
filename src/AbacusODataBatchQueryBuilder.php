<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Batch\BatchRequestItem;
use Contoweb\AbacusApi\Traits\HasODataQueryMethods;

class AbacusODataBatchQueryBuilder
{
    use HasODataQueryMethods;

    private AbacusODataClient $client;
    private string $resource;
    private string $modelClass;
    protected ODataQueryState $queryState;

    public function __construct(AbacusODataClient $client, string $resource, string $modelClass)
    {
        $this->client = $client;
        $this->resource = $resource;
        $this->modelClass = $modelClass;
        $this->queryState = new ODataQueryState();
    }

    /**
     * Prepare a get operation as batch request item
     *
     * @return BatchRequestItem
     */
    public function get(): BatchRequestItem
    {
        $path = $this->client->entityPath($this->resource);
        $odataParams = $this->queryState->buildODataQuery();

        /* Build full path with query string */
        if (!empty($odataParams)) {
            $path .= '?' . $this->client->buildQueryString($odataParams);
        }

        return new BatchRequestItem($this->modelClass, 'GET', $path, null);
    }

    /**
     * Prepare a find operation as batch request item
     *
     * @param int|string|array<string, int|string> $idOrCriteria
     * @return BatchRequestItem
     */
    public function find(int|string|array $idOrCriteria): BatchRequestItem
    {
        $this->queryState->id($idOrCriteria);

        $path = $this->queryState->buildPathWithId($this->client, $this->resource);
        $odataParams = $this->queryState->buildODataQuery();

        if (!empty($odataParams)) {
            $path .= '?' . $this->client->buildQueryString($odataParams);
        }

        return new BatchRequestItem($this->modelClass, 'GET', $path, null);
    }

    /**
     * Prepare a create operation as batch request item
     *
     * @param array<string, int|string> $data
     * @return BatchRequestItem
     */
    public function create(array $data): BatchRequestItem
    {
        $path = $this->client->entityPath($this->resource);
        $odataParams = $this->queryState->buildODataQuery();

        if (!empty($odataParams)) {
            $path .= '?' . $this->client->buildQueryString($odataParams);
        }

        return new BatchRequestItem($this->modelClass, 'POST', $path, $data);
    }

    /**
     * Prepare a delete operation as batch request item
     *
     * @param int|string|array<string, int|string> $idOrCriteria
     * @return BatchRequestItem
     */
    public function delete(int|string|array $idOrCriteria): BatchRequestItem
    {
        $this->queryState->id($idOrCriteria);
        $path = $this->queryState->buildPathWithId($this->client, $this->resource);
        $odataParams = $this->queryState->buildODataQuery();

        if (!empty($odataParams)) {
            $path .= '?' . $this->client->buildQueryString($odataParams);
        }

        return new BatchRequestItem($this->modelClass, 'DELETE', $path, null);
    }

    /**
     * Prepare a batch operation as batch request item
     *
     * @param int|string|array<string, int|string> $idOrCriteria
     * @param array<string, int|string> $data
     * @return BatchRequestItem
     */
    public function update(int|string|array $idOrCriteria, array $data): BatchRequestItem
    {
        $this->queryState->id($idOrCriteria);
        $path = $this->queryState->buildPathWithId($this->client, $this->resource);
        $odataParams = $this->queryState->buildODataQuery();

        if (!empty($odataParams)) {
            $path .= '?' . $this->client->buildQueryString($odataParams);
        }

        return new BatchRequestItem($this->modelClass, 'PATCH', $path, $data);
    }
}