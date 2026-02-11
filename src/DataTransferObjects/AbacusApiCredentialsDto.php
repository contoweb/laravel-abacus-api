<?php

namespace Contoweb\AbacusApi\DataTransferObjects;

class AbacusApiCredentialsDto
{
    public function __construct(
        public string $baseUrl,
        public string $mandate,
        public string $clientId,
        public string $clientSecret,
        public string $apiVersion,
    ) {}
}
