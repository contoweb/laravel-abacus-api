<?php

namespace Contoweb\AbacusApi\Credentials;

use Contoweb\AbacusApi\DataTransferObjects\AbacusApiCredentialsDto;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;

class ConfigCredentialsProvider implements AbacusCredentialsProvider
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function getCredentials(): AbacusApiCredentialsDto
    {
        $values = [
            'base_url' => $this->config->get('abacus-api.rest_api.url'),
            'mandate' => $this->config->get('abacus-api.rest_api.mandate'),
            'client_id' => $this->config->get('abacus-api.rest_api.client_id'),
            'client_secret' => $this->config->get('abacus-api.rest_api.client_secret'),
            'version' => $this->config->get('abacus-api.rest_api.version'),
        ];

        foreach ($values as $key => $value) {
            if ($value === null || trim($value) === '') {
                throw new InvalidArgumentException("Config value $key is missing or empty.");
            }
        }

        return new AbacusApiCredentialsDto(
            $values['base_url'],
            $values['mandate'],
            $values['client_id'],
            $values['client_secret'],
            $values['version'],
        );
    }
}
