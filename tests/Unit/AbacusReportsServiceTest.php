<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\Reports\AbacusReportsClient;
use Contoweb\AbacusApi\Reports\AbacusReportsService;
use Contoweb\AbacusApi\Reports\Contracts\Report;
use Contoweb\AbacusApi\Reports\Contracts\RequiresValidationRules;
use Contoweb\AbacusApi\Reports\Exceptions\ReportExecutionException;
use Contoweb\AbacusApi\Reports\Exceptions\ReportValidationException;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

/* Test report model */
class SimpleReportModel implements \Contoweb\AbacusApi\Reports\Contracts\ReportModel
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null
    ) {}
}

/* Test report classes */
class SimpleReport implements Report
{
    public function name(): string
    {
        return 'test-report.avx';
    }

    public function mapping(array $record): \Contoweb\AbacusApi\Reports\Contracts\ReportModel
    {
        return new SimpleReportModel(
            id: $record['Id'] ?? null,
            name: $record['Name'] ?? null
        );
    }
}

class ValidatedReport implements Report, RequiresValidationRules
{
    public function name(): string
    {
        return 'validated-report.avx';
    }

    public function mapping(array $record): \Contoweb\AbacusApi\Reports\Contracts\ReportModel
    {
        return new SimpleReportModel(
            id: $record['Id'] ?? null,
            name: null
        );
    }

    public static function validationRules(): array
    {
        return [
            'startDate' => 'required|date',
            'endDate' => 'required|date|after:startDate',
        ];
    }
}

class AbacusReportsServiceTest extends TestCase
{
    protected AbacusReportsService $service;

