<?php

namespace Contoweb\AbacusApi\Reports;

use Contoweb\AbacusApi\AbacusClient;
use Contoweb\AbacusApi\Reports\Exceptions\ReportExecutionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

class AbacusReportsClient extends AbacusClient
{
    /**
     * Start a report.
     *
     * @param  string  $reportName  Name of the report (e.g., "mandate%2Freport.avx")
     * @param  array|string  $parameters  Report parameters
     * @param  string  $outputType  Output format (default: json)
     * @return array Response containing job ID and state
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws ReportExecutionException
     */
    public function startReport(string $reportName, array|string $parameters = [], string $outputType = 'json'): array
    {
        $path = $this->reportPath($reportName);

        $response = $this->post($path, [
            'outputType' => $outputType,
            'parameters' => $parameters,
        ]);

        if ($response->failed()) {
            throw new ReportExecutionException(
                'Starting AbaReport failed with '.$response->status().': '.$response->body()
            );
        }

        return $response->json();
    }

    /**
     * Get the job status.
     *
     * @param  string  $jobId  Job identifier
     * @return array Job status information
     *
     * @throws ConnectionException
     * @throws ReportExecutionException
     * @throws RequestException
     */
    public function getJobStatus(string $jobId): array
    {
        $path = $this->jobPath($jobId);

        $response = $this->get($path);

        if ($response->failed()) {
            throw new ReportExecutionException(
                'Fetching AbaReport status for job id "'.$jobId.
                '" failed with '.$response->status().': '.$response->body()
            );
        }

        return $response->json();
    }

    /**
     * Get the job output (final result).
     *
     * @param  string  $jobId  Job identifier
     * @return string Job output
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws ReportExecutionException
     */
    public function getJobOutput(string $jobId): string
    {
        $path = $this->jobOutputPath($jobId);

        $response = $this->get($path);

        if ($response->failed()) {
            throw new ReportExecutionException(
                'Fetching AbaReport output for job id "'.$jobId.
                '" responded with '.$response->status().': '.$response->body()
            );
        }

        return $response->body();
    }

    /**
     * Delete/close a report job session.
     *
     * @param  string  $jobId  Job identifier
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws ReportExecutionException
     */
    public function deleteJob(string $jobId): void
    {
        $path = $this->jobPath($jobId);

        $response = $this->delete($path);

        if ($response->failed()) {
            throw new ReportExecutionException(
                'Deleting AbaReport with job id "'.$jobId.
                '" failed with '.$response->status().': '.$response->body()
            );
        }
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
        return "{$this->getReportBasePath()}/report/$this->mandate/$reportName";
    }

    /**
     * Build job status path
     */
    public function jobPath(string $jobId): string
    {
        return $this->getReportBasePath().'/jobs/'.$jobId;
    }

    /**
     * Build job output path
     */
    public function jobOutputPath(string $jobId): string
    {
        return $this->getReportBasePath().'/jobs/'.$jobId.'/output';
    }
}
