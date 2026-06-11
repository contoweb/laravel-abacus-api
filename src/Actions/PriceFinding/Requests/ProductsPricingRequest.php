<?php

namespace Contoweb\AbacusApi\Actions\PriceFinding\Requests;

use DateTimeInterface;

/* Request body for the FindProductsPriceOverview and FindProductsPriceShoppingCart actions */
class ProductsPricingRequest
{
    /**
     * @param  RequestPosition[]  $positions
     */
    public function __construct(
        public readonly ?int $customerNumber = null,
        public readonly ?string $currency = null,
        public readonly DateTimeInterface|string|null $calculationDate = null,
        public readonly array $positions = [],
        public readonly ?DeliveryAddressCondition $deliveryAddressCondition = null,
        public readonly ?bool $includeCalculationFee = null,
        public readonly ?bool $includeCalculationDocumentDiscount = null,
        public readonly int $fixPriceNumberArticle = -1,
        public readonly int $fixPriceNumberService = -1,
        public readonly ?string $requestKey = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'RequestKey' => $this->requestKey,
            'Currency' => $this->currency,
            'CustomerNumber' => $this->customerNumber,
            'DeliveryAddressCondition' => $this->deliveryAddressCondition?->toArray(),
            'CalculationDate' => $this->formatCalculationDate(),
            'IncludeCalculationFee' => $this->includeCalculationFee,
            'IncludeCalculationDocumentDiscount' => $this->includeCalculationDocumentDiscount,
            'FixPriceNumberArticle' => $this->fixPriceNumberArticle,
            'FixPriceNumberService' => $this->fixPriceNumberService,
            'Positions' => array_map(
                fn (RequestPosition $position) => $position->toArray(),
                array_values($this->positions)
            ),
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
