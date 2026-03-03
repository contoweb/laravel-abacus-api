<?php

/**
 * Example Stock Model
 *
 * This is an example implementation showing how to create an Abacus model
 * for the Stocks endpoint with composite primary keys.
 *
 * To use in your application:
 * 1. Copy this file to app/Models/Abacus/Stock.php (or your preferred location)
 * 2. Update the namespace to match your application structure
 * 3. Customize based on your needs
 */

// namespace App\Models\Abacus;

use Contoweb\AbacusApi\Models\AbacusModel;

class Stock extends AbacusModel
{
    protected static string $resource = 'Stocks';

    /**
     * Composite primary key for Stock entities.
     *
     * The Abacus Stocks endpoint uses a composite key made up of:
     * - ProductId
     * - VariantId
     * - StockLocation
     * - StockArea
     */
    protected static string|array $primaryKey = ['ProductId', 'VariantId', 'StockLocation', 'StockArea'];
}
