<?php

namespace Contoweb\AbacusApi\Events;

class AbacusRequestSend
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $body = [],
    ) {}
}
