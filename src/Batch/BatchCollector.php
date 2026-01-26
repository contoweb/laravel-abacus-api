<?php

namespace Contoweb\AbacusApi\Batch;

/**
 * Manages the global batch context for collecting queries.
 *
 * When batch mode is active (via Abacus::batch(callback)), query builders
 * will add their queries here instead of executing them immediately.
 */
class BatchCollector
{
    protected static ?self $current = null;
    protected array $queries = [];

    /**
     * Check if batch mode is currently active
     */
    public static function isActive(): bool
    {
        return self::$current !== null;
    }

    /**
     * Get the current active collector
     */
    public static function getCurrent(): ?self
    {
        return self::$current;
    }

    /**
     * Set the current active collector (or null to deactivate)
     */
    public static function setCurrent(?self $collector): void
    {
        self::$current = $collector;
    }

    /**
     * Add a prepared query to the collection
     *
     * @param array $preparedQuery Array with 'method', 'path', 'body' keys from prepareForBatch()
     * @param string|null $modelClass The model class to wrap results in
     */
    public function addQuery(array $preparedQuery, ?string $modelClass = null): void
    {
        $this->queries[] = [
            'request' => $preparedQuery,
            'modelClass' => $modelClass,
        ];
    }

    /**
     * Get all collected queries
     *
     * @return array Array of ['request' => [...], 'modelClass' => string|null]
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Get the count of collected queries
     */
    public function count(): int
    {
        return count($this->queries);
    }
}
