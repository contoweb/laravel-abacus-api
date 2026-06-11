<?php

namespace Contoweb\AbacusApi\Actions\PriceFinding\Responses;

class DiscountDetail
{
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?float $percent = null,
        public readonly ?bool $useSubTotal = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['Type'] ?? null,
            percent: $data['Percent'] ?? null,
            useSubTotal: $data['UseSubTotal'] ?? null,
        );
    }
}
