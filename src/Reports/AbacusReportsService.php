<?php

namespace Contoweb\AbacusApi\Reports;

use Contoweb\AbacusApi\Reports\Contracts\Report;
use Contoweb\AbacusApi\Reports\Contracts\RequiresValidationRules;
use Contoweb\AbacusApi\Reports\Exceptions\ReportExecutionException;
use Contoweb\AbacusApi\Reports\Exceptions\ReportValidationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class AbacusReportsService
{
    /**
     * Parameters for the report request
     */
    protected array | string $parameters = [];

    /**
     * Cache configuration
     */
    protected bool    $cacheEnabled   = false;
    protected int     $cacheTtl       = 3600;
    protected ?string $customCacheKey = null;

    public function __construct(
        protected AbacusReportsClient $client
    ) {
    }

    /**
     * Set report parameters
     */
    public function parameter(array | string $parameters): static
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * Enable caching for this report
     */
    public function cache(int $ttl = 3600, ?string $cacheKey = null): static
    {
        $this->cacheEnabled   = true;
        $this->cacheTtl       = $ttl;
        $this->customCacheKey = $cacheKey;

        return $this;
    }

    /**
     * Execute report and return collection of models
     *
     * @param Report $report Report instance
     *
     * @return Collection Collection of report models
     * @throws ConnectionException
     * @throws ReportExecutionException
     * @throws ReportValidationException
     * @throws RequestException
     */
    public function collection(Report $report): Collection
    {
        /* Check cache if enabled */
        if ($this->cacheEnabled) {
            $cacheKey = $this->getCacheKey($report);
            $cached   = Cache::get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        /* Execute report and get models */
        $models     = $this->executeReport($report);
        $collection = collect($models);

        /* Store in cache if enabled */
        if ($this->cacheEnabled) {
            $cacheKey = $this->getCacheKey($report);
            Cache::put($cacheKey, $collection, $this->cacheTtl);
        }

        /* Reset state for next call */
        $this->resetState();

        return $collection;
    }

    /**
     * Execute report and map to models
     *
     * @param Report $report
     *
     * @return array
     * @throws ReportExecutionException
     * @throws ReportValidationException
     * @throws ConnectionException
     * @throws RequestException
     */
    protected function executeReport(Report $report): array
    {
        /* Validate parameters if required */
        if ($report instanceof RequiresValidationRules) {
            $this->validateParameters($this->parameters, $report::validationRules());
        }

        /* Submit report */
        $jobResponse = $this->client->submitReport(
            $report->name(),
            is_array($this->parameters) ? $this->parameters : []
        );

        /* Check for immediate errors */
        if (isset($jobResponse['status']) && ($jobResponse['status'] === 403 || $jobResponse['status'] === 500)) {
            throw new ReportExecutionException(
                'AbaReport failed with message: ' . ($jobResponse['title'] ?? 'Unknown error')
            );
        }

        if ( ! isset($jobResponse['id'])) {
            throw new ReportExecutionException('Report submission did not return a job ID');
        }

        $jobId = $jobResponse['id'];

        /* Poll until complete */
        $pollInterval = config('abacus-api.reports.poll_interval', 200000);
        $maxAttempts  = config('abacus-api.reports.max_poll_attempts', 150);
        $finalStatus  = $this->client->pollJobUntilComplete($jobId, $pollInterval, $maxAttempts);

        /* Check final status */
        if (($finalStatus['state'] ?? null) !== 'FinishedSuccess') {
            throw new ReportExecutionException(
                'AbaReport failed since it was not successful. Message: ' . ($finalStatus['message'] ?? 'unknown')
            );
        }

        /* Get result JSON */
        $jsonData = $this->client->getJobOutput($jobId);

        /* Close the report session */
        $this->client->deleteJob($jobId);
        
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
        /* Check if data is an array of records */
        if ( ! is_array($jsonData)) {
            throw new ReportExecutionException('Report output is not a valid array');
        }

        $models = [];

        foreach ($jsonData as $record) {
            if ( ! is_array($record)) {
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

    /**
     * Generate a cache key for a report
     */
    protected function getCacheKey(Report $report): string
    {
        if ($this->customCacheKey !== null) {
            return 'abacus_report:' . $this->customCacheKey;
        }

        $paramKey = is_array($this->parameters)
            ? md5(json_encode($this->parameters))
            : md5($this->parameters);

        return 'abacus_report:' . md5($report->name()) . ':' . $paramKey;
    }

    /**
     * Reset state after execution
     */
    protected function resetState(): void
    {
        $this->parameters     = [];
        $this->cacheEnabled   = false;
        $this->cacheTtl       = 3600;
        $this->customCacheKey = null;
    }
}
