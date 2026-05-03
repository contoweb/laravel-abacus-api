<?php

namespace Contoweb\AbacusApi\Reports\Facades;

use Contoweb\AbacusApi\Reports\AbacusReportsService;
use Contoweb\AbacusApi\Reports\Contracts\Report;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
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
