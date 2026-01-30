<?php

namespace Contoweb\AbacusApi\Reports\Examples;

use Contoweb\AbacusApi\Reports\Contracts\Report;
use Contoweb\AbacusApi\Reports\Contracts\ReportModel;
use Contoweb\AbacusApi\Reports\Contracts\RequiresValidationRules;
use Contoweb\AbacusApi\Reports\Examples\Models\Department;

class DepartmentsReport implements Report, RequiresValidationRules
{
    /**
     * Get validation rules for report parameters
     */
    public static function validationRules(): array
    {
        return [
            'organization_number' => 'required|integer',
        ];
    }

    /**
     * Get the report name (with URL encoding)
     * More details: https://apihub.abacus.ch/apis/notodata/entity/aba-report.api
     */
    public function name(): string
    {
        return config('abacus-api.rest_api.mandate').'%2F'.'contacts_organisations.avx';
    }

    /**
     * Map JSON record to report model
     */
    public function mapping(array $record): ReportModel
    {
        return new Department(
            $record['NR'] ?? null,
            $record['SUBJEKT_NR'] ?? null,
            $record['NAME'] ?? null,
        );
    }
}
