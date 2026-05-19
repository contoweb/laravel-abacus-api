<?php

namespace Contoweb\AbacusApi\Reports\Facades;

use Contoweb\AbacusApi\Reports\AbacusReportsService;
use Contoweb\AbacusApi\Reports\Contracts\Report;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static static run(Report $report)
 * @method static Collection toCollection()
 * @method static array toArray()
 * @method static string|null raw()
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
