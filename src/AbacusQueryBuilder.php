<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Enums\ODataOperator;
use Illuminate\Support\Collection;

/**
 * @template TypeModel of \Contoweb\AbacusApi\Models\AbacusModel
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

    public function __construct(AbacusService $service, string $resource, ?string $modelClass = null)
    {
        $this->service    = $service;
        $this->resource   = $resource;
        $this->modelClass = $modelClass;
    }

    /**
     * Filter with OData operators
     * Example: ->where('LastName', ODataOperator::EQUALS, 'Müller')
     * Example: ->where('LastName', 'eq', 'Müller')
     *
     * @return $this
     */
    public function where(string $field, ODataOperator | string $operator, mixed $value)
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
     * Combine multiple filters (AND linkage)
     */
    public function whereAnd(string $field, ODataOperator | string $operator, mixed $value)
    {
        return $this->where($field, $operator, $value);
    }

    /**
     * $select - Query only specific properties
     * Example: ->select(['LastName', 'AddressNumber'])
     */
    public function select(array | string $fields)
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
    public function top(int $limit)
    {
        $this->top = $limit;

        return $this;
    }

    /**
     * Alias für top() (Laravel-like)
     */
    public function limit(int $limit)
    {
        return $this->top($limit);
    }

    /**
     * Alias für top() (Laravel-like)
     */
    public function take(int $limit)
    {
        return $this->top($limit);
    }

    /**
     * $orderby - Sort by attribute (asc or desc)
     * Example: ->orderBy('LastName', 'desc')
     * IMPORTANT: Only one orderBy possible, further calls override previous ones
     */
    public function orderBy(string $field, string $direction = 'asc')
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
    public function expand(array | string $relations)
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
    public function format(string $format)
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
    public function getFirstPage()
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
     * @return Collection<int, TypeModel>
     */
    public function get()
    {
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
     */
    public function find($id)
    {
        return $this->service->find($this->resource, $id);
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
     * Assemble OData query parameters
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
}