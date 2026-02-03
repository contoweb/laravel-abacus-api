<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Enums\ODataEnum;
use Contoweb\AbacusApi\Enums\ODataOperator;
use InvalidArgumentException;

class ODataQueryState
{
    protected array $filters = [];

    protected array $selects = [];

    protected ?string $orderBy = null;

    protected ?int $top = null;

    protected array $expand = [];

    protected string $format = 'json';

    protected mixed $entityId = null;

    protected array $compositeKey = [];

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

        if ($value instanceof \DateTime) {
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
