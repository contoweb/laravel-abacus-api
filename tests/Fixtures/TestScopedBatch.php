<?php

namespace Contoweb\AbacusApi\Tests\Fixtures;

use Contoweb\AbacusApi\AbacusODataQueryBuilder;
use Contoweb\AbacusApi\Attributes\Scope;
use Contoweb\AbacusApi\Models\AbacusModel;

/**
 * Test model with local scopes
 */
class TestScopedBatch extends AbacusModel
{
    protected static string $resource = 'Batches';

    protected array $casts = [
        'Assignments' => TestScopedAssignment::class,
    ];

    #[Scope]
    protected function whereBatchNumber(AbacusODataQueryBuilder $query, string $batchNumber): void
    {
        $query
            ->where('BatchNumber', 'ge', $batchNumber)
            ->where('BatchNumber', 'le', $batchNumber.'z');
    }

    #[Scope]
    protected function whereVariant(AbacusODataQueryBuilder $query, int $variantId): AbacusODataQueryBuilder
    {
        return $query->where('VariantId', 'eq', $variantId);
    }

    #[Scope]
    private function privateScope(AbacusODataQueryBuilder $query): void
    {
        $query->where('Private', 'eq', 1);
    }

    protected function scopeActive(AbacusODataQueryBuilder $query): void
    {
        $query->where('Active', 'eq', true);
    }

    protected function notAScope(AbacusODataQueryBuilder $query): void
    {
        $query->where('Nope', 'eq', 1);
    }
}
