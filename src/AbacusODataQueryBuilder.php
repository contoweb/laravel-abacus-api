<?php

namespace Contoweb\AbacusApi;

use Closure;
use Contoweb\AbacusApi\Batch\BatchContext;
use Contoweb\AbacusApi\Batch\BatchRequestItem;
use Contoweb\AbacusApi\Enums\ODataEnum;
use Contoweb\AbacusApi\Enums\ODataOperator;
use Contoweb\AbacusApi\Models\AbacusModel;
use DateTime;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

class AbacusODataQueryBuilder
{
    protected AbacusODataClient $client;

    protected string $resource;

    protected string $modelClass;

    private int $pages;

    private bool $cursor;

    protected ?Closure $pageCallback = null;

    protected array $filters = [];

    protected array $selects = [];

    protected ?string $orderBy = null;

    protected ?int $top = null;

    protected array $expand = [];

    protected string $format = 'json';

    protected mixed $entityId = null;

    protected array $compositeKey = [];

    public function __construct(AbacusODataClient $client, string $resource, string $modelClass)
    {
        $this->client = $client;
        $this->resource = $resource;
        $this->modelClass = $modelClass;
        $this->pages = config('abacus-api.query_builder.max_next_link_page_resolving') ?? 0;
        $this->cursor = false;
    }

    /**
     * Set the maximum number of pages to retrieve when cursor pagination is enabled
     *
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
     * @param  callable  $callback  Callback function receiving (Collection $items, int $pageNumber)
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
     * @return Collection<int, AbacusModel>|BatchRequestItem
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function get(): Collection|BatchRequestItem
    {
        // Check if we're in a batch context
        if ($batch = BatchContext::get()) {
            $item = $this->toBatchItem(Request::METHOD_GET);
            $batch->add($item);

            return $item;
        }

        $allResults = [];

        $path = $this->client->entityPath($this->resource);

        /* Fetch first page */
        $response = $this->client
            ->get($path, $this->buildODataQuery())
            ->json();

        /* Collect results of first page */
        if (isset($response['value'])) {
            $allResults = array_merge($allResults, $response['value']);
        }

        if ($this->cursor) {
            $pageNumber = 1;

            /* Call callback for first page if provided */
            if ($this->pageCallback !== null && isset($response['value'])) {
                $pageItems = collect($response['value'])->map(fn ($item) => new $this->modelClass($item));
                call_user_func($this->pageCallback, $pageItems, $pageNumber);
            }

            for ($i = 1; $i < $this->pages; $i++) {
                if (! isset($response['@odata.nextLink'])) {
                    break;
                }

                $pageNumber++;

                $response = $this->client
                    ->getNextLink($response['@odata.nextLink'])
                    ->json();

                if (isset($response['value'])) {
                    $pageItems = collect($response['value'])->map(fn ($item) => new $this->modelClass($item));

                    /* Call callback for each page if provided */
                    if ($this->pageCallback !== null) {
                        call_user_func($this->pageCallback, $pageItems, $pageNumber);
                    }

                    $allResults = array_merge($allResults, $response['value']);
                }
            }
        }

