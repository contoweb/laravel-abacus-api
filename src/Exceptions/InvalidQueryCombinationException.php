<?php

namespace Contoweb\AbacusApi\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when query methods are combined in an invalid way for a given HTTP method.
 *
 * For example:
 * - Using filter(), orderBy(), or top() with PATCH/DELETE
 * - Using filter(), orderBy(), or top() when retrieving a single entity by ID
 */
class InvalidQueryCombinationException extends InvalidArgumentException
{
    public static function forMethod(string $httpMethod, string $invalidOptions): self
    {
        return new self(
            "{$httpMethod} does not support: {$invalidOptions}"
        );
    }

    public static function filterNotAllowed(string $httpMethod): self
    {
        return new self(
            "{$httpMethod} does not support filter(). Remove where() clauses."
        );
    }

    public static function paginationNotAllowed(string $httpMethod): self
    {
        return new self(
            "{$httpMethod} does not support pagination options (top/orderBy)."
        );
    }
}
