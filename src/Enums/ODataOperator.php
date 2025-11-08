<?php

namespace Contoweb\AbacusApi\Enums;

/**
 * Abacus OData Query Operators
 *
 * Provides IDE autocomplete support for available OData filter operators.
 * Use these constants when building queries with the where() method.
 *
 * Supported operators:
 * - eq: equals
 * - ge: greater than or equal
 * - gt: greater than
 * - le: less than or equal
 * - lt: less than
 * - and: connect multiple filter parameters (handled automatically by whereAnd)
 */
enum ODataOperator: string
{
    /* Comparison Operators */
    case EQUALS                = 'eq';
    case GREATER_THAN          = 'gt';
    case GREATER_THAN_OR_EQUAL = 'ge';
    case LESS_THAN             = 'lt';
    case LESS_THAN_OR_EQUAL    = 'le';

    /**
     * Get all supported operator values
     */
    public static function values(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    /**
     * Check if an operator is supported
     */
    public static function isValid(string $operator): bool
    {
        return in_array($operator, self::values());
    }
}
