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
     * The report name.
     *
     * @return string Report identifier
     */
    public function name(): string;

    /**
     * Map the JSON record.
     *
     * @param  array  $record  Associative array representing a single record
     */
    public function mapping(array $record);
}