        return collect($allResults)->map(fn ($item) => new $this->modelClass($item));
    }

    /**
     * Fetch entity via primary key
     *
     * @param  int|string|array<string, int|string>  $idOrCriteria
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function find(int|string|array $idOrCriteria): AbacusModel|BatchRequestItem
    {
        // Check if we're in a batch context
        if ($batch = BatchContext::get()) {
            $this->id($idOrCriteria);
            $item = $this->toBatchItem(Request::METHOD_GET);
            $batch->add($item);

            return $item;
        }

        $this->id($idOrCriteria);
        $path = $this->buildPathWithId($this->client, $this->resource);

        $result = $this->client
            ->get($path, $this->buildODataQuery())
            ->json();

        return new $this->modelClass($result);
    }

    /**
     * Execute a POST request (create entity)
     * Valid query methods: select(), expand()
     *
     * @param  array<string, int|string>  $data  Request body data
     * @return AbacusModel|BatchRequestItem The created entity
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function create(array $data): AbacusModel|BatchRequestItem
    {
        // Check if we're in a batch context
        if ($batch = BatchContext::get()) {
            $item = $this->toBatchItem(Request::METHOD_POST, $data);
            $batch->add($item);

            return $item;
        }

        $path = $this->client->entityPath($this->resource);

        $response = $this->client
            ->post($path, $data, $this->buildODataQuery())
            ->json();

        return new $this->modelClass($response);
    }

    /**
     * Execute a DELETE request
     * Requires: ID set via id()
     *
     * @param  int|string|array<string, int|string>  $idOrCriteria
     * @return BatchRequestItem|null Returns BatchRequestItem in batch mode, null otherwise
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function delete(int|string|array $idOrCriteria): ?BatchRequestItem
    {
        // Check if we're in a batch context
        if ($batch = BatchContext::get()) {
            $this->id($idOrCriteria);
            $item = $this->toBatchItem(Request::METHOD_DELETE);
            $batch->add($item);

            return $item;
        }

        $this->id($idOrCriteria);
        $path = $this->buildPathWithId($this->client, $this->resource);
        $this->client->delete($path);

        return null;
    }

    /**
     * Update an entity
     *
     * @param  int|string|array<string, int|string>  $idOrCriteria  Single value for simple keys, array for composite keys
     * @param  array<string, int|array>  $data  Request body data
     * @return AbacusModel|BatchRequestItem The updated entity
     *
     * @throws ConnectionException
     * @throws RequestException
     *
     * @example Simple: Model::update(210, ['Name' => 'Test'])
     */
    public function update(int|string|array $idOrCriteria, array $data): AbacusModel|BatchRequestItem
    {
        // Check if we're in a batch context
        if ($batch = BatchContext::get()) {
            $this->id($idOrCriteria);
            $item = $this->toBatchItem(Request::METHOD_PATCH, $data);
            $batch->add($item);

            return $item;
        }

        $this->id($idOrCriteria);
        $path = $this->buildPathWithId($this->client, $this->resource);

        $result = $this->client
            ->patch($path, $data, $this->buildODataQuery())
            ->json();

        return new $this->modelClass($result);
    }

    /**
     * Execute query and return all paginated results as Collection
     *
     * @return Collection<int, AbacusModel>|BatchRequestItem
     *
     * @throws RequestException
     * @throws ConnectionException
     */
    public function all(): Collection|BatchRequestItem
    {
        return $this->get();
    }

    /**
     * Fetch specific property of an entity
     * Example: Subjects::query()->findProperty(2, 'LastName')
     *
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

    /**
     * Execute query and return first result
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function first(): AbacusModel|BatchRequestItem|null
    {
        $this->top(1);

        $results = $this->get();

        // If we got a BatchRequestItem, return it as-is
        if ($results instanceof BatchRequestItem) {
            return $results;
        }

        return $results->first();
    }

    /**
     * Convert current query to a BatchRequestItem
     */
    protected function toBatchItem(string $method, ?array $data = null): BatchRequestItem
    {
        $path = $this->hasId()
            ? $this->buildPathWithId($this->client, $this->resource)
            : $this->client->entityPath($this->resource);

        $odataParams = $this->buildODataQuery();

        if (! empty($odataParams)) {
            $path .= '?'.$this->client->buildQueryString($odataParams);
        }

        return new BatchRequestItem($this->modelClass, $method, $path, $data);
    }

    /**
     * Set entity ID (simple or composite)
     *
     * @param  int|string|array<string, int|string>  $idOrCriteria  Single value for simple keys, array for composite keys
     */
    public function id(int|string|array $idOrCriteria): void
    {
        if (is_array($idOrCriteria)) {
            $this->compositeKey = $idOrCriteria;
            $this->entityId = null;
        } else {
            $this->entityId = $idOrCriteria;
            $this->compositeKey = [];
        }
    }

    /**
     * Check if an entity ID has been set
     */
    public function hasId(): bool
    {
        return $this->entityId !== null || ! empty($this->compositeKey);
    }

    /**
     * Filter with OData operators or Laravel Eloquent operators
     * Example: ->where('LastName', ODataOperator::EQUALS, 'Müller')
     * Example: ->where('LastName', 'eq', 'Müller')
     * Example: ->where('LastName', '=', 'Müller')
     *
     * @return $this
     */
    public function where(string $field, ODataOperator|string $operator, mixed $value): static
    {
        /* Convert enum to string value */
        $operatorValue = $operator instanceof ODataOperator ? $operator->value : $operator;

        /* Convert Laravel operator to OData if needed */
        $operatorValue = ODataOperator::fromLaravel($operatorValue) ?? $operatorValue;

        /* Supported operators: eq, ge, gt, le, lt */
        $allowedOperators = ['eq', 'lt', 'gt', 'le', 'ge'];

        if (! in_array($operatorValue, $allowedOperators)) {
            throw new InvalidArgumentException(
                "Operator '{$operatorValue}' not supported. Allowed: ".implode(', ', $allowedOperators).' (or Laravel equivalents: =, >, >=, <, <=)'
            );
        }

        $formattedValue = $this->formatValue($value);
        $this->filters[] = "{$field} {$operatorValue} {$formattedValue}";

        return $this;
    }

    /**
     * Convenience method for equality
     */
    public function whereEquals(string $field, mixed $value): static
    {
        return $this->where($field, 'eq', $value);
    }

    /**
     * $select - Query only specific properties
     * Example: ->select(['LastName', 'AddressNumber'])
     */
    public function select(array|string $fields): static
    {
        $this->selects = array_merge(
            $this->selects,
            is_array($fields) ? $fields : func_get_args()
        );

        return $this;
    }

    /**
     * $top - Return only top N elements
     * Example: ->top(10)
     */
    public function top(int $limit): static
    {
        $this->top = $limit;

        return $this;
    }

    /**
     * Alias for top() (Laravel-like)
     */
    public function limit(int $limit): static
    {
        return $this->top($limit);
    }

    /**
     * Alias for top() (Laravel-like)
     */
    public function take(int $limit): static
    {
        return $this->top($limit);
    }

    /**
     * $orderby - Sort by attribute (asc or desc)
     * Example: ->orderBy('LastName', 'desc')
     * IMPORTANT: Only one orderBy possible, further calls override previous ones
     */
    public function orderBy(string $field, string $direction = 'asc'): static
    {
        if (! in_array(strtolower($direction), ['asc', 'desc'])) {
            throw new InvalidArgumentException("Direction must be 'asc' or 'desc'");
        }

        $this->orderBy = "{$field} ".strtolower($direction);

        return $this;
    }

    /**
     * $expand - Expand navigation properties
     * Example: ->expand('Addresses') or ->expand(['Addresses', 'Contacts'])
     */
    public function expand(array|string $relations): static
    {
        $this->expand = array_merge(
            $this->expand,
            is_array($relations) ? $relations : func_get_args()
        );

        return $this;
    }

    /**
     * Build the full path with entity ID (simple or composite)
     */
    public function buildPathWithId(AbacusODataClient $client, string $resource): string
    {
        $basePath = $client->entityPath($resource);

        return $basePath.'('.$this->buildEntityIdSegment().')';
    }

    /**
     * Build the entity ID segment for the path
     * Handles both simple IDs and composite keys
     *
     * @example Simple: "123"
     * @example Composite: "BatchNumber='5436',BatchSequenceNumber=0,ProductId=12276,VariantId=0"
     */
    private function buildEntityIdSegment(): string
    {
        if (! empty($this->compositeKey)) {
            $parts = [];
            foreach ($this->compositeKey as $key => $value) {
                $formattedValue = $this->formatValue($value);
                $parts[] = "{$key}={$formattedValue}";
            }

            return implode(',', $parts);
        }

        return (string) $this->entityId;
    }

    /**
     * Assemble OData query parameters for list queries
     */
    public function buildODataQuery(): array
    {
        $query = [];

        // $filter
        if (! empty($this->filters)) {
            $query['$filter'] = implode(' and ', $this->filters);
        }

        // $select
        if (! empty($this->selects)) {
            $query['$select'] = implode(',', $this->selects);
        }

        // $orderby
        if ($this->orderBy !== null) {
            $query['$orderby'] = $this->orderBy;
        }

        // $top
        if ($this->top !== null) {
            $query['$top'] = $this->top;
        }

        // $expand
        if (! empty($this->expand)) {
            $query['$expand'] = implode(',', $this->expand);
        }

        // $format
        if ($this->format !== 'json') {
            $query['$format'] = $this->format;
        }

        return $query;
    }

    /**
     * Format values for OData
     */
    private function formatValue(mixed $value): string
    {
        if ($value instanceof ODataEnum) {
            return $value->toODataString();
        }

        if (is_string($value)) {
            /* Escape single quotes */
            return "'".str_replace("'", "''", $value)."'";
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if ($value instanceof DateTime) {
            return $value->format('Y-m-d\TH:i:s\Z');
        }

        return (string) $value;
    }

    /**
     * Debug: Display query parameters
     */
    public function toODataQuery(): array
    {
        return $this->buildODataQuery();
    }
}
