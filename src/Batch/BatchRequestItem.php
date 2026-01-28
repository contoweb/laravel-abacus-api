<?php

namespace Contoweb\AbacusApi\Batch;

class BatchRequestItem
{
    public readonly string $modelClass;

    public readonly string $method;

    public readonly string $path;

    public readonly ?array $body;

    /**
     * @param string $modelClass
     * @param string $method
     * @param string $path
     * @param array|null $body
     */
    public function __construct(string $modelClass, string $method, string $path, ?array $body)
    {
        $this->modelClass = $modelClass;
        $this->method = $method;
        $this->path = $path;
        $this->body = $body;
    }
}
