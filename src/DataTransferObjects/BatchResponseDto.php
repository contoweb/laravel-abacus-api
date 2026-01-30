<?php

namespace Contoweb\AbacusApi\DataTransferObjects;

use Illuminate\Support\Collection;

class BatchResponseDto
{
    public function __construct(
        public readonly bool $success,
        public readonly int $status,
        public readonly array $headers,
        public readonly mixed $body,
        public readonly string $modelClass,
        public readonly ?string $error = null,
    ) {}

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
     * Get OData value array
     */
    public function getValue(): array
    {
        return $this->body['value'] ?? [];
    }

    /**
     * Check if response is successful
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get HTTP error status text
     */
    public function getError(): ?string
    {
        return $this->error ?? null;
    }

    /**
     * Get detailed API error message from response body
     */
    public function getErrorMessage(): ?string
    {
        return $this->body['error']['message'] ?? null;
    }

    /**
     * Get OData value array as model instances
     */
    public function getModels(): Collection
    {
        return collect($this->getValue())
            ->map(fn ($item) => new $this->modelClass($item));
    }
}
