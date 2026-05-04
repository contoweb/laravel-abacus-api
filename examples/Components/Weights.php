<?php

/**
 * Example Weights Component
 *
 * This is an example component class for the Abacus Weights schema
 * (ch.abacus.orde.Weights from the Products API).
 *
 * Components are used to represent nested OData complex types as objects,
 * allowing you to access properties like:
 *   $product->Weights->Net
 * instead of:
 *   $product->Weights['Net']
 *
 * To use in your application:
 * 1. Copy this file to app/Models/Abacus/Components/Weights.php
 * 2. Update the namespace to match your application structure
 * 3. Reference it in your model's $casts array:
 *    protected array $casts = ['Weights' => Weights::class];
 *
 * The @property annotations provide IDE autocomplete for the nested fields.
 * Update these based on the actual Abacus API schema for your endpoint.
 *
 * @property float|null $Net Net weight
 * @property float|null $Tare Tare weight
 * @property float|null $SpecificWeight Specific weight/density
 * @property int|null $UnitId Unit identifier for weights
 */

// namespace App\Models\Abacus\Components;

use Contoweb\AbacusApi\Models\AbacusComponent;

class Weights extends AbacusComponent
{
    // No additional code needed - the base AbacusComponent class
    // handles all property access via __get() and __set()
}
