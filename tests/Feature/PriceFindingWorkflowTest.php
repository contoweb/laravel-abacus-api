<?php

namespace Contoweb\AbacusApi\Tests\Feature;

use Contoweb\AbacusApi\Actions\PriceFinding\Facades\PriceFinder;
use Contoweb\AbacusApi\Actions\PriceFinding\PriceFindingService;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\ProductPricingRequest;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\ProductsPricingRequest;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\RequestPosition;
use Contoweb\AbacusApi\Tests\Fixtures\ResponseFixtures;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class PriceFindingWorkflowTest extends TestCase
{
    #[Test]
    public function it_finds_a_product_price_via_the_facade(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response(ResponseFixtures::successfulTokenResponse(), 200),
            '*/api/entity/v1/mandants/1212/FindProductPrice' => Http::response(ResponseFixtures::productPriceResponse(), 200),
        ]);

        $result = PriceFinder::findProductPrice(new ProductPricingRequest(
            customerNumber: 10042,
            currency: 'CHF',
            position: new RequestPosition(productId: 1234, quantity: 5),
        ));

        $this->assertEquals(107.70, $result->position->perUnitValue->priceInclTax);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST' &&
                   str_ends_with($request->url(), '/api/entity/v1/mandants/1212/FindProductPrice');
        });
    }

    #[Test]
    public function it_finds_shopping_cart_prices_via_the_facade(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response(ResponseFixtures::successfulTokenResponse(), 200),
            '*/api/entity/v1/mandants/1212/FindProductsPriceShoppingCart' => Http::response(ResponseFixtures::productsPriceResponse(), 200),
        ]);

        $result = PriceFinder::findProductsPriceShoppingCart(new ProductsPricingRequest(
            customerNumber: 10042,
            positions: [
                new RequestPosition(productId: 1234),
                new RequestPosition(productId: 5678),
            ],
        ));

        $this->assertCount(2, $result->positions);
        $this->assertCount(1, $result->documentDiscounts);
    }

    #[Test]
    public function it_resolves_fresh_service_instances_from_the_container(): void
    {
        $first = $this->app->make(PriceFindingService::class);
        $second = $this->app->make(PriceFindingService::class);

        $this->assertInstanceOf(PriceFindingService::class, $first);
        $this->assertNotSame($first, $second);
    }
}
