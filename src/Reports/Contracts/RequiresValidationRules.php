<?php

namespace Contoweb\AbacusApi\Reports\Contracts;

interface RequiresValidationRules
{
    /**
     * Get validation rules for report parameters
     *
     * @return array Laravel validation rules
     */
    public static function validationRules(): array;
}
