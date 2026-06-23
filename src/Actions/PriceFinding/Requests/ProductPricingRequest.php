<?php

namespace Contoweb\AbacusApi\Actions\PriceFinding\Requests;

use DateTimeInterface;

/* Request body for the FindProductPrice action */
class ProductPricingRequest
{
    public function __construct(
        public readonly ?int $customerNumber = null,
        public readonly ?string $currency = null,
        public readonly DateTimeInterface|string|null $calculationDate = null,
        public readonly ?RequestPosition $position = null,
        public readonly ?string $requestKey = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'RequestKey' => $this->requestKey,
            'Currency' => $this->currency,
            'CustomerNumber' => $this->customerNumber,
            'CalculationDate' => $this->formatCalculationDate(),
            'Position' => $this->position?->toArray(),
        ], fn ($value) => $value !== null);
    }

    protected function formatCalculationDate(): ?string
    {
        if ($this->calculationDate instanceof DateTimeInterface) {
            return $this->calculationDate->format('Y-m-d');
        }

        return $this->calculationDate;
    }
}
