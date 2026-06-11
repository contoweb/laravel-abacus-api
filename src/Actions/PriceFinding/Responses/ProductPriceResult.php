<?php

namespace Contoweb\AbacusApi\Actions\PriceFinding\Responses;

/* Result of the FindProductPrice action */
class ProductPriceResult
{
    /**
     * @param  array  $raw  The untouched decoded JSON response
     */
    public function __construct(
        public readonly ?string $requestKey = null,
        public readonly ?CalculatedPosition $position = null,
        public readonly array $raw = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            requestKey: $data['RequestKey'] ?? null,
            position: isset($data['Position']) ? CalculatedPosition::fromArray($data['Position']) : null,
            raw: $data,
        );
    }
}
