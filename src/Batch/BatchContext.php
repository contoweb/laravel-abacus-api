<?php

namespace Contoweb\AbacusApi\Batch;

/**
 * Manages the active batch context for automatic query capture.
 *
 * This singleton-style class tracks the currently active batch request
 * to enable automatic batching of queries executed within capture closures.
 *
 * @example
 * ```php
 * $batch = Abacus::newBatch();
 * BatchContext::set($batch);
 *
 * // Now queries will be captured instead of executed
 * Customer::find(123); // Adds to batch instead of executing
 *
 * BatchContext::clear();
 * ```
 */
class BatchContext
{
    /**
     * The currently active batch request.
     */
    private static ?PendingBatchRequest $activeBatch = null;

    /**
     * Set the active batch context.
     */
    public static function set(PendingBatchRequest $batch): void
    {
        self::$activeBatch = $batch;
    }

    /**
     * Get the current active batch context.
     */
    public static function get(): ?PendingBatchRequest
    {
        return self::$activeBatch;
    }

    /**
     * Clear the active batch context.
     */
    public static function clear(): void
    {
        self::$activeBatch = null;
    }

    /**
     * Check if a batch context is currently active.
     */
    public static function has(): bool
    {
        return self::$activeBatch !== null;
    }

    /**
     * Alias for has() - check if we're in batch mode.
     */
    public static function inBatchMode(): bool
    {
        return self::has();
    }
}
