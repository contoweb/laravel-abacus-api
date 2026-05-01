<?php

namespace Contoweb\AbacusApi\Reports\Contracts;

abstract class Report
{
    /**
     * The output type for the report result.
     */
    protected string $outputType = 'json_compact';

    /**
     * Parameters for the report request.
     */
    protected array|string $parameters = [];

    public function __construct(array|string $parameters = [])
    {
        $this->parameters = $parameters;
    }

    /**
     * Get the output type for the report result.
     */
    public function outputType(): string
    {
        return $this->outputType;
    }

    /**
     * Set the report parameters.
     */
    public function setParameter(array|string $parameters): static
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * Get the report parameters.
     */
    public function parameter(): array|string
    {
        return $this->parameters;
    }

    /**
     * Get the report name (including path encoding)
     * Example: "mandate%2Freport.avx"
     *
     * @return string Report identifier
     */
    abstract public function name(): string;

    /**
     * Map JSON record to report model
     *
     * @param  array  $record  Associative array representing a single record
     * @return ReportModel Model instance with mapped data
     */
    abstract public function mapping(array $record): ReportModel;
}
