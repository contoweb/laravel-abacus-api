<?php

namespace Contoweb\AbacusApi\Enums;

/**
 * OData Enum Value Wrapper
 *
 * Use this class to pass enum values to where() filters.
 * Enum values in OData have a special format: Namespace.Type'Value'
 * and must NOT be wrapped in additional quotes.
 *
 * @example
 * // Filter by ProductType enum
 * Product::query()
 *     ->where('Type', 'eq', ODataQueryString::enum('ch.abacus.orde.ProductType', 'Article'))
 *     ->get();
 *
 * // Results in: $filter=Type eq ch.abacus.orde.ProductType'Article'
 */
class ODataEnum
{
    public function __construct(
        public readonly string $namespace,
        public readonly string $value,
    ) {}

    /**
     * Create a new OData enum value
     *
     * @param  string  $namespace  The full enum namespace (e.g., 'ch.abacus.orde.ProductType')
     * @param  string  $value  The enum value (e.g., 'Article')
     */
    public static function make(string $namespace, string $value): self
    {
        return new self($namespace, $value);
    }

    /**
     * Format as OData enum string
     *
     * @return string Formatted as: Namespace.Type'Value'
     */
    public function toODataString(): string
    {
        return "{$this->namespace}'{$this->value}'";
    }

    public function __toString(): string
    {
        return $this->toODataString();
    }
}
