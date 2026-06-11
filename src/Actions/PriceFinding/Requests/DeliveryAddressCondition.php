<?php

namespace Contoweb\AbacusApi\Actions\PriceFinding\Requests;

class DeliveryAddressCondition
{
    public function __construct(
        public readonly ?int $deliveryAddressNumber = null,
        public readonly ?int $conditionNumber = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'DeliveryAddressNumber' => $this->deliveryAddressNumber,
            'ConditionNumber' => $this->conditionNumber,
        ], fn ($value) => $value !== null);
    }
}
