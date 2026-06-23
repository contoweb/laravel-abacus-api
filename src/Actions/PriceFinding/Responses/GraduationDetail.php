<?php

namespace Contoweb\AbacusApi\Actions\PriceFinding\Responses;

class GraduationDetail
{
    public function __construct(
        public readonly ?string $valueType = null,
        public readonly ?string $scaleType = null,
        public readonly ?string $decreaseType = null,
        public readonly ?int $quantityScale = null,
        public readonly ?float $decreaseScale = null,
        public readonly ?string $activeFrom = null,
        public readonly ?string $activeTo = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            valueType: $data['ValueType'] ?? null,
            scaleType: $data['ScaleType'] ?? null,
            decreaseType: $data['DecreaseType'] ?? null,
            quantityScale: $data['QuantityScale'] ?? null,
            decreaseScale: $data['DecreaseScale'] ?? null,
            activeFrom: $data['ActiveFrom'] ?? null,
            activeTo: $data['ActiveTo'] ?? null,
        );
    }
}
