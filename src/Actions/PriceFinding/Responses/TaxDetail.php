<?php

namespace Contoweb\AbacusApi\Actions\PriceFinding\Responses;

class TaxDetail
{
    public function __construct(
        public readonly ?string $code = null,
        public readonly ?float $rate = null,
        public readonly ?bool $inclusive = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: $data['Code'] ?? null,
            rate: $data['Rate'] ?? null,
            inclusive: $data['Inclusive'] ?? null,
        );
    }
}
