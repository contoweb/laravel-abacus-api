<?php

namespace Contoweb\AbacusApi\Tests\Unit\Actions\PriceFinding;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\Actions\PriceFinding\PriceFindingService;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\ProductPricingRequest;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\ProductsPricingRequest;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\RequestPosition;
use Contoweb\AbacusApi\Actions\PriceFinding\Responses\ProductPriceResult;
use Contoweb\AbacusApi\Actions\PriceFinding\Responses\ProductsPriceResult;
use Contoweb\AbacusApi\Exceptions\AbacusBadRequestException;
use Contoweb\AbacusApi\Tests\Fixtures\ResponseFixtures;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class PriceFindingServiceTest extends TestCase
{
    protected PriceFindingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PriceFindingService(
            new AbacusODataClient($this->makeCredentialsProvider())
        );
    }

    #[Test]
    public function it_posts_to_the_find_product_price_endpoint_with_wrapper_key(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response(ResponseFixtures::successfulTokenResponse(), 200),
            '*/api/entity/v1/mandants/1212/FindProductPrice' => Http::response(ResponseFixtures::productPriceResponse(), 200),
        ]);

        $this->service->findProductPrice(new ProductPricingRequest(
            customerNumber: 10042,
            currency: 'CHF',
            calculationDate: '2024-06-01',
            position: new RequestPosition(productId: 1234, quantity: 5),
        ));

        Http::assertSent(function ($request) {
            return $request->method() === 'POST' &&
                   str_ends_with($request->url(), '/api/entity/v1/mandants/1212/FindProductPrice') &&
                   $request->data() === [
                       'ProductPricingRequest' => [
                           'Currency' => 'CHF',
                           'CustomerNumber' => 10042,
                           'CalculationDate' => '2024-06-01',
                           'Position' => [
                               'ProductId' => 1234,
                               'Quantity' => 5.0,
                               'Division' => 0,
                               'FixPriceNumberArticle' => -1,
                               'FixPriceNumberService' => -1,
                           ],
                       ],
                   ];
        });
    }

    #[Test]
    public function it_maps_the_find_product_price_response_to_a_result_dto(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response(ResponseFixtures::successfulTokenResponse(), 200),
            '*/api/entity/v1/mandants/1212/FindProductPrice' => Http::response(ResponseFixtures::productPriceResponse(), 200),
        ]);

        $result = $this->service->findProductPrice(new ProductPricingRequest(
            position: new RequestPosition(productId: 1234),
        ));

        $this->assertInstanceOf(ProductPriceResult::class, $result);
        $this->assertEquals('req-1', $result->requestKey);
        $this->assertEquals('SalesPrice', $result->position->priceType);
        $this->assertEquals(107.70, $result->position->perUnitValue->priceInclTax);
        $this->assertEquals(119.67, $result->position->perUnitValue->priceInclTaxBeforDiscount);
        $this->assertEquals(7.7, $result->position->taxDetail->rate);
        $this->assertCount(1, $result->position->discountDetails);
        $this->assertEquals(10.0, $result->position->discountDetails[0]->percent);
        $this->assertCount(1, $result->position->graduationDetails);
        $this->assertEquals('Piece', $result->position->graduationDetails[0]->scaleType);
        $this->assertCount(1, $result->position->feeDetails);
        $this->assertEquals(2.00, $result->position->feeDetails[0]->priceExcludingTax);
        $this->assertEquals(ResponseFixtures::productPriceResponse(), $result->raw);
    }

    #[Test]
    public function it_posts_overview_and_shopping_cart_to_their_endpoints(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response(ResponseFixtures::successfulTokenResponse(), 200),
            '*/api/entity/v1/mandants/1212/FindProductsPriceOverview' => Http::response(ResponseFixtures::productsPriceResponse(), 200),
            '*/api/entity/v1/mandants/1212/FindProductsPriceShoppingCart' => Http::response(ResponseFixtures::productsPriceResponse(), 200),
        ]);

        $request = new ProductsPricingRequest(
            customerNumber: 10042,
            currency: 'CHF',
            positions: [
                new RequestPosition(productId: 1234, quantity: 2),
                new RequestPosition(productId: 5678),
            ],
        );

        $overview = $this->service->findProductsPriceOverview($request);
        $cart = $this->service->findProductsPriceShoppingCart($request);

        $this->assertInstanceOf(ProductsPriceResult::class, $overview);
        $this->assertCount(2, $overview->positions);
        $this->assertCount(1, $overview->documentDiscounts);
        $this->assertEquals(2.5, $cart->documentDiscounts[0]->percent);

        foreach (['FindProductsPriceOverview', 'FindProductsPriceShoppingCart'] as $action) {
            Http::assertSent(function ($request) use ($action) {
                $data = $request->data();

                return str_ends_with($request->url(), "/api/entity/v1/mandants/1212/{$action}") &&
                       isset($data['ProductsPricingRequest']) &&
                       count($data['ProductsPricingRequest']['Positions']) === 2 &&
                       $data['ProductsPricingRequest']['Positions'][0]['ProductId'] === 1234;
            });
        }
    }

    #[Test]
    public function it_accepts_a_plain_array_request(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response(ResponseFixtures::successfulTokenResponse(), 200),
            '*/api/entity/v1/mandants/1212/FindProductPrice' => Http::response(ResponseFixtures::productPriceResponse(), 200),
        ]);

        $this->service->findProductPrice([
            'CustomerNumber' => 10042,
            'Position' => ['ProductId' => 1234],
        ]);

        Http::assertSent(function ($request) {
            return $request->data() === [
                'ProductPricingRequest' => [
                    'CustomerNumber' => 10042,
                    'Position' => ['ProductId' => 1234],
                ],
            ];
        });
    }

    #[Test]
    public function it_throws_a_bad_request_exception_on_400(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response(ResponseFixtures::successfulTokenResponse(), 200),
            '*/api/entity/v1/mandants/1212/FindProductPrice' => Http::response(ResponseFixtures::errorResponse(), 400),
        ]);

        $this->expectException(AbacusBadRequestException::class);

        $this->service->findProductPrice(new ProductPricingRequest(
            position: new RequestPosition(productId: 1234),
        ));
    }
}
