<?php

namespace Contoweb\AbacusApi\DataTransferObjects;

use Contoweb\AbacusApi\Models\AbacusModel;
use Illuminate\Support\Collection;

/**
 * @template TModel of AbacusModel
 */
class BatchResponseDto
{
    /**
     * @param  class-string<TModel>  $modelClass
     */
    public function __construct(
        public readonly bool $success,
        public readonly int $status,
        public readonly array $headers,
        public readonly ?array $body,
        public readonly string $modelClass,
        public readonly ?string $error = null,
    ) {}

    /**
     * @param  class-string<TModel>  $modelClass
     * @return self<TModel>
     */
    public static function fromArray(array $data, string $modelClass): self
    {
        return new self(
            success: $data['success'],
            status: $data['status'],
            headers: $data['headers'],
            body: $data['body'],
            modelClass: $modelClass,
            error: $data['error'] ?? null,
        );
    }

    /**
     * Get OData value array.
     */
    public function value(): array
    {
        return $this->body['value'] ?? $this->body ?? [];
    }

    /**
     * Check if response is successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get HTTP error status text.
     */
    public function error(): ?string
    {
        return $this->error ?? null;
    }

    /**
     * Get detailed API error message from response body.
     */
    public function errorMessage(): ?string
    {
        return $this->body['error']['message'] ?? null;
    }

    /**
     * Get HTTP error status code.
     */
    public function errorCode(): ?int
    {
        return $this->body['error']['code'] ?? null;
    }

    /**
     * Returns a collection of model instances if the response contains multiple items,
     * otherwise returns a single model instance.
     *
     * @return Collection<int, AbacusModel>|AbacusModel
     */
    public function mapped(): Collection|AbacusModel
    {
        if (isset($this->body['value'])) {
            return collect($this->body['value'])
                ->map(fn ($item) => new $this->modelClass($item));
        }

        return new $this->modelClass($this->body);
    }
}
