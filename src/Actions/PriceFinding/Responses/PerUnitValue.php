<?php

namespace Contoweb\AbacusApi\Actions\PriceFinding\Responses;

/*
 * The "Befor" (instead of "Before") spelling matches the Abacus API
 * field names PriceInclTaxBeforDiscount / PriceExclTaxBeforDiscount.
 */
class PerUnitValue
{
    public function __construct(
        public readonly ?float $priceInclTax = null,
        public readonly ?float $priceExclTax = null,
        public readonly ?float $priceInclTaxBeforDiscount = null,
        public readonly ?float $priceExclTaxBeforDiscount = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            priceInclTax: $data['PriceInclTax'] ?? null,
            priceExclTax: $data['PriceExclTax'] ?? null,
            priceInclTaxBeforDiscount: $data['PriceInclTaxBeforDiscount'] ?? null,
            priceExclTaxBeforDiscount: $data['PriceExclTaxBeforDiscount'] ?? null,
        );
    }
}
