<?php

namespace Contoweb\AbacusApi\Tests\Unit\Actions\PriceFinding;

use Contoweb\AbacusApi\Actions\PriceFinding\Responses\CalculatedPosition;
use Contoweb\AbacusApi\Actions\PriceFinding\Responses\ProductPriceResult;
use Contoweb\AbacusApi\Actions\PriceFinding\Responses\ProductsPriceResult;
use Contoweb\AbacusApi\Tests\Fixtures\ResponseFixtures;
use Contoweb\AbacusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PriceFindingResponseDtoTest extends TestCase
{
    #[Test]
    public function calculated_position_maps_all_fields(): void
    {
        $position = CalculatedPosition::fromArray(ResponseFixtures::calculatedPosition());

        $this->assertEquals('pos-1', $position->requestKey);
        $this->assertEquals('SalesPrice', $position->priceType);

        $this->assertEquals(107.70, $position->perUnitValue->priceInclTax);
        $this->assertEquals(100.00, $position->perUnitValue->priceExclTax);
        $this->assertEquals(119.67, $position->perUnitValue->priceInclTaxBeforDiscount);
        $this->assertEquals(111.11, $position->perUnitValue->priceExclTaxBeforDiscount);

        $this->assertEquals(5.0, $position->quantityDetail->ordered);
        $this->assertEquals(0.0, $position->quantityDetail->shipped);
        $this->assertEquals(5.0, $position->quantityDetail->charged);

        $this->assertEquals('N81', $position->taxDetail->code);
        $this->assertEquals(7.7, $position->taxDetail->rate);
        $this->assertFalse($position->taxDetail->inclusive);

        $this->assertEquals('CustomerDiscount', $position->discountDetails[0]->type);
        $this->assertEquals(10.0, $position->discountDetails[0]->percent);
        $this->assertFalse($position->discountDetails[0]->useSubTotal);

        $this->assertEquals('Amount', $position->graduationDetails[0]->valueType);
        $this->assertEquals('Piece', $position->graduationDetails[0]->scaleType);
        $this->assertEquals('Percent', $position->graduationDetails[0]->decreaseType);
        $this->assertEquals(10, $position->graduationDetails[0]->quantityScale);
        $this->assertEquals(5.0, $position->graduationDetails[0]->decreaseScale);
        $this->assertEquals('2024-01-01', $position->graduationDetails[0]->activeFrom);
        $this->assertEquals('2024-12-31', $position->graduationDetails[0]->activeTo);

        $this->assertEquals(1, $position->feeDetails[0]->feeId);
        $this->assertEquals(2.15, $position->feeDetails[0]->priceIncludingTax);
        $this->assertEquals(2.00, $position->feeDetails[0]->priceExcludingTax);
        $this->assertEquals('N81', $position->feeDetails[0]->taxDetail->code);
    }

    #[Test]
    public function calculated_position_tolerates_an_empty_response(): void
    {
        $position = CalculatedPosition::fromArray([]);

        $this->assertNull($position->requestKey);
        $this->assertNull($position->priceType);
        $this->assertNull($position->perUnitValue);
        $this->assertNull($position->quantityDetail);
        $this->assertNull($position->taxDetail);
        $this->assertEquals([], $position->discountDetails);
        $this->assertEquals([], $position->graduationDetails);
        $this->assertEquals([], $position->feeDetails);
    }

    #[Test]
    public function product_price_result_preserves_the_raw_response(): void
    {
        $data = ResponseFixtures::productPriceResponse();

        $result = ProductPriceResult::fromArray($data);

        $this->assertEquals('req-1', $result->requestKey);
        $this->assertEquals('pos-1', $result->position->requestKey);
        $this->assertEquals($data, $result->raw);
    }

    #[Test]
    public function product_price_result_tolerates_an_empty_response(): void
    {
        $result = ProductPriceResult::fromArray([]);

        $this->assertNull($result->requestKey);
        $this->assertNull($result->position);
        $this->assertEquals([], $result->raw);
    }

    #[Test]
    public function products_price_result_maps_positions_and_document_discounts(): void
    {
        $data = ResponseFixtures::productsPriceResponse(3);

        $result = ProductsPriceResult::fromArray($data);

        $this->assertEquals('req-1', $result->requestKey);
        $this->assertCount(3, $result->positions);
        $this->assertEquals('pos-2', $result->positions[1]->requestKey);
        $this->assertCount(1, $result->documentDiscounts);
        $this->assertEquals(1, $result->documentDiscounts[0]->number);
        $this->assertEquals(2.5, $result->documentDiscounts[0]->percent);
        $this->assertEquals(5.40, $result->documentDiscounts[0]->amountInclTax);
        $this->assertFalse($result->documentDiscounts[0]->isSubTotal);
        $this->assertEquals($data, $result->raw);
    }

    #[Test]
    public function products_price_result_tolerates_an_empty_response(): void
    {
        $result = ProductsPriceResult::fromArray([]);

        $this->assertNull($result->requestKey);
        $this->assertEquals([], $result->positions);
        $this->assertEquals([], $result->documentDiscounts);
        $this->assertEquals([], $result->raw);
    }
}
