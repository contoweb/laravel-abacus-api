<?php

namespace Contoweb\AbacusApi\Actions\PriceFinding\Responses;

class FeeDetail
{
    public function __construct(
        public readonly ?int $feeId = null,
        public readonly ?float $priceIncludingTax = null,
        public readonly ?float $priceExcludingTax = null,
        public readonly ?TaxDetail $taxDetail = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            feeId: $data['FeeId'] ?? null,
            priceIncludingTax: $data['PriceIncludingTax'] ?? null,
            priceExcludingTax: $data['PriceExcludingTax'] ?? null,
            taxDetail: isset($data['TaxDetail']) ? TaxDetail::fromArray($data['TaxDetail']) : null,
        );
    }
}
