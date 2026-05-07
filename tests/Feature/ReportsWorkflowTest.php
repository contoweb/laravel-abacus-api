<?php

namespace Contoweb\AbacusApi\Tests\Feature;

use Contoweb\AbacusApi\Reports\AbacusReportsClient;
use Contoweb\AbacusApi\Reports\AbacusReportsService;
use Contoweb\AbacusApi\Reports\Abstracts\Report;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

/* Test report model for integration testing */
class SalesReportModel
{
    public function __construct(
        public ?string $invoice_id = null,
        public ?string $customer_name = null,
        public float $total_amount = 0,
        public ?string $date = null
    ) {}
}

/* Test report for integration testing */
class SalesReport extends Report
{
    public function name(): string
    {
        return '%2Fsales-report.avx';
    }

    public function mapping(array $record): SalesReportModel
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

        $client = new AbacusReportsClient($this->makeCredentialsProvider());
        $this->service = new AbacusReportsService($client);
    }

    #[Test]
    public function it_executes_complete_report_workflow(): void
    {
        $this->withoutDefer();

        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            /* Submit report */
            '*/api/abareport/v1/report/1212/%2Fsales-report.avx' => Http::response([
                'id' => 'job-workflow-123',
                'state' => 'Running',
                'mandate' => '1212',
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
                /* Delete job (deferred) */
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

        $report = new SalesReport([
            'startDate' => '2024-01-01',
            'endDate' => '2024-01-31',
        ]);
        $results = $this->service->collection($report);

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
    public function it_executes_different_reports_independently(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/1212/%2Fsales-report.avx' => Http::response([
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

        $salesReport = new SalesReport(['type' => 'sales']);

        /* Execute report */
        $salesResults = $this->service->collection($salesReport);

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
            '*/api/abareport/v1/report/1212/%2Fsales-report.avx' => Http::response([
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
            '*/api/abareport/v1/report/1212/%2Fsales-report.avx' => Http::response([
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
            '*/api/abareport/v1/report/1212/%2Fsales-report.avx' => Http::response([
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
    public function it_handles_concurrent_report_executions(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/1212/%2Fsales-report.avx' => Http::response([
                'id' => 'job-concurrent',
                'state' => 'Running',
            ], 202),
            '*/api/abareport/v1/jobs/job-concurrent' => Http::sequence()
                ->push(['id' => 'job-concurrent', 'state' => 'FinishedSuccess'], 200)  /* poll 1 */
                ->push(['id' => 'job-concurrent', 'state' => 'FinishedSuccess'], 200)  /* poll 2 */
                ->push(['id' => 'job-concurrent', 'state' => 'FinishedSuccess'], 200)  /* poll 3 */
                ->push(null, 204)  /* deferred delete 1 */
                ->push(null, 204)  /* deferred delete 2 */
                ->push(null, 204), /* deferred delete 3 */
            '*/api/abareport/v1/jobs/job-concurrent/output' => Http::response([
                ['InvoiceId' => 'C-001', 'CustomerName' => 'Concurrent', 'TotalAmount' => 100, 'Date' => '2024-01-01'],
            ], 200),
        ]);

        /* Execute multiple times with different parameters */
        $results1 = $this->service->collection(new SalesReport(['region' => 'US']));
        $results2 = $this->service->collection(new SalesReport(['region' => 'EU']));
        $results3 = $this->service->collection(new SalesReport(['region' => 'ASIA']));

        $this->assertCount(1, $results1);
        $this->assertCount(1, $results2);
        $this->assertCount(1, $results3);

        /* Flush deferred callbacks so DELETE requests run before assertion */
        defer()->invoke();

        /* token + 3×(submit + poll + output) + 3×deferred deletes = 1 + 9 + 3 = 13 */
        Http::assertSentCount(13);
    }
}
