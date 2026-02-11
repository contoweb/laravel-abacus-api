<?php

namespace Contoweb\AbacusApi\Credentials;

use Contoweb\AbacusApi\DataTransferObjects\AbacusApiCredentialsDto;

interface ProvidesApiCredentials
{
    public function abacusCredentials(): AbacusApiCredentialsDto;
}
