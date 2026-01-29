<?php

namespace Contoweb\AbacusApi;

use Closure;
use Contoweb\AbacusApi\Models\AbacusModel;
use Contoweb\AbacusApi\Traits\HasODataQueryMethods;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;

class AbacusODataQueryBuilder
{
    use HasODataQueryMethods;

    protected AbacusODataClient $client;
    protected string $resource;
    protected string $modelClass;
    private int $pages;
    private bool $cursor;
    protected ?Closure $pageCallback = null;
    protected ODataQueryState $queryState;

    public function __construct(AbacusODataClient $client, string $resource, string $modelClass)
    {
        $this->client = $client;
        $this->resource = $resource;
        $this->modelClass = $modelClass;
        $this->pages = config('abacus-api.query_builder.max_next_link_page_resolving') ?? 0;
        $this->cursor = false;
        $this->queryState = new OdataQueryState();
    }

    /**
     * Set the maximum number of pages to retrieve when cursor pagination is enabled
     *
     * @param int $limit
     * @return $this
     */
    public function pages(int $limit): static
    {
        $this->pages = $limit;
        return $this;
    }

    /**
     * Enable automatic pagination through OData nextLink
     *
     * @return $this
     */
    public function cursor(): static
    {
        $this->cursor = true;
        return $this;
    }

    /**
     * Enable automatic pagination with a callback for each page
     * Useful for processing large datasets without loading everything into memory
     * Note: Automatically enables cursor pagination
     *
     * @param callable $callback Callback function receiving (Collection $items, int $pageNumber)
     * @return $this
     *
     * @example
     * Subject::pages(100)
     *     ->cursorWithCallback(function($items, $pageNumber) {
     *         foreach ($items as $item) {
     *             $this->processItem($item);
     *         }
     *         Log::info("Processed page {$pageNumber} with {$items->count()} items");
     *     })
     *     ->get();
     */
    public function cursorWithCallback(callable $callback): static
    {
        $this->cursor = true;
        $this->pageCallback = $callback;
        return $this;
    }

    /**
     * Execute query and return all paginated results as Collection
     *
     * @return Collection<int, AbacusModel>
     * @throws ConnectionException
     * @throws RequestException
     */
    public function get(): Collection
    {
        $allResults = [];

        $path = $this->client->entityPath($this->resource);

        /* Fetch first page */
        $response = $this->client
            ->get($path, $this->queryState->buildODataQuery())
            ->json();

        /* Collect results of first page */
        if (isset($response['value'])) {
            $allResults = array_merge($allResults, $response['value']);
        }

        if ($this->cursor) {
            $pageNumber = 1;

            /* Call callback for first page if provided */
            if ($this->pageCallback !== null && isset($response['value'])) {
                $pageItems = collect($response['value'])->map(fn($item) => new $this->modelClass($item));
                call_user_func($this->pageCallback, $pageItems, $pageNumber);
            }

            for ($i = 1; $i < $this->pages; ++$i) {
                if (!isset($response['@odata.nextLink'])) {
                    break;
                }

                $pageNumber++;

                $response = $this->client
                    ->getNextLink($response['@odata.nextLink'])
                    ->json();

                if (isset($response['value'])) {
                    $pageItems = collect($response['value'])->map(fn($item) => new $this->modelClass($item));

                    /* Call callback for each page if provided */
                    if ($this->pageCallback !== null) {
                        call_user_func($this->pageCallback, $pageItems, $pageNumber);
                    }

                    $allResults = array_merge($allResults, $response['value']);
                }
            }
        }

        return collect($allResults)->map(fn($item) => new $this->modelClass($item));
    }

    /**
     * Fetch entity via primary key
     *
     * @param int|string|array<string, int|string> $idOrCriteria
     * @return AbacusModel
     * @throws ConnectionException
     * @throws RequestException
     */
    public function find(int|string|array $idOrCriteria): AbacusModel
    {
        $this->queryState->id($idOrCriteria);
        $path = $this->queryState->buildPathWithId($this->client, $this->resource);

        $result = $this->client
            ->get($path, $this->queryState->buildODataQuery())
            ->json();

        return new $this->modelClass($result);
    }

    /**
     * Execute a POST request (create entity)
     * Valid query methods: select(), expand()
     *
     * @param array<string, int|string> $data Request body data
     * @return AbacusModel The created entity
     * @throws ConnectionException
     * @throws RequestException
     */
    public function create(array $data): AbacusModel
    {
        $path = $this->client->entityPath($this->resource);

        $response = $this->client
            ->post($path, $data, $this->queryState->buildODataQuery())
            ->json();

        return new $this->modelClass($response);
    }

    /**
     * Execute a DELETE request
     * Requires: ID set via id()
     *
     * @param int|string|array<string, int|string> $idOrCriteria
     * @return void Success status
     * @throws ConnectionException
     * @throws RequestException
     */
    public function delete(int|string|array $idOrCriteria): void
    {
        $this->queryState->id($idOrCriteria);
        $path = $this->queryState->buildPathWithId($this->client, $this->resource);
        $this->client->delete($path);
    }

    /**
     * Update an entity
     *
     * @param int|string|array<string, int|string> $idOrCriteria Single value for simple keys, array for composite keys
     * @param array<string, int|array> $data Request body data
     * @return AbacusModel The updated entity
     * @throws ConnectionException
     * @throws RequestException
     * @example Simple: Model::update(210, ['Name' => 'Test'])
     */
    public function update(int|string|array $idOrCriteria, array $data): AbacusModel
    {
        $this->queryState->id($idOrCriteria);
        $path = $this->queryState->buildPathWithId($this->client, $this->resource);

        $result = $this->client
            ->patch($path, $data, $this->queryState->buildODataQuery())
            ->json();

        return new $this->modelClass($result);
    }

    /**
     * Execute query and return all paginated results as Collection
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    public function all(): Collection
    {
        return $this->get();
    }

    /**
     * Fetch specific property of an entity
     * Example: Subjects::query()->findProperty(2, 'LastName')
     * @throws ConnectionException
     * @throws RequestException
     */
    public function findProperty($id, string $property)
    {
        $path = $this->client->entityPropertyPath($this->resource, $id, $property);

        return $this->client
            ->get($path)
            ->json();
    }
}
