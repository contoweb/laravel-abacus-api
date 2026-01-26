<?php

namespace Contoweb\AbacusApi\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when an HTTP method requires an entity ID but none was provided.
 *
 * PATCH and DELETE operations require an entity ID to identify which
 * entity to modify or delete.
 */
class MissingEntityIdentifierException extends InvalidArgumentException
{
    public static function forMethod(string $httpMethod): self
    {
        return new self(
            "{$httpMethod} requires an entity ID. Use ->id(\$id) to specify the entity."
        );
    }
}
