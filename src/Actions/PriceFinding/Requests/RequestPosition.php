<?php

namespace Contoweb\AbacusApi\Actions\PriceFinding\Requests;

class RequestPosition
{
    public function __construct(
        public readonly ?int $productId = null,
        public readonly ?int $variantId = null,
        public readonly float $quantity = 1.0,
        public readonly int $division = 0,
        public readonly int $fixPriceNumberArticle = -1,
        public readonly int $fixPriceNumberService = -1,
        public readonly ?string $requestKey = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'RequestKey' => $this->requestKey,
            'ProductId' => $this->productId,
            'VariantId' => $this->variantId,
            'Quantity' => $this->quantity,
            'Division' => $this->division,
            'FixPriceNumberArticle' => $this->fixPriceNumberArticle,
            'FixPriceNumberService' => $this->fixPriceNumberService,
        ], fn ($value) => $value !== null);
    }
}
