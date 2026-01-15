<?php

namespace Contoweb\AbacusApi\Reports;

use Contoweb\AbacusApi\BaseAbacusClient;
use Contoweb\AbacusApi\Reports\Exceptions\ReportExecutionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class AbacusReportsClient extends BaseAbacusClient
{
    /**
     * Submit a report for execution
     *
     * @param string $reportName Name of the report (e.g., "mandate%2Freport.avx")
     * @param array  $parameters Report parameters
     * @param string $outputType Output format (default: json)
     *
     * @return array Response containing job ID and state
     * @throws RequestException|ConnectionException
     */
    public function submitReport(string $reportName, array $parameters = [], string $outputType = 'json'): array
    {
        $path = $this->reportPath($reportName);

        $response = $this->post($path, [
            'outputType' => $outputType,
            'parameters' => $parameters,
        ]);

        return $response->json();
    }

    /**
     * Get job status
     *
     * @param string $jobId Job identifier
     *
     * @return array Job status information
     * @throws RequestException|ConnectionException
     */
    public function getJobStatus(string $jobId): array
    {
        $path = $this->jobPath($jobId);

        $response = $this->get($path);

        return $response->json();
    }

    /**
     * Get job output (final result)
     *
     * @param string $jobId Job identifier
     *
     * @return array Parsed JSON output
     * @throws RequestException|ConnectionException
     */
    public function getJobOutput(string $jobId): array
    {
        $path = $this->jobOutputPath($jobId);

        $response = Http::withToken($this->getAccessToken())
                        ->get($this->getUrl() . $path)
                        ->throw();

        return $response->json() ?? [];
    }

    /**
     * Delete/close a report job session
     *
     * @param string $jobId Job identifier
     *
     * @return void
     * @throws RequestException|ConnectionException
     */
    public function deleteJob(string $jobId): void
    {
        $path = $this->jobPath($jobId);

        $this->delete($path);
    }

    /**
     * Poll job until completion
     *
     * @param string $jobId        Job identifier
     * @param int    $pollInterval Microseconds between polls (default: 200000 = 0.2s)
     * @param int    $maxAttempts  Maximum number of poll attempts
     *
     * @return array Final job status
     * @throws ConnectionException
     * @throws ReportExecutionException
     * @throws RequestException
     */
    public function pollJobUntilComplete(string $jobId, int $pollInterval = 200000, int $maxAttempts = 150): array
    {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            $status = $this->getJobStatus($jobId);

            /* Check for error states */
            if (isset($status['status']) && ($status['status'] === 403 || $status['status'] === 500)) {
                throw new ReportExecutionException(
                    'AbaReport failed with message: ' . ($status['title'] ?? 'Unknown error')
                );
            }

            /* Check if job is complete */
            if (isset($status['state']) && $status['state'] !== 'Running') {
                return $status;
            }

            usleep($pollInterval);
            $attempts++;
        }

        throw new ReportExecutionException('Report job polling timed out after ' . $maxAttempts . ' attempts');
    }

    /**
     * Get the base path for reports API
     */
    protected function getReportBasePath(): string
    {
        return "/api/abareport/{$this->apiVersion}";
    }

    /**
     * Build report submission path
     */
    public function reportPath(string $reportName): string
    {
        return $this->getReportBasePath() . '/report/' . $reportName;
    }

    /**
     * Build job status path
     */
    public function jobPath(string $jobId): string
    {
        return $this->getReportBasePath() . '/jobs/' . $jobId;
    }

    /**
     * Build job output path
     */
    public function jobOutputPath(string $jobId): string
    {
        return $this->getReportBasePath() . '/jobs/' . $jobId . '/output';
    }
}
