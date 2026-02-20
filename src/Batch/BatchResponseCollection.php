<?php

namespace Contoweb\AbacusApi\Batch;

use Contoweb\AbacusApi\DataTransferObjects\BatchResponseDto;
use Contoweb\AbacusApi\Exceptions\BatchRequestFailedException;
use Contoweb\AbacusApi\Models\AbacusModel;
use Illuminate\Support\Collection;

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
     * Get all models from responses, returns null for failed responses.
     *
     * @return Collection<int, AbacusModel|Collection<int, AbacusModel>|null>
     */
    public function models(): Collection
    {
        return $this->map(fn (BatchResponseDto $response) => $response->isSuccess() ? $response->models() : null);
    }

    /**
     * Get all models or throw an exception if any request failed.
     *
     * @return Collection<int, AbacusModel|Collection<int, AbacusModel>>
     *
     * @throws BatchRequestFailedException
     */
    public function modelsOrFail(): Collection
    {
        $this->each(function (BatchResponseDto $response) {
            if (! $response->isSuccess()) {
                throw new BatchRequestFailedException($response->errorMessage() ?? '', $response->errorCode() ?? 0);
            }
        });

        return $this->map(fn (BatchResponseDto $response) => $response->models());
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
                'error' => $response->error(),
                'message' => $response->errorMessage(),
            ]);
    }
}
