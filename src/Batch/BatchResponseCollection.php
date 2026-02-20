<?php

namespace Contoweb\AbacusApi\Batch;

use Contoweb\AbacusApi\DataTransferObjects\BatchResponseDto;
use Contoweb\AbacusApi\Models\AbacusModel;
use Illuminate\Support\Collection;

/**
 * Extended Laravel Collection with batch-specific helper methods.
 *
 * This collection wraps BatchResponseDto objects and provides
 * convenient methods for filtering, error checking, and extracting models.
 *
 * @template TKey of array-key
 * @template TValue of BatchResponseDto
 *
 * @extends Collection<TKey, TValue>
 *
 * @example
 * ```php
 * $results = Abacus::batch(fn() => [Customer::find(123)])->send();
 *
 * if ($results->allSuccessful()) {
 *     $models = $results->models();
 * } else {
 *     $errors = $results->failed()->errors();
 * }
 * ```
 */
class BatchResponseCollection extends Collection
{
    /**
     * Filter only successful responses (2xx status codes).
     */
    public function successful(): static
    {
        /** @var static */
        return $this->filter(fn (BatchResponseDto $response) => $response->isSuccess());
    }

    /**
     * Filter only failed responses (non-2xx status codes).
     */
    public function failed(): static
    {
        /** @var static */
        return $this->filter(fn (BatchResponseDto $response) => ! $response->isSuccess());
    }

    /**
     * Check if all responses were successful.
     */
    public function allSuccessful(): bool
    {
        return $this->every(fn (BatchResponseDto $response) => $response->isSuccess());
    }

    /**
     * Check if any responses failed.
     */
    public function hasFailures(): bool
    {
        return ! $this->allSuccessful();
    }

    /**
     * Get all models from successful responses.
     *
     * @return Collection<int, AbacusModel|Collection<int, AbacusModel>>
     */
    public function models(): Collection
    {
        return $this->successful()
            ->map(fn (BatchResponseDto $response) => $response->getModels());
    }

    /**
     * Get error information from failed responses.
     *
     * @return Collection<int, array{status: int, error: ?string, message: ?string}>
     */
    public function errors(): Collection
    {
        return $this->failed()
            ->map(fn (BatchResponseDto $response) => [
                'status' => $response->status,
                'error' => $response->getError(),
                'message' => $response->getErrorMessage(),
            ]);
    }
}
