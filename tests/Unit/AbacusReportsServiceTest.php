<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\Reports\AbacusReportsClient;
use Contoweb\AbacusApi\Reports\AbacusReportsService;
use Contoweb\AbacusApi\Reports\Contracts\Report;
use Contoweb\AbacusApi\Reports\Contracts\ReportModel;
use Contoweb\AbacusApi\Reports\Contracts\RequiresValidationRules;
use Contoweb\AbacusApi\Reports\Exceptions\ReportExecutionException;
use Contoweb\AbacusApi\Reports\Exceptions\ReportValidationException;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

/* Test report model */
class SimpleReportModel implements ReportModel
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null
    ) {}
}

/* Test report classes */
class SimpleReport extends Report
{
    public function name(): string
    {
        return 'test-report.avx';
    }

    public function mapping(array $record): ReportModel
    {
        return new SimpleReportModel(
            id: $record['Id'] ?? null,
            name: $record['Name'] ?? null
        );
    }
}

class ValidatedReport extends Report implements RequiresValidationRules
{
    public function name(): string
    {
        return 'validated-report.avx';
    }

    public function mapping(array $record): ReportModel
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

class CompactOutputReport extends Report
{
    protected string $outputType = 'json_userdef_compact';

    public function name(): string
    {
        return 'compact-report.avx';
    }

    public function mapping(array $record): ReportModel
    {
        return new SimpleReportModel(id: $record['Id'] ?? null);
    }
}

class AbacusReportsServiceTest extends TestCase
{
    protected AbacusReportsService $service;

    protected AbacusReportsClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new AbacusReportsClient($this->makeCredentialsProvider());
        $this->service = new AbacusReportsService($this->client);
    }

    #[Test]
    public function it_returns_default_output_type(): void
    {
        $report = new SimpleReport;

        $this->assertEquals('json_compact', $report->outputType());
    }

    #[Test]
    public function it_uses_custom_output_type(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/test-mandate/compact-report.avx' => Http::response([
                'id' => 'job-compact',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-compact' => Http::response([
                'id' => 'job-compact',
                'state' => 'FinishedSuccess',
            ], 200),
            '*/api/abareport/v1/jobs/job-compact/output' => Http::response([
                ['Id' => 1],
            ], 200),
        ]);

        $report = new CompactOutputReport;
        $this->service->collection($report);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/report/test-mandate/compact-report.avx')) {
                return false;
            }

            return ($request->data()['outputType'] ?? null) === 'json_userdef_compact';
        });
    }

    #[Test]
    public function it_includes_mandate_in_report_url(): void
    {
        $path = $this->client->reportPath('my-report.avx');

        $this->assertStringContainsString('/test-mandate/', $path);
        $this->assertStringEndsWith('my-report.avx', $path);
    }

    #[Test]
    public function it_executes_simple_report(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/test-mandate/test-report.avx' => Http::response([
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
            '*/api/abareport/v1/report/test-mandate/test-report.avx' => Http::response([
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

        $report = new SimpleReport(['filter' => 'active']);
        $results = $this->service->collection($report);

        $this->assertCount(1, $results);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/report/test-mandate/test-report.avx')) {
                return false;
            }
            $data = $request->data();

            return isset($data['parameters']['filter']) &&
                   $data['parameters']['filter'] === 'active';
        });
    }

    #[Test]
    public function it_executes_independently_for_different_report_instances(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/test-mandate/test-report.avx' => Http::sequence()
                ->push(['id' => 'job-ind-1', 'state' => 'Running'], 202)
                ->push(['id' => 'job-ind-2', 'state' => 'Running'], 202),
            '*/api/abareport/v1/jobs/job-ind-1' => Http::response([
                'id' => 'job-ind-1',
                'state' => 'FinishedSuccess',
            ], 200),
            '*/api/abareport/v1/jobs/job-ind-1/output' => Http::response([
                ['Id' => 1, 'Name' => 'Result A'],
            ], 200),
            '*/api/abareport/v1/jobs/job-ind-2' => Http::response([
                'id' => 'job-ind-2',
                'state' => 'FinishedSuccess',
            ], 200),
            '*/api/abareport/v1/jobs/job-ind-2/output' => Http::response([
                ['Id' => 2, 'Name' => 'Result B'],
            ], 200),
        ]);

        $results1 = $this->service->collection(new SimpleReport(['param' => 'value1']));
        $results2 = $this->service->collection(new SimpleReport(['param' => 'value2']));

        $this->assertCount(1, $results1);
        $this->assertCount(1, $results2);
        $this->assertEquals(1, $results1[0]->id);
        $this->assertEquals(2, $results2[0]->id);
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

        $report = new ValidatedReport(['startDate' => '2024-01-01']);  /* Missing endDate */
        $this->service->collection($report);
    }

    #[Test]
    public function it_passes_validation_with_valid_parameters(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/test-mandate/validated-report.avx' => Http::response([
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

        $report = new ValidatedReport([
            'startDate' => '2024-01-01',
            'endDate' => '2024-12-31',
        ]);
        $results = $this->service->collection($report);

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
            '*/api/abareport/v1/report/test-mandate/test-report.avx' => Http::response([
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
            '*/api/abareport/v1/report/test-mandate/test-report.avx' => Http::response([
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
            '*/api/abareport/v1/report/test-mandate/test-report.avx' => Http::response([
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
            '*/api/abareport/v1/report/test-mandate/test-report.avx' => Http::response([
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
    public function it_handles_empty_output_array(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/test-mandate/test-report.avx' => Http::response([
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
