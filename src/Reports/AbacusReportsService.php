<?php

namespace Contoweb\AbacusApi\Reports;

use Contoweb\AbacusApi\Reports\Contracts\Report;
use Contoweb\AbacusApi\Reports\Contracts\RequiresValidationRules;
use Contoweb\AbacusApi\Reports\Exceptions\ReportExecutionException;
use Contoweb\AbacusApi\Reports\Exceptions\ReportValidationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Validator;

use function Illuminate\Support\defer;

class AbacusReportsService
{
    public function __construct(
        private readonly AbacusReportsClient $client,
        private readonly int $pollInterval,
        private readonly int $maxPollAttempts
    ) {}

    /**
     * Executes the given report.
     *
     * @throws ReportValidationException
     * @throws ConnectionException
     * @throws RequestException
     * @throws ReportExecutionException
     */
    public function run(Report $report): AbacusReportResult
    {
        $jobId = $this->startReport($report);

        $this->pollJobUntilCompleted($jobId, $this->pollInterval, $this->maxPollAttempts);

        $result = $this->result($report, $jobId);

        /* Run the delete / cleanup action deferred. */
        defer(fn () => $this->client->deleteJob($jobId));

        return $result;
    }

    /**
     * Starts the report and returns the responded job id.
     *
     * @throws ReportExecutionException
     * @throws ReportValidationException
     * @throws ConnectionException
     * @throws RequestException
     */
    public function startReport(Report $report): string
    {
        /* Validate input parameters if required */
        if ($report instanceof RequiresValidationRules) {
            $this->validateParameters(
                $report->parameters(),
                $report::validationRules()
            );
        }

        $jobResponse = $this->client->startReport(
            $report->name(),
            $report->parameters(),
            $report->outputType()
        );

        if (! isset($jobResponse['id'])) {
            throw new ReportExecutionException('Report start response did not contain a job ID');
        }

        return $jobResponse['id'];
    }

    /**
     * Check if the job has finished (successfully).
     *
     *
     * @throws ConnectionException
     * @throws ReportExecutionException
     * @throws RequestException
     */
    public function checkJobFinished($jobId): bool
    {
        $status = $this->client->getJobStatus($jobId);

        /* Check if job is complete */
        if (isset($status['state']) && $status['state'] !== 'Running') {
            if ($status['state'] !== 'FinishedSuccess') {
                throw new ReportExecutionException(
                    'AbaReport job status finished in an unsuccessful state: '.
                    ($status['message'] ?? 'Unknown error')
                );
            }

            return true;
        }

        return false;
    }

    /**
     * Poll the job until it is indicated as finished.
     *
     * @throws ConnectionException
     * @throws ReportExecutionException
     * @throws RequestException
     */
    public function pollJobUntilCompleted(string $jobId, int $pollInterval, int $maxAttempts): AbacusReportsService
    {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            if ($this->checkJobFinished($jobId) === true) {
                return $this;
            }

            usleep($pollInterval);
            $attempts++;
        }

        throw new ReportExecutionException(
            'Job polling of id '.
            $jobId.' timed out after '.$maxAttempts.' attempts.'
        );
    }

    /**
     * Get the job / report output.
     *
     * @throws RequestException
     * @throws ReportExecutionException
     * @throws ConnectionException
     */
    public function result(Report $report, string $jobId): AbacusReportResult
    {
        $result = $this->client->getJobOutput($jobId);

        return AbacusReportResult::make($report, $result);
    }

    /**
     * Validate report parameters
     *
     * @throws ReportValidationException
     */
    private function validateParameters(array $data, array $validationRules): void
    {
        $validator = Validator::make($data, $validationRules);

        if ($validator->fails()) {
            throw new ReportValidationException($validator->errors()->first());
        }
    }
}
