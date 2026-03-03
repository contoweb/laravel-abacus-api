<?php

/**
 * Example Product Model
 *
 * This is an example implementation showing how to create an Abacus model
 * for the Products endpoint with nested components.
 *
 * To use in your application:
 * 1. Copy this file to app/Models/Abacus/Product.php (or your preferred location)
 * 2. Update the namespace to match your application structure
 * 3. Copy the component classes from examples/Components/ as needed
 * 4. Customize casts and methods based on your needs
 */

// namespace App\Models\Abacus;

use Contoweb\AbacusApi\Models\AbacusModel;

// use App\Models\Abacus\Components\Measurements;
// use App\Models\Abacus\Components\Weights;

class Product extends AbacusModel
{
    protected static string $resource = 'Products';

    /**
     * Cast nested OData components to strongly-typed objects.
     *
     * This allows accessing nested data like: $product->Measurements->Length
     * instead of: $product->Measurements['Length']
     */
    protected array $casts = [
        // 'Measurements' => Measurements::class,
        // 'Weights' => Weights::class,
    ];

    /**
     * Determines whether batch or serial number handling is required.
     */
    public function requiresBatchOrSerialNumber(): bool
    {
        return $this->requiresBatch() || $this->requiresSerialNumber();
    }

    /**
     * Check if product requires batch tracking.
     */
    public function requiresBatch(): bool
    {
        $status = $this->StockBatchDefinition['Status'] ?? null;
        $type = $this->StockBatchDefinition['Type'] ?? null;

        return $type === 'Batch' && $status === 'Active';
    }

    /**
     * Check if product requires serial number tracking.
     */
    public function requiresSerialNumber(): bool
    {
        $status = $this->StockBatchDefinition['Status'] ?? null;
        $type = $this->StockBatchDefinition['Type'] ?? null;

        return $type === 'SerialNumber' && $status === 'Active';
    }

    /**
     * Check if serial numbers should be kept from receipt.
     */
    public function keepSerialNumberFromReceipt(): bool
    {
        $definitionSwitch = $this->StockBatchDefinition['DefintionSwitch'] ?? [];

        return ($definitionSwitch['KeepSerialNumbers'] ?? null) === 'FromRecipt';
    }
}
