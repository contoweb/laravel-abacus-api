<?php

namespace Contoweb\AbacusApi\DataTransferObjects;

class BatchResponseDto
{
    public function __construct(
        public readonly bool $success,
        public readonly int $status,
        public readonly array $headers,
        public readonly mixed $body,
        public readonly ?string $error = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            success: $data['success'],
            status: $data['status'],
            headers: $data['headers'],
            body: $data['body'],
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
}
