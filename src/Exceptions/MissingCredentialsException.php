<?php

namespace Contoweb\AbacusApi\Exceptions;

use Exception;

class MissingCredentialsException extends Exception
{
    public function __construct($message = '', $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
