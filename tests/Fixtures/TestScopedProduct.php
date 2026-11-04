<?php

namespace Contoweb\AbacusApi\Tests\Fixtures;

use Contoweb\AbacusApi\Models\AbacusModel;

/**
 * Test model with a navigation property cast to a scoped model
 */
class TestScopedProduct extends AbacusModel
{
    protected static string $resource = 'Products';

    protected array $casts = [
        'Id' => 'int',
        'Name' => 'string',
        'Batches' => TestScopedBatch::class,
    ];
}
