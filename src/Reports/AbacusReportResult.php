<?php

namespace Contoweb\AbacusApi\Reports;

use Contoweb\AbacusApi\Reports\Contracts\Report;
use Contoweb\AbacusApi\Reports\Exceptions\ReportExecutionException;
use Illuminate\Support\Collection;

class AbacusReportResult
{
    private Report $report;

    private string $result;

    public function __construct(Report $report, string $result)
    {
        $this->result = $result;
        $this->report = $report;
    }

    public static function make(Report $report, string $result): self
    {
        return new self($report, $result);
    }

    /**
     * Returns the raw report result.
     */
    public function raw(): ?string
    {
        return $this->result;
    }

    /**
     * Maps the report result to a collection of models defined in the report mapping.
     *
     * @see Report::mapping()
     *
     * @throws ReportExecutionException
     */
    public function toCollection(): Collection
    {
        return collect($this->parseAndMapJson());
    }

    /**
     * Decodes the raw report result and returns it as an associative array.
     *
     * @throws ReportExecutionException
     */
    public function toArray(): array
    {
        $value = json_decode($this->result, true);

        if (is_null($value)) {
            throw new ReportExecutionException('Failed to parse the report result');
        }

        return $value;
    }

    /**
     * Parse JSON and map the records.
     *
     * @throws ReportExecutionException
     */
    private function parseAndMapJson(): array
    {
        $jsonData = $this->toArray();

        /* Check if data is empty */
        if (empty($jsonData)) {
            return [];
        }

        $models = [];

        foreach ($jsonData as $record) {
            if (! is_array($record)) {
                throw new ReportExecutionException('Report record is not a valid array');
            }

            $models[] = $this->report->mapping($record);
        }

        return $models;
    }
}
