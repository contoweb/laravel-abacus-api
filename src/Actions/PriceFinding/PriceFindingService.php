<?php

namespace Contoweb\AbacusApi\Actions\PriceFinding;

use Contoweb\AbacusApi\AbacusService;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\ProductPricingRequest;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\ProductsPricingRequest;
use Contoweb\AbacusApi\Actions\PriceFinding\Responses\ProductPriceResult;
use Contoweb\AbacusApi\Actions\PriceFinding\Responses\ProductsPriceResult;
use Contoweb\AbacusApi\Exceptions\AbacusAuthenticationException;
use Contoweb\AbacusApi\Exceptions\AbacusBadRequestException;
use Contoweb\AbacusApi\Exceptions\AbacusForbiddenException;
use Contoweb\AbacusApi\Exceptions\AbacusRateLimitException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class PriceFindingService
{
    public function __construct(
        protected readonly AbacusService $abacus,
    ) {}

    /**
     * Find the price of a single product position.
     * POST /api/entity/v1/mandants/{mandate}/FindProductPrice
     *
     * @param  ProductPricingRequest|array  $request  Typed request or the plain inner request object
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws AbacusAuthenticationException
     * @throws AbacusBadRequestException
     * @throws AbacusForbiddenException
     * @throws AbacusRateLimitException
     */
    public function findProductPrice(ProductPricingRequest|array $request): ProductPriceResult
    {
        return ProductPriceResult::fromArray(
            $this->callAction('FindProductPrice', 'ProductPricingRequest', $request)
        );
    }

    /**
     * Find the current prices of multiple product positions.
     * POST /api/entity/v1/mandants/{mandate}/FindProductsPriceOverview
     *
     * @param  ProductsPricingRequest|array  $request  Typed request or the plain inner request object
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws AbacusAuthenticationException
     * @throws AbacusBadRequestException
     * @throws AbacusForbiddenException
     * @throws AbacusRateLimitException
     */
    public function findProductsPriceOverview(ProductsPricingRequest|array $request): ProductsPriceResult
    {
        return ProductsPriceResult::fromArray(
            $this->callAction('FindProductsPriceOverview', 'ProductsPricingRequest', $request)
        );
    }

    /**
     * Find the prices of multiple product positions evaluated as one cart,
     * including order-level/cross-product discounts.
     * POST /api/entity/v1/mandants/{mandate}/FindProductsPriceShoppingCart
     *
     * @param  ProductsPricingRequest|array  $request  Typed request or the plain inner request object
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws AbacusAuthenticationException
     * @throws AbacusBadRequestException
     * @throws AbacusForbiddenException
     * @throws AbacusRateLimitException
     */
    public function findProductsPriceShoppingCart(ProductsPricingRequest|array $request): ProductsPriceResult
    {
        return ProductsPriceResult::fromArray(
            $this->callAction('FindProductsPriceShoppingCart', 'ProductsPricingRequest', $request)
        );
    }

    /**
     * Call an unbound OData action with the wrapped request body.
     */
    protected function callAction(string $action, string $wrapperKey, ProductPricingRequest|ProductsPricingRequest|array $request): array
    {
        $payload = is_array($request) ? $request : $request->toArray();

        return $this->abacus->callUnboundAction($action, $wrapperKey, $payload);
    }
}
