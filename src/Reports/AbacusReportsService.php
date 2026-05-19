<?php

namespace Contoweb\AbacusApi\Reports;

use BadMethodCallException;
use Contoweb\AbacusApi\Reports\Contracts\Report;
use Contoweb\AbacusApi\Reports\Contracts\RequiresValidationRules;
use Contoweb\AbacusApi\Reports\Exceptions\ReportExecutionException;
use Contoweb\AbacusApi\Reports\Exceptions\ReportValidationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

use function Illuminate\Support\defer;

class AbacusReportsService
{
    protected ?Report $report = null;

    protected ?string $result = null;

    public function __construct(
        protected AbacusReportsClient $client
    ) {}

    /**
     * Executes the given report.
     *
     * @throws ReportValidationException
     * @throws ConnectionException
     * @throws RequestException
     * @throws ReportExecutionException
     */
    public function run(Report $report): static
    {
        $this->report = $report;
        $this->result = null;

        return $this->executeReport();
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
        if (is_null($this->report)) {
            throw new BadMethodCallException('Trying to access the report result before calling run().');
        }

        $value = json_decode($this->result, true);

        if (is_null($value)) {
            throw new ReportExecutionException('Failed to parse the report result');
        }

        return $value;
    }

    /**
     * Submits the report, polls until completion and stores the result.
     *
     * @throws ReportExecutionException
     * @throws ReportValidationException
     * @throws ConnectionException
     * @throws RequestException
     */
    protected function executeReport(): static
    {
        /* Validate parameters if required */
        if ($this->report instanceof RequiresValidationRules) {
            $this->validateParameters($this->report->parameters(), $this->report::validationRules());
        }

        /* Submit report */
        $jobResponse = $this->client->submitReport(
            $this->report->name(),
            $this->report->parameters(),
            $this->report->outputType()
        );

        /* Check for immediate errors */
        if (isset($jobResponse['status']) && ($jobResponse['status'] === 403 || $jobResponse['status'] === 500)) {
            throw new ReportExecutionException(
                'AbaReport response indicates unsuccessful request with message: '.($jobResponse['title'] ?? 'Unknown error')
            );
        }

        if (! isset($jobResponse['id'])) {
            throw new ReportExecutionException('Report submission did not return a job ID');
        }

        $jobId = $jobResponse['id'];

        /* Poll until complete */
        $pollInterval = config('abacus-api.reports.poll_interval', 200000);
        $maxAttempts = config('abacus-api.reports.max_poll_attempts', 150);
        $finalStatus = $this->client->pollJobUntilComplete($jobId, $pollInterval, $maxAttempts);

        /* Check final status */
        if (($finalStatus['state'] ?? null) !== 'FinishedSuccess') {
            throw new ReportExecutionException(
                'AbaReport response indicates unsuccessful request with message: '.($finalStatus['message'] ?? 'Unknown error')
            );
        }

        /* Get the result */
        $this->result = $this->client->getJobOutput($jobId);

        /* Close the report session */
        defer(fn () => $this->client->deleteJob($jobId));

        return $this;
    }

    /**
     * Parse JSON and map each record to a model
     *
     * @throws ReportExecutionException
     */
    protected function parseAndMapJson(): array
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

    /**
     * Validate report parameters
     *
     * @throws ReportValidationException
     */
    protected function validateParameters(array $data, array $validationRules): void
    {
        $validator = Validator::make($data, $validationRules);

        if ($validator->fails()) {
            throw new ReportValidationException($validator->errors()->first());
        }
    }
}
