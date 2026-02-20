<?php

namespace Contoweb\AbacusApi\Facades;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\AbacusService;
use Contoweb\AbacusApi\Batch\BatchRequest;
use Contoweb\AbacusApi\Batch\BatchRequestItem;
use Contoweb\AbacusApi\Credentials\AbacusCredentialsProvider;
use Illuminate\Support\Facades\Facade;

/**
 * @method static BatchRequest batch(BatchRequestItem ...$requests)
 * @method static array listEntityIds()
 * @method static string metadata()
 * @method static AbacusODataClient client(AbacusCredentialsProvider $credentialsProvider)
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
