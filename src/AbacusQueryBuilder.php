<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Batch\BatchCollector;
use Contoweb\AbacusApi\Enums\ODataOperator;
use Contoweb\AbacusApi\Exceptions\InvalidQueryCombinationException;
use Contoweb\AbacusApi\Exceptions\MissingEntityIdentifierException;
use Contoweb\AbacusApi\Models\AbacusModel;
use Illuminate\Support\Collection;

/**
 * @template TypeModel of AbacusModel
 */
class AbacusQueryBuilder
{
    protected AbacusService $service;
    protected string        $resource;
    protected array         $filters = [];
    protected array         $selects = [];
    protected ?string       $orderBy = null;
    protected ?int          $top     = null;
    protected array         $expand  = [];
    protected string        $format  = 'json';
    protected ?string       $modelClass = null;
    protected mixed         $entityId = null;
    protected array         $compositeKey = [];
    protected ?array        $body = null;

    public function __construct(AbacusService $service, string $resource, ?string $modelClass = null)
    {
        $this->service    = $service;
        $this->resource   = $resource;
        $this->modelClass = $modelClass;
    }

    /**
     * Set entity ID (simple or composite)
     *
     * @param mixed $id Single value for simple keys, array for composite keys
     * @return $this
     */
    public function id(mixed $id): static
    {
        if (is_array($id)) {
            $this->compositeKey = $id;
            $this->entityId = null;
        } else {
            $this->entityId = $id;
            $this->compositeKey = [];
        }

        return $this;
    }

    /**
     * Check if an entity ID (simple or composite) is set
     */
    public function hasEntityId(): bool
    {
        return $this->entityId !== null || !empty($this->compositeKey);
    }

    /**
     * Filter with OData operators
     * Example: ->where('LastName', ODataOperator::EQUALS, 'Müller')
     * Example: ->where('LastName', 'eq', 'Müller')
     *
     * @return $this
     */
    public function where(string $field, ODataOperator | string $operator, mixed $value): static
    {
        /* Convert enum to string value */
        $operatorValue = $operator instanceof ODataOperator ? $operator->value : $operator;

        /* Supported operators: eq, ge, gt, le, lt */
        $allowedOperators = ['eq', 'lt', 'gt', 'le', 'ge'];

        if ( ! in_array($operatorValue, $allowedOperators)) {
            throw new \InvalidArgumentException(
                "Operator '{$operatorValue}' not supported. Allowed: " . implode(', ', $allowedOperators)
            );
        }

        $formattedValue  = $this->formatValue($value);
        $this->filters[] = "{$field} {$operatorValue} {$formattedValue}";

        return $this;
    }

    /**
     * Convenience method for equality
     */
    public function whereEquals(string $field, mixed $value)
    {
        return $this->where($field, 'eq', $value);
    }

    /**
     * $select - Query only specific properties
     * Example: ->select(['LastName', 'AddressNumber'])
     */
    public function select(array | string $fields): static
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
     * Alias für top() (Laravel-like)
     */
    public function limit(int $limit): static
    {
        return $this->top($limit);
    }

    /**
     * Alias für top() (Laravel-like)
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
        if ( ! in_array(strtolower($direction), ['asc', 'desc'])) {
            throw new \InvalidArgumentException("Direction must be 'asc' or 'desc'");
        }

        $this->orderBy = "{$field} " . strtolower($direction);

        return $this;
    }

    /**
     * $expand - Expand navigation properties
     * Example: ->expand('Addresses') or ->expand(['Addresses', 'Contacts'])
     */
    public function expand(array | string $relations): static
    {
        $this->expand = array_merge(
            $this->expand,
            is_array($relations) ? $relations : func_get_args()
        );

        return $this;
    }

    /**
     * $format - Change response format (json, atom, xml)
     * Default: json
     */
    public function format(string $format): static
    {
        if ( ! in_array($format, ['json', 'atom', 'xml'])) {
            throw new \InvalidArgumentException("Format must be 'json', 'atom' or 'xml'");
        }

        $this->format = $format;

        return $this;
    }

