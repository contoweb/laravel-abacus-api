<?php

/**
 * Example Measurements Component
 *
 * This is an example component class for the Abacus Measurements schema
 * (ch.abacus.orde.Measurements from the Products API).
 *
 * Components are used to represent nested OData complex types as objects,
 * allowing you to access properties like:
 *   $product->Measurements->Length
 * instead of:
 *   $product->Measurements['Length']
 *
 * To use in your application:
 * 1. Copy this file to app/Models/Abacus/Components/Measurements.php
 * 2. Update the namespace to match your application structure
 * 3. Reference it in your model's $casts array:
 *    protected array $casts = ['Measurements' => Measurements::class];
 *
 * The @property annotations provide IDE autocomplete for the nested fields.
 * Update these based on the actual Abacus API schema for your endpoint.
 *
 * @property float|null $Length Product length
 * @property float|null $Width Product width
 * @property float|null $Height Product height
 * @property float|null $VolumeOrArea Calculated volume or area
 * @property float|null $Diameter Product diameter
 * @property int|null $UnitId Unit identifier for measurements
 */

// namespace App\Models\Abacus\Components;

use Contoweb\AbacusApi\Models\AbacusComponent;

class Measurements extends AbacusComponent
{
    // No additional code needed - the base AbacusComponent class
    // handles all property access via __get() and __set()
}
