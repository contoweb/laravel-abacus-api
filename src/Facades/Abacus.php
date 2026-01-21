<?php

namespace Contoweb\AbacusApi\Facades;

use Contoweb\AbacusApi\AbacusService;
use Contoweb\AbacusApi\BatchRequest;
use Illuminate\Support\Facades\Facade;

/**
 * @method static BatchRequest batch()
 * @method static array query(string $resource, array $odataParams = [])
 * @method static array queryWithMetadata(string $resource, array $odataParams = [])
 * @method static array getNextPage(string $nextLink)
 * @method static array find(string $resource, mixed $id, array $odataParams = [])
 * @method static mixed findProperty(string $resource, mixed $id, string $property)
 * @method static array create(string $resource, array $data)
 * @method static array update(string $resource, mixed $id, array $data)
 * @method static array replace(string $resource, mixed $id, array $data)
 * @method static bool delete(string $resource, mixed $id)
 * @method static array listEntityIds()
 * @method static string metadata()
 *
 * @see AbacusService
 */
class Abacus extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AbacusService::class;
    }
}
