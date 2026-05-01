<?php

namespace Contoweb\AbacusApi\Reports;

use Contoweb\AbacusApi\Reports\Contracts\Report;
use Contoweb\AbacusApi\Reports\Contracts\ReportModel;
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
    public function __construct(
        protected AbacusReportsClient $client
    ) {}

    /**
     * Execute report and return collection of models
     *
     * @param  Report  $report  Report instance
     * @return Collection<int, ReportModel> Collection of report models
     *
     * @throws ConnectionException
     * @throws ReportExecutionException
     * @throws ReportValidationException
     * @throws RequestException
     */
    public function collection(Report $report): Collection
    {
        /* Execute report and get models */
        $models = $this->executeReport($report);

        return collect($models);
    }

    /**
     * Execute report and map to models
     *
     *
     * @throws ReportExecutionException
     * @throws ReportValidationException
     * @throws ConnectionException
     * @throws RequestException
     */
    protected function executeReport(Report $report): array
    {
        /* Validate parameters if required */
        if ($report instanceof RequiresValidationRules) {
            $this->validateParameters($report->parameter(), $report::validationRules());
        }

        /* Submit report */
        $jobResponse = $this->client->submitReport(
            $report->name(),
            $report->parameter(),
            $report->outputType()
        );

        /* Check for immediate errors */
        if (isset($jobResponse['status']) && ($jobResponse['status'] === 403 || $jobResponse['status'] === 500)) {
            throw new ReportExecutionException(
                'AbaReport failed with message: '.($jobResponse['title'] ?? 'Unknown error')
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
                'AbaReport failed since it was not successful. Message: '.($finalStatus['message'] ?? 'unknown')
            );
        }

        /* Get result JSON */
        $jsonData = $this->client->getJobOutput($jobId);

        /* Close the report session */
        defer(fn () => $this->client->deleteJob($jobId));

        /* Parse and map to models */

        return $this->parseAndMapJson($jsonData, $report);
    }

    /**
     * Parse JSON and map each record to a model
     *
     * @throws ReportExecutionException
     */
    protected function parseAndMapJson(array $jsonData, Report $report): array
    {
        /* Check if data is empty */
        if (empty($jsonData)) {
            return [];
        }

        $models = [];

        foreach ($jsonData as $record) {
            if (! is_array($record)) {
                throw new ReportExecutionException('Report record is not a valid array');
            }

            $models[] = $report->mapping($record);
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
