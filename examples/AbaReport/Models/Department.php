<?php

namespace Models;

use Contoweb\AbacusApi\Reports\Contracts\ReportModel;

class Department implements ReportModel
{
    public function __construct(
        public readonly ?string $contactNumber,
        public readonly ?string $subjectNumber,
        public readonly ?string $name,
    ) {}
}