    /**
     * Execute query and return results as Collection
     *
     * @return Collection<int, TypeModel>
     */
    public function getFirstPage(): Collection
    {
        $result = $this->service->query($this->resource, $this->buildODataQuery());

        /* JSON response usually contains 'value' array */
        $data = $result['value'] ?? $result;

        /* Wrap in model instances if model class is set */
        if ($this->modelClass !== null) {
            return collect($data)->map(fn($item) => new $this->modelClass($item));
        }

        return collect($data);
    }

    /**
     * Execute query and return all paginated results as Collection
     * Follows all @odata.nextLink URLs automatically
     *
     * In batch mode, collects the query instead of executing it.
     *
     * @return Collection<int, TypeModel>
     */
    public function get(): Collection
    {
        /* In batch mode, collect query instead of executing */
        if (BatchCollector::isActive()) {
            BatchCollector::getCurrent()->addQuery(
                $this->hasEntityId() ? $this->prepareForBatchGetSingle() : $this->prepareForBatch(),
                $this->modelClass
            );

            return collect();
        }
        
        $allResults = [];

        /* Fetch first page */
        $response = $this->service->queryWithMetadata($this->resource, $this->buildODataQuery());

        /* Collect results of first page */
        if (isset($response['value'])) {
            $allResults = array_merge($allResults, $response['value']);
        }

        /* Fetch all remaining pages */
        while (isset($response['@odata.nextLink'])) {
            \Log::debug('Fetching next page: ' . $response['@odata.nextLink']);
            $response = $this->service->getNextPage($response['@odata.nextLink']);

            if (isset($response['value'])) {
                $allResults = array_merge($allResults, $response['value']);
            }
        }

        /* Wrap in model instances if model class is set */
        if ($this->modelClass !== null) {
            return collect($allResults)->map(fn($item) => new $this->modelClass($item));
        }

        return collect($allResults);
    }

    /**
     * Return first match
     *
     * @return TypeModel|null
     */
    public function first(): mixed
    {
        $this->top = 1;
        $result    = $this->get();

        /* get() already wraps in model instances if modelClass is set */
        return $result->first();
    }

    /**
     * Fetch entity via primary key
     *
     * In batch mode, collects the query instead of executing it.
     *
     * @return TypeModel|array
     */
    public function find($id)
    {
        /* Set the ID */
        $this->id($id);

        /* In batch mode, collect query instead of executing */
        if (BatchCollector::isActive()) {
            BatchCollector::getCurrent()->addQuery(
                $this->prepareForBatchFind($id),
                $this->modelClass
            );

            return $this->modelClass !== null ? new $this->modelClass([]) : [];
        }

        $this->validateGetWithId();
        $result = $this->executeGetSingle();

        if ($this->modelClass !== null) {
            return new $this->modelClass($result);
        }

        return $result;
    }

    /**
     * Execute a POST request (create entity)
     * Valid query methods: select(), expand(), format()
     *
     * @param array $data Request body data
     * @return TypeModel|array The created entity
     * @throws InvalidQueryCombinationException
     */
    public function post(array $data): mixed
    {
        $this->body = $data;
        $this->validatePostCombination();

        if (BatchCollector::isActive()) {
            BatchCollector::getCurrent()->addQuery(
                $this->prepareForBatchPost(),
                $this->modelClass
            );

            return $this->modelClass ? new $this->modelClass([]) : [];
        }

        return $this->executePost();
    }

    /**
     * Execute a PATCH request (update entity)
     * Valid query methods: select(), expand(), format()
     * Requires: ID set via id()
     *
     * @param array $data Request body data
     * @return TypeModel|array The updated entity
     * @throws InvalidQueryCombinationException
     * @throws MissingEntityIdentifierException
     */
    public function patch(array $data): mixed
    {
        $this->body = $data;
        $this->validatePatchCombination();
        $this->validateEntityIdentifier('PATCH');

        if (BatchCollector::isActive()) {
            BatchCollector::getCurrent()->addQuery(
                $this->prepareForBatchPatch(),
                $this->modelClass
            );

            return $this->modelClass ? new $this->modelClass([]) : [];
        }

        return $this->executePatch();
    }

