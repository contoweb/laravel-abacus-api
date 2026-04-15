<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Batch\BatchContext;
use Contoweb\AbacusApi\Batch\BatchRequestItem;
use Contoweb\AbacusApi\Enums\ODataEnum;
use Contoweb\AbacusApi\Enums\ODataOperator;
use Contoweb\AbacusApi\Models\AbacusModel;
use DateTime;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

class AbacusODataQueryBuilder
{
    protected AbacusODataClient $client;

    protected string $resource;

    protected string $modelClass;

    protected array $filters = [];

    protected array $selects = [];

    protected ?string $orderBy = null;

    protected ?int $top = null;

    protected array $expand = [];

    protected string $format = 'json';

    protected mixed $entityId = null;

    protected array $compositeKey = [];

    protected bool $unescapeUUID = true;

    public function __construct(AbacusODataClient $client, string $resource, string $modelClass)
    {
        $this->client = $client;
        $this->resource = $resource;
        $this->modelClass = $modelClass;
    }

    /**
     * Executes the query and returns a paginated result
     *
     * The $perPage parameter controls the OData query option $top to set the number of items per page
     * Returns a BatchRequestItem in batch context, otherwise an OdataPaginator
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws InvalidArgumentException
     */
    public function paginate(?int $perPage = null): OdataPaginator|BatchRequestItem
    {
        if ($perPage <= 0 && $perPage !== null) {
            throw new InvalidArgumentException('Limit should be greater than 0');
        }

        if ($perPage !== null) {
            $this->top($perPage);
        }

        /* Check if we're in a batch context */
        if ($batch = BatchContext::get()) {
            $item = $this->toBatchItem(Request::METHOD_GET);
            $batch->add($item);

            return $item;
        }

        $items = [];

        $path = $this->client->entityPath($this->resource);

        /* Fetch first page */
        $response = $this->client
            ->get($path, $this->buildODataQuery())
            ->json();

        /* Collect results of first page */
        if (isset($response['value'])) {
            $items = $response['value'];
        }

        return new OdataPaginator(
            $items,
            $this->client,
            $this->modelClass,
            $response['@odata.nextLink'] ?? null,
        );
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
        /* Check if we're in a batch context */
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
     * @param  array<string, mixed>  $data  Request body data
     * @return AbacusModel|BatchRequestItem The created entity
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function create(array $data): AbacusModel|BatchRequestItem
    {
        /* Check if we're in a batch context */
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
        /* Check if we're in a batch context */
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
     * @param  array<string, mixed>  $data  Request body data
     * @return AbacusModel|BatchRequestItem The updated entity
     *
     * @throws ConnectionException
     * @throws RequestException
     */
    public function update(int|string|array $idOrCriteria, array $data): AbacusModel|BatchRequestItem
    {
        /* Check if we're in a batch context */
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
     * Fetch specific property of an entity
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
        $results = $this->paginate(1);

        // If we got a BatchRequestItem, return it as-is
        if ($results instanceof BatchRequestItem) {
            return $results;
        }

        return $results->items()->first();
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
     * Filter with OData operators or Laravel Eloquent operators.
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
     */
    private function top(int $limit): void
    {
        $this->top = $limit;
    }

    /**
     * $orderby - Sort by attribute (asc or desc)
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

            if (Str::isUuid($value) && $this->unescapeUUID) {

                return $value;
            }

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

    /**
     * Disable UUID escaping so UUID values are formatted without string quotes in OData queries.
     *
     * When disabled (default), UUID values are output as raw GUIDs: `$filter=Id eq 57bc1fe4-bac4-6549-53fa-8ce85e63f4cb`
     */
    public function withoutUUIDEscaping(): static
    {
        $this->unescapeUUID = true;

        return $this;
    }

    /**
     * Enable UUID escaping so UUID values are treated as regular strings in OData queries.
     *
     * When enabled, UUID values are wrapped in single quotes: `$filter=Id eq '57bc1fe4-bac4-6549-53fa-8ce85e63f4cb'`
     */
    public function withUUIDEscaping(): static
    {
        $this->unescapeUUID = false;

        return $this;
    }
}
