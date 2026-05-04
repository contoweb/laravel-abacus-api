<?php

namespace Contoweb\AbacusApi\Reports\Contracts;

interface Report
{
    /**
     * Get the output type for the report result.
     */
    public function outputType(): string;

    /**
     * Set the report parameters.
     */
    public function setParameters(array|string $parameters): static;

    /**
     * Get the report parameters.
     */
    public function parameters(): array|string;

    /**
     * Get the report name (including path encoding)
     * Example: "mandate%2Freport.avx"
     *
     * @return string Report identifier
     */
    public function name(): string;

    /**
     * Map JSON record to report model
     *
     * @param  array  $record  Associative array representing a single record
     * @return ReportModel Model instance with mapped data
     */
    public function mapping(array $record): ReportModel;
}