    /**
     * Execute a DELETE request
     * No query methods allowed except format()
     * Requires: ID set via id()
     *
     * @return bool Success status
     * @throws InvalidQueryCombinationException
     * @throws MissingEntityIdentifierException
     */
    public function delete(): bool
    {
        $this->validateDeleteCombination();
        $this->validateEntityIdentifier('DELETE');

        if (BatchCollector::isActive()) {
            BatchCollector::getCurrent()->addQuery(
                $this->prepareForBatchDelete(),
                null
            );

            return true;
        }

        return $this->executeDelete();
    }

    /**
     * Update an entity (combines id() and patch())
     * Supports chaining with select() and expand()
     *
     * @example Simple: Model::update(210, ['Name' => 'Test'])
     * @example Chained: Model::select(['Id', 'Name'])->update(210, ['Name' => 'Test'])
     *
     * @param mixed $id Single value for simple keys, array for composite keys
     * @param array $data Request body data
     * @return TypeModel|array The updated entity
     * @throws InvalidQueryCombinationException
     * @throws MissingEntityIdentifierException
     */
    public function update(mixed $id, array $data): mixed
    {
        $this->id($id);

        return $this->patch($data);
    }

    /**
     * Execute POST request
     */
    protected function executePost(): mixed
    {
        $path = $this->service->getClient()->entityPath($this->resource);
        $result = $this->service->getClient()
            ->post($path, $this->body)
            ->json();

        if ($this->modelClass !== null) {
            return new $this->modelClass($result);
        }

        return $result;
    }

    /**
     * Execute PATCH request
     */
    protected function executePatch(): mixed
    {
        $path = $this->buildPathWithId();
        $result = $this->service->getClient()
            ->patch($path, $this->body)
            ->json();

        if ($this->modelClass !== null) {
            return new $this->modelClass($result);
        }

        return $result;
    }

    /**
     * Execute DELETE request
     */
    protected function executeDelete(): bool
    {
        $path = $this->buildPathWithId();
        $this->service->getClient()->delete($path);

        return true;
    }

    /**
     * Build the full path with entity ID (simple or composite)
     */
    protected function buildPathWithId(): string
    {
        $basePath = $this->service->getClient()->entityPath($this->resource);

        return $basePath . '(' . $this->buildEntityIdSegment() . ')';
    }

    /**
     * Build the entity ID segment for the path
     * Handles both simple IDs and composite keys
     *
     * @example Simple: "123"
     * @example Composite: "BatchNumber='5436',BatchSequenceNumber=0,ProductId=12276,VariantId=0"
     */
    protected function buildEntityIdSegment(): string
    {
        if (!empty($this->compositeKey)) {
            $parts = [];
            foreach ($this->compositeKey as $key => $value) {
                $formattedValue = $this->formatValue($value);
                $parts[] = "{$key}={$formattedValue}";
            }

            return implode(',', $parts);
        }

        return (string)$this->entityId;
    }

    /**
     * Prepare a find-by-ID query for batch request
     *
     * @return array{method: string, path: string, body: array|null}
     */
    protected function prepareForBatchFind($id): array
    {
        /* Temporarily set the ID if not already set */
        if (!$this->hasEntityId()) {
            $this->id($id);
        }

        return $this->prepareForBatchGetSingle();
    }

    /**
     * Prepare GET single entity for batch request
     *
     * @return array{method: string, path: string, body: array|null}
     */
    protected function prepareForBatchGetSingle(): array
    {
        $path = $this->buildPathWithId();
        $odataParams = $this->buildODataQueryForSingleEntity();

        if (!empty($odataParams)) {
            $path .= '?' . $this->buildQueryString($odataParams);
        }

        return [
            'method' => 'GET',
            'path' => $path,
            'body' => null,
        ];
    }

