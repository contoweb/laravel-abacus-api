<?php

namespace Contoweb\AbacusApi\Actions\PriceFinding\Responses;

class QuantityDetail
{
    public function __construct(
        public readonly ?float $ordered = null,
        public readonly ?float $shipped = null,
        public readonly ?float $charged = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            ordered: $data['Ordered'] ?? null,
            shipped: $data['Shipped'] ?? null,
            charged: $data['Charged'] ?? null,
        );
    }
}
