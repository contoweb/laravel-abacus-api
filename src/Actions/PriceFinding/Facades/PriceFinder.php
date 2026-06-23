<?php

namespace Contoweb\AbacusApi\Actions\PriceFinding\Facades;

use Contoweb\AbacusApi\Actions\PriceFinding\PriceFindingService;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\ProductPricingRequest;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\ProductsPricingRequest;
use Contoweb\AbacusApi\Actions\PriceFinding\Responses\ProductPriceResult;
use Contoweb\AbacusApi\Actions\PriceFinding\Responses\ProductsPriceResult;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ProductPriceResult findProductPrice(ProductPricingRequest|array $request)
 * @method static ProductsPriceResult findProductsPriceOverview(ProductsPricingRequest|array $request)
 * @method static ProductsPriceResult findProductsPriceShoppingCart(ProductsPricingRequest|array $request)
 *
 * @see PriceFindingService
 */
class PriceFinder extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PriceFindingService::class;
    }
}