    /**
     * Prepare POST request for batch
     *
     * @return array{method: string, path: string, body: array|null}
     */
    protected function prepareForBatchPost(): array
    {
        $path = $this->service->getClient()->entityPath($this->resource);
        $odataParams = $this->buildODataQueryForPost();

        if (!empty($odataParams)) {
            $path .= '?' . $this->buildQueryString($odataParams);
        }

        return [
            'method' => 'POST',
            'path' => $path,
            'body' => $this->body,
        ];
    }

    /**
     * Prepare PATCH request for batch
     *
     * @return array{method: string, path: string, body: array|null}
     */
    protected function prepareForBatchPatch(): array
    {
        $path = $this->buildPathWithId();
        $odataParams = $this->buildODataQueryForPatch();

        if (!empty($odataParams)) {
            $path .= '?' . $this->buildQueryString($odataParams);
        }

        return [
            'method' => 'PATCH',
            'path' => $path,
            'body' => $this->body,
        ];
    }

    /**
     * Prepare DELETE request for batch
     *
     * @return array{method: string, path: string, body: array|null}
     */
    protected function prepareForBatchDelete(): array
    {
        return [
            'method' => 'DELETE',
            'path' => $this->buildPathWithId(),
            'body' => null,
        ];
    }

    /**
     * Fetch specific property of an entity
     * Example: Subjects::query()->findProperty(2, 'LastName')
     */
    public function findProperty($id, string $property)
    {
        return $this->service->findProperty($this->resource, $id, $property);
    }

