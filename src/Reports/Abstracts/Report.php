<?php

namespace Contoweb\AbacusApi\Reports\Abstracts;

use Contoweb\AbacusApi\Reports\Contracts\Report as ReportContract;

abstract class Report implements ReportContract
{
    /**
     * The output type for the report result.
     */
    protected string $outputType = 'json_compact';

    /**
     * Parameters for the report request.
     */
    protected array|string $parameters = [];

    public function __construct(array|string $parameters = [], ?string $outputType = null)
    {
        $this->setParameter($parameters);

        if (! is_null($outputType)) {
            $this->outputType = $outputType;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function outputType(): string
    {
        return $this->outputType;
    }

    /**
     * {@inheritDoc}
     */
    public function parameter(): array|string
    {
        return $this->parameters;
    }

    /**
     * {@inheritDoc}
     */
    public function setParameter(array|string $parameters): static
    {
        $this->parameters = $parameters;

        return $this;
    }
}
