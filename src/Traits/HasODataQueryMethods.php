<?php

namespace Contoweb\AbacusApi\Traits;

use Contoweb\AbacusApi\Enums\ODataOperator;

trait HasODataQueryMethods
{
    /**
     * $expand - Expand navigation properties
     * Example: ->expand('Addresses') or ->expand(['Addresses', 'Contacts'])
     */
    public function expand(array|string $relations): static
    {
        $this->queryState->expand(...func_get_args());

        return $this;
    }

    /**
     * $orderby - Sort by attribute (asc or desc)
     * Example: ->orderBy('LastName', 'desc')
     * IMPORTANT: Only one orderBy possible, further calls override previous ones
     */
    public function orderBy(string $field, string $direction = 'asc'): static
    {

        $this->queryState->orderBy($field, $direction);
        return $this;
    }

    /**
     * Alias for top() (Laravel-like)
     */
    public function take(int $limit): static
    {
        return $this->top($limit);
    }

    /**
     * $top - Return only top N elements
     * Example: ->top(10)
     */
    public function top(int $limit): static
    {
        $this->queryState->top($limit);

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
        $this->queryState->select(...func_get_args());

        return $this;
    }

    /**
     * Filter with OData operators
     * Example: ->where('LastName', ODataOperator::EQUALS, 'Müller')
     * Example: ->where('LastName', 'eq', 'Müller')
     *
     * @return $this
     */
    public function where(string $field, ODataOperator|string $operator, mixed $value): static
    {
        $this->queryState->where($field, $operator, $value);

        return $this;
    }

    /**
     * Debug: Display query parameters
     *
     * @return array<string, mixed>
     */
    public
    function toODataQuery(): array
    {
        return $this->queryState->toODataQuery();
    }
}
