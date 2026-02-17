<?php

namespace Contoweb\AbacusApi\Credentials;

use Contoweb\AbacusApi\DataTransferObjects\AbacusApiCredentialsDto;
use Contoweb\AbacusApi\Exceptions\MissingCredentialsException;
use Exception;
use Illuminate\Auth\AuthenticationException;
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

        if ($user === null) {
            throw new AuthenticationException('No authenticated user found');
        }

        if (! $user instanceof ProvidesApiCredentials) {
            throw new MissingCredentialsException('User must implement ProvidesApiCredentials');
        }

        return $user->abacusCredentials();
    }
}