    protected AbacusReportsClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->client = new AbacusReportsClient;
        $this->service = new AbacusReportsService($this->client);
    }

    #[Test]
    public function it_sets_parameters(): void
    {
        $result = $this->service->parameter(['param1' => 'value1']);

        $this->assertInstanceOf(AbacusReportsService::class, $result);
    }

    #[Test]
    public function it_enables_cache(): void
    {
        $result = $this->service->cache(7200, 'custom-key');

        $this->assertInstanceOf(AbacusReportsService::class, $result);
    }

    #[Test]
    public function it_executes_simple_report(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/test-report.avx' => Http::response([
                'id' => 'job-123',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-123' => Http::response([
                'id' => 'job-123',
                'state' => 'FinishedSuccess',
            ], 200),
            '*/api/abareport/v1/jobs/job-123/output' => Http::response([
                ['Id' => 1, 'Name' => 'Item 1'],
                ['Id' => 2, 'Name' => 'Item 2'],
            ], 200),
        ]);

        $report = new SimpleReport;
        $results = $this->service->collection($report);

        $this->assertCount(2, $results);
        $this->assertEquals(1, $results[0]->id);
        $this->assertEquals('Item 1', $results[0]->name);
    }

    #[Test]
    public function it_executes_report_with_parameters(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/test-report.avx' => Http::response([
                'id' => 'job-456',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-456' => Http::response([
                'id' => 'job-456',
                'state' => 'FinishedSuccess',
            ], 200),
            '*/api/abareport/v1/jobs/job-456/output' => Http::response([
                ['Id' => 100, 'Name' => 'Filtered'],
            ], 200),
        ]);

        $report = new SimpleReport;
        $results = $this->service
            ->parameter(['filter' => 'active'])
            ->collection($report);

        $this->assertCount(1, $results);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/report/test-report.avx')) {
                return false;
            }
            $data = $request->data();

            return isset($data['parameters']['filter']) &&
                   $data['parameters']['filter'] === 'active';
        });
    }

    #[Test]
    public function it_caches_report_results(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/test-report.avx' => Http::response([
                'id' => 'job-cache',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-cache' => Http::sequence()
                ->push([
                    'id' => 'job-cache',
                    'state' => 'FinishedSuccess',
                ], 200)
                ->push(null, 204), /* Delete job */
            '*/api/abareport/v1/jobs/job-cache/output' => Http::response([
                ['Id' => 1, 'Name' => 'Cached'],
            ], 200),
        ]);

        $report = new SimpleReport;

        /* First call - fetches from API */
        $results1 = $this->service
            ->cache(3600)
            ->collection($report);

        /* Second call - should use cache */
        $results2 = $this->service
            ->cache(3600)
            ->collection($report);

        $this->assertEquals($results1, $results2);

        /* Should only submit report once (token + submit + status + output + delete) */
        Http::assertSentCount(5);
    }

    #[Test]
    public function it_uses_custom_cache_key(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/test-report.avx' => Http::response([
                'id' => 'job-custom',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-custom' => Http::response([
                'id' => 'job-custom',
                'state' => 'FinishedSuccess',
            ], 200),
            '*/api/abareport/v1/jobs/job-custom/output' => Http::response([
                ['Id' => 1, 'Name' => 'Custom'],
            ], 200),
        ]);

        $report = new SimpleReport;
        $this->service
            ->cache(3600, 'my-custom-key')
            ->collection($report);

        $this->assertTrue(Cache::has('abacus_report:my-custom-key'));
    }

    #[Test]
    public function it_validates_report_parameters(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $this->expectException(ReportValidationException::class);

        $report = new ValidatedReport;
        $this->service
            ->parameter(['startDate' => '2024-01-01'])  /* Missing endDate */
            ->collection($report);
    }

    #[Test]
    public function it_passes_validation_with_valid_parameters(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/validated-report.avx' => Http::response([
                'id' => 'job-valid',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-valid' => Http::response([
                'id' => 'job-valid',
                'state' => 'FinishedSuccess',
            ], 200),
            '*/api/abareport/v1/jobs/job-valid/output' => Http::response([
                ['Id' => 1],
            ], 200),
        ]);

        $report = new ValidatedReport;
        $results = $this->service
            ->parameter([
                'startDate' => '2024-01-01',
                'endDate' => '2024-12-31',
            ])
            ->collection($report);

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_throws_exception_on_immediate_error(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/test-report.avx' => Http::response([
                'status' => 403,
                'title' => 'Access denied',
            ], 200),
        ]);

        $this->expectException(ReportExecutionException::class);
        $this->expectExceptionMessage('AbaReport failed with message: Access denied');

        $report = new SimpleReport;
        $this->service->collection($report);
    }

    #[Test]
    public function it_throws_exception_when_no_job_id_returned(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/test-report.avx' => Http::response([
                'state' => 'Running',
                /* Missing 'id' field */
            ], 202),
        ]);

        $this->expectException(ReportExecutionException::class);
        $this->expectExceptionMessage('Report submission did not return a job ID');

        $report = new SimpleReport;
        $this->service->collection($report);
    }

    #[Test]
    public function it_throws_exception_when_final_state_not_finished_success(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/test-report.avx' => Http::response([
                'id' => 'job-fail',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-fail' => Http::response([
                'id' => 'job-fail',
                'state' => 'Failed',
                'message' => 'Report execution failed',
            ], 200),
        ]);

        $this->expectException(ReportExecutionException::class);
        $this->expectExceptionMessage('AbaReport failed since it was not successful');

        $report = new SimpleReport;
        $this->service->collection($report);
    }

    #[Test]
    public function it_throws_exception_when_output_record_is_not_array(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/test-report.avx' => Http::response([
                'id' => 'job-invalid',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-invalid' => Http::response([
                'id' => 'job-invalid',
                'state' => 'FinishedSuccess',
            ], 200),
            /* Return array containing a string instead of array of arrays */
            '*/api/abareport/v1/jobs/job-invalid/output' => Http::response([
                'not-an-array-record',
            ], 200),
        ]);

        $this->expectException(ReportExecutionException::class);
        $this->expectExceptionMessage('Report record is not a valid array');

        $report = new SimpleReport;
        $this->service->collection($report);
    }

    #[Test]
    public function it_resets_state_after_execution(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/*' => Http::response([
                'id' => 'job-reset',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-reset' => Http::sequence()
                ->push(['id' => 'job-reset', 'state' => 'FinishedSuccess'], 200)
                ->push(null, 204) /* Delete job 1 */
                ->push(['id' => 'job-reset', 'state' => 'FinishedSuccess'], 200)
                ->push(null, 204), /* Delete job 2 */
            '*/api/abareport/v1/jobs/job-reset/output' => Http::response([
                ['Id' => 1],
            ], 200),
        ]);

        $report = new SimpleReport;

        /* Execute with parameters and cache */
        $this->service
            ->parameter(['test' => 'value'])
            ->cache(3600)
            ->collection($report);

        /* Execute again without explicit parameters - should start fresh */
        $results = $this->service->collection($report);

        $this->assertCount(1, $results);

        /* Should make new API calls (not use cache from first execution) */
        /* First execution: token + submit + status + output + delete = 5 */
        /* Second execution: submit + status + output + delete = 4 (token is cached) */
        Http::assertSentCount(9);
    }

    #[Test]
    public function it_generates_unique_cache_keys_for_different_parameters(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/test-report.avx' => Http::response([
                'id' => 'job-key',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-key' => Http::sequence()
                ->push(['id' => 'job-key', 'state' => 'FinishedSuccess'], 200)
                ->push(null, 204) /* Delete job 1 */
                ->push(['id' => 'job-key', 'state' => 'FinishedSuccess'], 200)
                ->push(null, 204), /* Delete job 2 */
            '*/api/abareport/v1/jobs/job-key/output' => Http::response([
                ['Id' => 1],
            ], 200),
        ]);

        $report = new SimpleReport;

        /* Execute with first set of parameters */
        $this->service
            ->parameter(['param' => 'value1'])
            ->cache(3600)
            ->collection($report);

        /* Execute with different parameters */
        $this->service
            ->parameter(['param' => 'value2'])
            ->cache(3600)
            ->collection($report);

        /* Should make separate API calls for different parameters */
        /* First execution: token + submit + status + output + delete = 5 */
        /* Second execution: submit + status + output + delete = 4 (token cached) */
        Http::assertSentCount(9);
    }

    #[Test]
    public function it_handles_empty_output_array(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/test-report.avx' => Http::response([
                'id' => 'job-empty',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-empty' => Http::response([
                'id' => 'job-empty',
                'state' => 'FinishedSuccess',
            ], 200),
            '*/api/abareport/v1/jobs/job-empty/output' => Http::response([], 200),
        ]);

        $report = new SimpleReport;
        $results = $this->service->collection($report);

        $this->assertCount(0, $results);
    }
}
