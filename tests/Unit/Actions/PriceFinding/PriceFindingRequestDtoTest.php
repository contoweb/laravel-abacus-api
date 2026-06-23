<?php

namespace Contoweb\AbacusApi\Tests\Unit\Actions\PriceFinding;

use Contoweb\AbacusApi\Actions\PriceFinding\Requests\DeliveryAddressCondition;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\ProductPricingRequest;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\ProductsPricingRequest;
use Contoweb\AbacusApi\Actions\PriceFinding\Requests\RequestPosition;
use Contoweb\AbacusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PriceFindingRequestDtoTest extends TestCase
{
    #[Test]
    public function request_position_serializes_defaults_and_filters_nulls(): void
    {
        $position = new RequestPosition(productId: 1234);

        $this->assertEquals([
            'ProductId' => 1234,
            'Quantity' => 1.0,
            'Division' => 0,
            'FixPriceNumberArticle' => -1,
            'FixPriceNumberService' => -1,
        ], $position->toArray());
    }

    #[Test]
    public function request_position_serializes_all_fields_in_pascal_case(): void
    {
        $position = new RequestPosition(
            productId: 1234,
            variantId: 2,
            quantity: 5.0,
            division: 1,
            fixPriceNumberArticle: 10,
            fixPriceNumberService: 20,
            requestKey: 'pos-1',
        );

        $this->assertEquals([
            'RequestKey' => 'pos-1',
            'ProductId' => 1234,
            'VariantId' => 2,
            'Quantity' => 5.0,
            'Division' => 1,
            'FixPriceNumberArticle' => 10,
            'FixPriceNumberService' => 20,
        ], $position->toArray());
    }

    #[Test]
    public function product_pricing_request_filters_nulls(): void
    {
        $request = new ProductPricingRequest;

        $this->assertEquals([], $request->toArray());
    }

    #[Test]
    public function product_pricing_request_formats_a_date_time_calculation_date(): void
    {
        $request = new ProductPricingRequest(
            calculationDate: new \DateTimeImmutable('2024-06-01 15:30:00'),
        );

        $this->assertEquals(['CalculationDate' => '2024-06-01'], $request->toArray());
    }

    #[Test]
    public function product_pricing_request_keeps_a_string_calculation_date(): void
    {
        $request = new ProductPricingRequest(calculationDate: '2024-06-01');

        $this->assertEquals(['CalculationDate' => '2024-06-01'], $request->toArray());
    }

    #[Test]
    public function product_pricing_request_nests_the_position(): void
    {
        $request = new ProductPricingRequest(
            customerNumber: 10042,
            currency: 'CHF',
            position: new RequestPosition(productId: 1234),
            requestKey: 'req-1',
        );

        $this->assertEquals([
            'RequestKey' => 'req-1',
            'Currency' => 'CHF',
            'CustomerNumber' => 10042,
            'Position' => [
                'ProductId' => 1234,
                'Quantity' => 1.0,
                'Division' => 0,
                'FixPriceNumberArticle' => -1,
                'FixPriceNumberService' => -1,
            ],
        ], $request->toArray());
    }

    #[Test]
    public function products_pricing_request_nests_positions_and_delivery_address_condition(): void
    {
        $request = new ProductsPricingRequest(
            customerNumber: 10042,
            currency: 'CHF',
            calculationDate: '2024-06-01',
            positions: [
                new RequestPosition(productId: 1234),
                new RequestPosition(productId: 5678, quantity: 3.0),
            ],
            deliveryAddressCondition: new DeliveryAddressCondition(
                deliveryAddressNumber: 7,
                conditionNumber: 1,
            ),
            includeCalculationFee: true,
            includeCalculationDocumentDiscount: false,
            requestKey: 'req-1',
        );

        $data = $request->toArray();

        $this->assertEquals('req-1', $data['RequestKey']);
        $this->assertEquals('CHF', $data['Currency']);
        $this->assertEquals(10042, $data['CustomerNumber']);
        $this->assertEquals('2024-06-01', $data['CalculationDate']);
        $this->assertTrue($data['IncludeCalculationFee']);
        $this->assertFalse($data['IncludeCalculationDocumentDiscount']);
        $this->assertEquals(-1, $data['FixPriceNumberArticle']);
        $this->assertEquals(-1, $data['FixPriceNumberService']);
        $this->assertEquals([
            'DeliveryAddressNumber' => 7,
            'ConditionNumber' => 1,
        ], $data['DeliveryAddressCondition']);
        $this->assertCount(2, $data['Positions']);
        $this->assertEquals(5678, $data['Positions'][1]['ProductId']);
        $this->assertEquals(3.0, $data['Positions'][1]['Quantity']);
    }

    #[Test]
    public function products_pricing_request_serializes_defaults_with_empty_positions(): void
    {
        $request = new ProductsPricingRequest;

        $this->assertEquals([
            'FixPriceNumberArticle' => -1,
            'FixPriceNumberService' => -1,
            'Positions' => [],
        ], $request->toArray());
    }
}
