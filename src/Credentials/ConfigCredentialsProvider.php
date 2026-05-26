<?php

namespace Contoweb\AbacusApi\Credentials;

use Contoweb\AbacusApi\DataTransferObjects\AbacusApiCredentialsDto;

class ConfigCredentialsProvider implements AbacusCredentialsProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $mandate,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $apiVersion,
    ) {}

    public function getCredentials(): AbacusApiCredentialsDto
    {
        return new AbacusApiCredentialsDto(
            $this->baseUrl,
            $this->mandate,
            $this->clientId,
            $this->clientSecret,
            $this->apiVersion,
        );
    }
}
