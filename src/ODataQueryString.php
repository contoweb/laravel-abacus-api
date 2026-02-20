<?php

namespace Contoweb\AbacusApi;

use Contoweb\AbacusApi\Enums\ODataEnum;

/**
 * Helper class for building OData query string values
 */
class ODataQueryString
{
    /**
     * Create an OData enum value for use in filters
     *
     * @param  string  $namespace  The full enum namespace (e.g., 'ch.abacus.orde.ProductType')
     * @param  string  $value  The enum value (e.g., 'Article')
     */
    public static function enum(string $namespace, string $value): ODataEnum
    {
        return ODataEnum::make($namespace, $value);
    }
}
