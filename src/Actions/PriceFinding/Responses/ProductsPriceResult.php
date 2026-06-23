<?php

namespace Contoweb\AbacusApi\Actions\PriceFinding\Responses;

/* Result of the FindProductsPriceOverview and FindProductsPriceShoppingCart actions */
class ProductsPriceResult
{
    /**
     * @param  CalculatedPosition[]  $positions
     * @param  DocumentDiscount[]  $documentDiscounts
     * @param  array  $raw  The untouched decoded JSON response
     */
    public function __construct(
        public readonly ?string $requestKey = null,
        public readonly array $positions = [],
        public readonly array $documentDiscounts = [],
        public readonly array $raw = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            requestKey: $data['RequestKey'] ?? null,
            positions: array_map(
                fn (array $position) => CalculatedPosition::fromArray($position),
                $data['Positions'] ?? []
            ),
            documentDiscounts: array_map(
                fn (array $discount) => DocumentDiscount::fromArray($discount),
                $data['DocumentDiscounts'] ?? []
            ),
            raw: $data,
        );
    }
}
