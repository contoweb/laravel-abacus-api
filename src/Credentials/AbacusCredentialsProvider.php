<?php

namespace Contoweb\AbacusApi\Credentials;

use Contoweb\AbacusApi\DataTransferObjects\AbacusApiCredentialsDto;

interface AbacusCredentialsProvider
{
    public function getCredentials(): AbacusApiCredentialsDto;
}
