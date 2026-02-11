<?php

namespace Contoweb\AbacusApi\Credentials;

use Contoweb\AbacusApi\DataTransferObjects\AbacusApiCredentialsDto;
use Illuminate\Contracts\Config\Repository;

class ConfigCredentialsProvider implements AbacusCredentialsProvider
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function getCredentials(): AbacusApiCredentialsDto
    {
        return new AbacusApiCredentialsDto(
            $this->config->get('abacus-api.rest_api.url'),
            $this->config->get('abacus-api.rest_api.mandate'),
            $this->config->get('abacus-api.rest_api.client_id'),
            $this->config->get('abacus-api.rest_api.client_secret'),
            $this->config->get('abacus-api.rest_api.version'),
        );
    }
}
