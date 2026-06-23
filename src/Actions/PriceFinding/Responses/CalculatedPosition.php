<?php

namespace Contoweb\AbacusApi\Actions\PriceFinding\Responses;

class CalculatedPosition
{
    /**
     * @param  DiscountDetail[]  $discountDetails
     * @param  GraduationDetail[]  $graduationDetails
     * @param  FeeDetail[]  $feeDetails
     */
    public function __construct(
        public readonly ?string $requestKey = null,
        public readonly ?string $priceType = null,
        public readonly ?PerUnitValue $perUnitValue = null,
        public readonly ?QuantityDetail $quantityDetail = null,
        public readonly ?TaxDetail $taxDetail = null,
        public readonly array $discountDetails = [],
        public readonly array $graduationDetails = [],
        public readonly array $feeDetails = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            requestKey: $data['RequestKey'] ?? null,
            priceType: $data['PriceType'] ?? null,
            perUnitValue: isset($data['PerUnitValue']) ? PerUnitValue::fromArray($data['PerUnitValue']) : null,
            quantityDetail: isset($data['QuantityDetail']) ? QuantityDetail::fromArray($data['QuantityDetail']) : null,
            taxDetail: isset($data['TaxDetail']) ? TaxDetail::fromArray($data['TaxDetail']) : null,
            discountDetails: array_map(
                fn (array $detail) => DiscountDetail::fromArray($detail),
                $data['DiscountDetails'] ?? []
            ),
            graduationDetails: array_map(
                fn (array $detail) => GraduationDetail::fromArray($detail),
                $data['GraduationDetails'] ?? []
            ),
            feeDetails: array_map(
                fn (array $detail) => FeeDetail::fromArray($detail),
                $data['FeeDetails'] ?? []
            ),
        );
    }
}
