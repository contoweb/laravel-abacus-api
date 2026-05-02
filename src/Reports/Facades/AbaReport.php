<?php

namespace Contoweb\AbacusApi\Reports\Facades;

use Contoweb\AbacusApi\Reports\AbacusReportsService;
use Contoweb\AbacusApi\Reports\Abstracts\Report;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static AbacusReportsService parameter(array|string $parameters)
 * @method static AbacusReportsService cache(int $ttl = 3600, ?string $cacheKey = null)
 * @method static Collection collection(Report $report)
 *
 * @see AbacusReportsService
 */
class AbaReport extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AbacusReportsService::class;
    }
}
