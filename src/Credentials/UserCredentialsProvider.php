<?php

namespace Contoweb\AbacusApi\Credentials;

use Contoweb\AbacusApi\DataTransferObjects\AbacusApiCredentialsDto;
use Exception;
use Illuminate\Contracts\Auth\Guard;

class UserCredentialsProvider implements AbacusCredentialsProvider
{
    public function __construct(
        private readonly Guard $auth
    ) {}

    /**
     * @throws Exception
     */
    public function getCredentials(): AbacusApiCredentialsDto
    {
        $user = $this->auth->user();

        if (! $user instanceof ProvidesApiCredentials) {
            throw new Exception('User must implement ProvidesApiCredentials');
        }

        return $user->abacusCredentials();
    }
}
