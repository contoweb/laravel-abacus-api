<?php

namespace Contoweb\AbacusApi\Models\v1;

use Contoweb\AbacusApi\Models\AbacusModel;

class Product extends AbacusModel
{
    protected static string $resource = 'Products';

    /*
     * Determines whether batch or serial number handling is required.
     *
     * It's required when:
     * - The batch/serial feature is enabled ("Active"), and
     * - Either:
     *      - The mode is "Batch", or
     *      - The mode is "SerialNumber" and serial numbers must be kept from incoming goods ("FromRecipt")
     */
    public function requiresBatchOrSerialNumber(): bool
    {
        return $this->requiresBatch() || $this->requiresSerialNumber();
    }

    /*
     * Determines whether a batch requires a serial number.
     */
    public function requiresBatch(): bool
    {
        $status = $this->StockBatchDefinition['Status'] ?? null;
        $type = $this->StockBatchDefinition['Type'] ?? null;

        return $type === 'Batch' && $status === 'Active';
    }

    /*
     * Determines whether a batch requires a serial number.
     */
    public function requiresSerialNumber(): bool
    {
        $status = $this->StockBatchDefinition['Status'] ?? null;
        $type = $this->StockBatchDefinition['Type'] ?? null;

        return $type === 'SerialNumber' && $status === 'Active';
    }

    /*
     * Determines whether a serial number should be assigned from stock movement inbound (Wareneingang), for e.g. purchase order.
     */
    public function keepSerialNumberFromReceipt(): bool
    {
        $definitionSwitch = $this->StockBatchDefinition['DefintionSwitch'] ?? [];

        return ($definitionSwitch['KeepSerialNumbers'] ?? null) === 'FromRecipt';
    }
}
