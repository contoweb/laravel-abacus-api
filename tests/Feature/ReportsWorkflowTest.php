<?php

namespace Contoweb\AbacusApi\Tests\Feature;

use Contoweb\AbacusApi\Reports\AbacusReportsClient;
use Contoweb\AbacusApi\Reports\AbacusReportsService;
use Contoweb\AbacusApi\Reports\Contracts\Report;
use Contoweb\AbacusApi\Reports\Contracts\ReportModel;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

/* Test report model for integration testing */
class SalesReportModel implements ReportModel
{
    public function __construct(
        public ?string $invoice_id = null,
        public ?string $customer_name = null,
        public float $total_amount = 0,
        public ?string $date = null
    ) {}
}

/* Test report for integration testing */
class SalesReport implements Report
{
    public function name(): string
    {
        return 'mandate%2Fsales-report.avx';
    }

    public function mapping(array $record): ReportModel
    {
        return new SalesReportModel(
            invoice_id: $record['InvoiceId'] ?? null,
            customer_name: $record['CustomerName'] ?? null,
            total_amount: $record['TotalAmount'] ?? 0,
            date: $record['Date'] ?? null
        );
    }
}

class ReportsWorkflowTest extends TestCase
{
    protected AbacusReportsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $client = new AbacusReportsClient($this->makeCredentialsProvider());
        $this->service = new AbacusReportsService($client);
    }

    #[Test]
    public function it_executes_complete_report_workflow(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            /* Submit report */
            '*/api/abareport/v1/report/mandate%2Fsales-report.avx' => Http::response([
                'id' => 'job-workflow-123',
                'state' => 'Running',
                'mandate' => 'test-mandate',
            ], 202),
            /* Poll status - first returns Running */
            '*/api/abareport/v1/jobs/job-workflow-123' => Http::sequence()
                ->push([
                    'id' => 'job-workflow-123',
                    'state' => 'Running',
                ], 200)
                ->push([
                    'id' => 'job-workflow-123',
                    'state' => 'Running',
                ], 200)
                ->push([
                    'id' => 'job-workflow-123',
                    'state' => 'FinishedSuccess',
                ], 200)
                /* Delete job */
                ->push(null, 204),
            /* Get output */
            '*/api/abareport/v1/jobs/job-workflow-123/output' => Http::response([
                [
                    'InvoiceId' => 'INV-001',
                    'CustomerName' => 'Acme Corp',
                    'TotalAmount' => 1500.00,
                    'Date' => '2024-01-15',
                ],
                [
                    'InvoiceId' => 'INV-002',
                    'CustomerName' => 'Tech Industries',
                    'TotalAmount' => 2300.50,
                    'Date' => '2024-01-16',
                ],
            ], 200),
        ]);

        $report = new SalesReport;
        $results = $this->service
            ->parameter([
                'startDate' => '2024-01-01',
                'endDate' => '2024-01-31',
            ])
            ->collection($report);

        $this->assertCount(2, $results);
        $this->assertEquals('INV-001', $results[0]->invoice_id);
        $this->assertEquals('Acme Corp', $results[0]->customer_name);
        $this->assertEquals(1500.00, $results[0]->total_amount);
        $this->assertEquals('INV-002', $results[1]->invoice_id);

        /* Verify that deleteJob was called */
        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE' &&
                   str_contains($request->url(), '/jobs/job-workflow-123');
        });
    }

    #[Test]
    public function it_caches_report_results_between_executions(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/mandate%2Fsales-report.avx' => Http::response([
                'id' => 'job-cache-456',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-cache-456' => Http::sequence()
                ->push([
                    'id' => 'job-cache-456',
                    'state' => 'FinishedSuccess',
                ], 200)
                ->push(null, 204), /* Delete job */
            '*/api/abareport/v1/jobs/job-cache-456/output' => Http::response([
                [
                    'InvoiceId' => 'CACHED-001',
                    'CustomerName' => 'Cached Customer',
                    'TotalAmount' => 999.99,
                    'Date' => '2024-01-01',
                ],
            ], 200),
        ]);

        $report = new SalesReport;

        /* First execution - fetches from API */
        $results1 = $this->service
            ->parameter(['month' => 'January'])
            ->cache(3600)
            ->collection($report);

        /* Second execution with same parameters - uses cache */
        $results2 = $this->service
            ->parameter(['month' => 'January'])
            ->cache(3600)
            ->collection($report);

        $this->assertEquals($results1, $results2);

        /* Should only submit report once (token + submit + status + output + delete) */
        Http::assertSentCount(5);
    }

    #[Test]
    public function it_executes_different_reports_independently(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/mandate%2Fsales-report.avx' => Http::response([
                'id' => 'job-sales',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-sales' => Http::response([
                'id' => 'job-sales',
                'state' => 'FinishedSuccess',
            ], 200),
            '*/api/abareport/v1/jobs/job-sales/output' => Http::response([
                ['InvoiceId' => 'S-001', 'CustomerName' => 'Sales Data', 'TotalAmount' => 100, 'Date' => '2024-01-01'],
            ], 200),
        ]);

        $salesReport = new SalesReport;

        /* Execute first report */
        $salesResults = $this->service
            ->parameter(['type' => 'sales'])
            ->collection($salesReport);

        $this->assertCount(1, $salesResults);
        $this->assertEquals('S-001', $salesResults[0]->invoice_id);
    }

    #[Test]
    public function it_handles_report_with_no_parameters(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/mandate%2Fsales-report.avx' => Http::response([
                'id' => 'job-no-params',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-no-params' => Http::response([
                'id' => 'job-no-params',
                'state' => 'FinishedSuccess',
            ], 200),
            '*/api/abareport/v1/jobs/job-no-params/output' => Http::response([
                ['InvoiceId' => 'ALL-001', 'CustomerName' => 'All Data', 'TotalAmount' => 500, 'Date' => '2024-01-01'],
            ], 200),
        ]);

        $report = new SalesReport;
        $results = $this->service->collection($report);

        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_handles_large_result_sets(): void
    {
        $largeDataset = [];
        for ($i = 1; $i <= 1000; $i++) {
            $largeDataset[] = [
                'InvoiceId' => "INV-{$i}",
                'CustomerName' => "Customer {$i}",
                'TotalAmount' => $i * 10.5,
                'Date' => '2024-01-01',
            ];
        }

        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/mandate%2Fsales-report.avx' => Http::response([
                'id' => 'job-large',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-large' => Http::response([
                'id' => 'job-large',
                'state' => 'FinishedSuccess',
            ], 200),
            '*/api/abareport/v1/jobs/job-large/output' => Http::response($largeDataset, 200),
        ]);

        $report = new SalesReport;
        $results = $this->service->collection($report);

        $this->assertCount(1000, $results);
        $this->assertEquals('INV-1', $results[0]->invoice_id);
        $this->assertEquals('INV-1000', $results[999]->invoice_id);
    }

    #[Test]
    public function it_handles_empty_report_results(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/mandate%2Fsales-report.avx' => Http::response([
                'id' => 'job-empty',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-empty' => Http::response([
                'id' => 'job-empty',
                'state' => 'FinishedSuccess',
            ], 200),
            '*/api/abareport/v1/jobs/job-empty/output' => Http::response([], 200),
        ]);

        $report = new SalesReport;
        $results = $this->service->collection($report);

        $this->assertCount(0, $results);
    }

    #[Test]
    public function it_uses_custom_cache_keys(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/mandate%2Fsales-report.avx' => Http::response([
                'id' => 'job-custom-key',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-custom-key' => Http::response([
                'id' => 'job-custom-key',
                'state' => 'FinishedSuccess',
            ], 200),
            '*/api/abareport/v1/jobs/job-custom-key/output' => Http::response([
                ['InvoiceId' => 'CK-001', 'CustomerName' => 'Custom', 'TotalAmount' => 100, 'Date' => '2024-01-01'],
            ], 200),
        ]);

        $report = new SalesReport;
        $this->service
            ->parameter(['region' => 'EU'])
            ->cache(3600, 'eu-sales-report')
            ->collection($report);

        $this->assertTrue(Cache::has('abacus_report:eu-sales-report'));
    }

    #[Test]
    public function it_handles_concurrent_report_executions(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/mandate%2Fsales-report.avx' => Http::response([
                'id' => 'job-concurrent',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-concurrent' => Http::sequence()
                ->push(['id' => 'job-concurrent', 'state' => 'FinishedSuccess'], 200)
                ->push(null, 204) /* Delete job 1 */
                ->push(['id' => 'job-concurrent', 'state' => 'FinishedSuccess'], 200)
                ->push(null, 204) /* Delete job 2 */
                ->push(['id' => 'job-concurrent', 'state' => 'FinishedSuccess'], 200)
                ->push(null, 204), /* Delete job 3 */
            '*/api/abareport/v1/jobs/job-concurrent/output' => Http::response([
                ['InvoiceId' => 'C-001', 'CustomerName' => 'Concurrent', 'TotalAmount' => 100, 'Date' => '2024-01-01'],
            ], 200),
        ]);

        $report = new SalesReport;

        /* Execute multiple times with different parameters */
        $results1 = $this->service
            ->parameter(['region' => 'US'])
            ->collection($report);

        $results2 = $this->service
            ->parameter(['region' => 'EU'])
            ->collection($report);

        $results3 = $this->service
            ->parameter(['region' => 'ASIA'])
            ->collection($report);

        $this->assertCount(1, $results1);
        $this->assertCount(1, $results2);
        $this->assertCount(1, $results3);

        /* First: token + submit + status + output + delete = 5 */
        /* Second + Third: submit + status + output + delete = 4 each (token cached) */
        /* Total: 5 + 4 + 4 = 13 */
        Http::assertSentCount(13);
    }
}
