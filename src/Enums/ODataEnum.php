<?php

namespace Contoweb\AbacusApi\Enums;

class ODataEnum
{
    public function __construct(
        public readonly string $namespace,
        public readonly string $value,
    ) {}

    /**
     * Create a new OData enum value.
     *
     * @param  string  $namespace  The full enum namespace (e.g., 'ch.abacus.orde.ProductType')
     * @param  string  $value  The enum value (e.g., 'Article')
     */
    public static function make(string $namespace, string $value): self
    {
        return new self($namespace, $value);
    }

    /**
     * Format as OData enum string.
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
