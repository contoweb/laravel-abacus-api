<?php

namespace Contoweb\AbacusApi\Facades;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\AbacusService;
use Contoweb\AbacusApi\Batch\PendingBatchRequest;
use Contoweb\AbacusApi\Credentials\AbacusCredentialsProvider;
use Illuminate\Support\Facades\Facade;

/**
 * @method static PendingBatchRequest newBatch(?string $name = null) Create a new fluent batch builder
 * @method static PendingBatchRequest batch(callable $callback) Create batch with capture closure
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
