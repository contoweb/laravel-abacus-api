<?php

namespace Contoweb\AbacusApi\Actions\PriceFinding\Responses;

class DocumentDiscount
{
    public function __construct(
        public readonly ?int $number = null,
        public readonly ?float $percent = null,
        public readonly ?float $amountInclTax = null,
        public readonly ?bool $isSubTotal = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            number: $data['Number'] ?? null,
            percent: $data['Percent'] ?? null,
            amountInclTax: $data['AmountInclTax'] ?? null,
            isSubTotal: $data['IsSubTotal'] ?? null,
        );
    }
}