    /**
     * Assemble OData query parameters for list queries
     */
    protected function buildODataQuery(): array
    {
        $query = [];

        // $filter
        if ( ! empty($this->filters)) {
            $query['$filter'] = implode(' and ', $this->filters);
        }

        // $select
        if ( ! empty($this->selects)) {
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
        if ( ! empty($this->expand)) {
            $query['$expand'] = implode(',', $this->expand);
        }

        // $format
        if ($this->format !== 'json') {
            $query['$format'] = $this->format;
        }

        return $query;
    }

    /**
     * Build OData query params valid for single entity GET
     * Only select and expand are allowed
     */
    protected function buildODataQueryForSingleEntity(): array
    {
        $query = [];

        if (!empty($this->selects)) {
            $query['$select'] = implode(',', $this->selects);
        }

        if (!empty($this->expand)) {
            $query['$expand'] = implode(',', $this->expand);
        }

        if ($this->format !== 'json') {
            $query['$format'] = $this->format;
        }

        return $query;
    }

    /**
     * Build OData query params valid for POST
     * select and expand are allowed
     */
    protected function buildODataQueryForPost(): array
    {
        return $this->buildODataQueryForSingleEntity();
    }

    /**
     * Build OData query params valid for PATCH
     * select and expand are allowed
     */
    protected function buildODataQueryForPatch(): array
    {
        return $this->buildODataQueryForSingleEntity();
    }

    /**
     * Build query string from parameters
     */
    protected function buildQueryString(array $params): string
    {
        $queryParts = [];
        foreach ($params as $key => $value) {
            $encodedValue = str_replace('+', '%20', urlencode((string)$value));
            $queryParts[] = $key . '=' . $encodedValue;
        }

        return implode('&', $queryParts);
    }

    /**
     * Format values for OData
     */
    protected function formatValue(mixed $value): string
    {
        if (is_string($value)) {
            /* Escape single quotes */
            return "'" . str_replace("'", "''", $value) . "'";
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if ($value instanceof \DateTime) {
            return $value->format('Y-m-d\TH:i:s\Z');
        }

        return (string)$value;
    }

    /**
     * Debug: Display query parameters
     */
    public function toODataQuery(): array
    {
        return $this->buildODataQuery();
    }

    /**
     * Validate query combination for POST requests
     * POST allows: select, expand, format
     * POST forbids: filter, orderBy, top, entityId
     *
     * @throws InvalidQueryCombinationException
     */
    protected function validatePostCombination(): void
    {
        $invalid = [];

        if (!empty($this->filters)) {
            $invalid[] = 'filter';
        }
        if ($this->orderBy !== null) {
            $invalid[] = 'orderBy';
        }
        if ($this->top !== null) {
            $invalid[] = 'top';
        }
        if ($this->hasEntityId()) {
            $invalid[] = 'entity ID';
        }

        if (!empty($invalid)) {
            throw InvalidQueryCombinationException::forMethod('POST', implode(', ', $invalid));
        }
    }

    /**
     * Validate query combination for PATCH requests
     * PATCH allows: select, expand, format, entityId (required)
     * PATCH forbids: filter, orderBy, top
     *
     * @throws InvalidQueryCombinationException
     */
    protected function validatePatchCombination(): void
    {
        $invalid = [];

        if (!empty($this->filters)) {
            $invalid[] = 'filter';
        }
        if ($this->orderBy !== null) {
            $invalid[] = 'orderBy';
        }
        if ($this->top !== null) {
            $invalid[] = 'top';
        }

        if (!empty($invalid)) {
            throw InvalidQueryCombinationException::forMethod('PATCH', implode(', ', $invalid));
        }
    }

    /**
     * Validate query combination for DELETE requests
     * DELETE allows: format, entityId (required)
     * DELETE forbids: filter, orderBy, top, select, expand
     *
     * @throws InvalidQueryCombinationException
     */
    protected function validateDeleteCombination(): void
    {
        $invalid = [];

        if (!empty($this->filters)) {
            $invalid[] = 'filter';
        }
        if (!empty($this->selects)) {
            $invalid[] = 'select';
        }
        if (!empty($this->expand)) {
            $invalid[] = 'expand';
        }
        if ($this->orderBy !== null) {
            $invalid[] = 'orderBy';
        }
        if ($this->top !== null) {
            $invalid[] = 'top';
        }

        if (!empty($invalid)) {
            throw InvalidQueryCombinationException::forMethod('DELETE', implode(', ', $invalid));
        }
    }

    /**
     * Validate query combination for GET with entity ID
     * GET with ID allows: select, expand, format
     * GET with ID forbids: filter, orderBy, top
     *
     * @throws InvalidQueryCombinationException
     */
    protected function validateGetWithId(): void
    {
        $invalid = [];

        if (!empty($this->filters)) {
            $invalid[] = 'filter';
        }
        if ($this->orderBy !== null) {
            $invalid[] = 'orderBy';
        }
        if ($this->top !== null) {
            $invalid[] = 'top';
        }

        if (!empty($invalid)) {
            throw InvalidQueryCombinationException::forMethod('GET (single entity)', implode(', ', $invalid));
        }
    }

    /**
     * Validate that an entity ID is set for operations that require it
     *
     * @throws MissingEntityIdentifierException
     */
    protected function validateEntityIdentifier(string $httpMethod): void
    {
        if (!$this->hasEntityId()) {
            throw MissingEntityIdentifierException::forMethod($httpMethod);
        }
    }

    /**
     * Execute GET request for single entity
     *
     * @return array The entity data
     */
    protected function executeGetSingle(): array
    {
        $path = $this->buildPathWithId();
        $odataParams = $this->buildODataQueryForSingleEntity();

        return $this->service->getClient()
            ->get($path, $odataParams)
            ->json();
    }

    /**
     * Prepare this query for batch request (list query)
     * Returns array structure for BatchRequest->addRequest()
     *
     * @return array{method: string, path: string, body: array|null}
     */
    public function prepareForBatch(): array
    {
        $path = $this->service->getClient()->entityPath($this->resource);
        $odataParams = $this->buildODataQuery();

        /* Build full path with query string */
        if (!empty($odataParams)) {
            $path .= '?' . $this->buildQueryString($odataParams);
        }

        return [
            'method' => 'GET',
            'path' => $path,
            'body' => null,
        ];
    }
}
