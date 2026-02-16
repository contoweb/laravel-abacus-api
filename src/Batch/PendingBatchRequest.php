<?php

namespace Contoweb\AbacusApi\Batch;

use Contoweb\AbacusApi\AbacusODataClient;
use Contoweb\AbacusApi\DataTransferObjects\BatchResponseDto;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

/**
 * Fluent builder for accumulating and executing batch requests.
 *
 * This class provides an elegant API for building batch requests with
 * automatic query capture through closures, enabling Laravel-like syntax
 * for batch operations.
 *
 * @example Basic capture pattern (recommended)
 * ```php
 * [$customer, $products] = Abacus::batch(function() {
 *     return [
 *         Customer::find(123),
 *         Product::where('Price', 'gt', 100)->get(),
 *     ];
 * })->send();
 * ```
 * @example Progressive building
 * ```php
 * $batch = Abacus::newBatch();
 * $batch->capture(fn() => Customer::find(123));
 * $batch->capture(fn() => Product::find(456));
 * $results = $batch->send();
 * ```
 * @example Access by index
 * ```php
 * $results = Abacus::batch(function() {
 *     return [Customer::find(123), Product::find(456)];
 * })->send();
 *
 * $customer = $results[0]->getModels()->first();
 * $product = $results[1]->getModels()->first();
 * ```
 */
class PendingBatchRequest
{
    /**
     * The batch request items, keyed by string or integer.
     *
     * @var array<string|int, BatchRequestItem>
     */
    private array $items = [];

    /**
     * The next auto-increment key for unnamed items.
     */
    private int $nextKey = 0;

    /**
     * Optional name for this batch (for debugging/logging).
     */
    private ?string $name = null;

    /**
     * Create a new pending batch request.
     */
    public function __construct(
        private readonly AbacusODataClient $client,
        ?string $name = null
    ) {
        $this->name = $name;
    }

    /**
     * Add a single batch request item.
     *
     * @param  BatchRequestItem  $item  The batch request item to add
     * @param  string|int|null  $key  Optional key for result mapping
     * @return $this
     */
    public function add(BatchRequestItem $item, string|int|null $key = null): self
    {
        $key = $key ?? $this->nextKey++;
        $this->items[$key] = $item;

        return $this;
    }

    /**
     * Add multiple batch request items at once.
     *
     * @param  array<string|int, BatchRequestItem>  $items  Items to add (optionally keyed)
     * @return $this
     */
    public function addMany(array $items): self
    {
        foreach ($items as $key => $item) {
            // If the key is a string, use it; otherwise let add() auto-assign
            if (is_string($key)) {
                $this->add($item, $key);
            } else {
                $this->add($item);
            }
        }

        return $this;
    }

    /**
     * Execute a closure in batch context and capture queries.
     *
     * Any queries executed within the closure will be automatically
     * added to this batch instead of executing immediately.
     *
     * @param  callable  $callback  Closure that executes queries
     * @return $this
     *
     * @throws RuntimeException If nested batch capture is attempted
     *
     * @example
     * ```php
     * $batch->capture(function() {
     *     Customer::find(123);
     *     Product::where('Price', 'gt', 100)->get();
     * });
     * ```
     */
    public function capture(callable $callback): self
    {
        // Prevent nested batch contexts
        if (BatchContext::has()) {
            throw new RuntimeException(
                'Nested batch captures are not supported. Complete the current batch before starting a new one.'
            );
        }

        // Set this batch as the active context
        BatchContext::set($this);

        try {
            // Execute the callback - queries will be captured
            $result = $callback();

            // If callback returns an array of items, they're already added
            // (via automatic capture in query builders)
            // The return value can be used for ordering in destructuring

        } finally {
            // Always clear context, even if callback throws
            BatchContext::clear();
        }

        return $this;
    }

    /**
     * Get the number of items in this batch.
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * Check if this batch is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * Get all items in this batch.
     *
     * @return array<string|int, BatchRequestItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    /**
     * Clear all items from this batch.
     *
     * @return $this
     */
    public function clear(): self
    {
        $this->items = [];
        $this->nextKey = 0;

        return $this;
    }

    /**
     * Get the batch name (if set).
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Execute the batch and return results.
     *
     * @return BatchResponseCollection<string|int, BatchResponseDto>
     *
     * @throws ConnectionException
     * @throws RequestException
     *
     * @example
     * ```php
     * $results = $batch->send();
     *
     * if ($results->allSuccessful()) {
     *     $models = $results->models();
     * }
     * ```
     */
    public function send(): BatchResponseCollection
    {
        // Handle empty batch
        if ($this->isEmpty()) {
            return new BatchResponseCollection([]);
        }

        // Create traditional BatchRequest with items
        $batchRequest = new BatchRequest($this->client, ...array_values($this->items));

        // Execute and get results
        $responses = $batchRequest->send();

        // Map results back to original keys
        $mappedResults = [];
        $keys = array_keys($this->items);

        foreach ($responses as $index => $response) {
            $originalKey = $keys[$index] ?? $index;
            $mappedResults[$originalKey] = $response;
        }

        return new BatchResponseCollection($mappedResults);
    }
}
