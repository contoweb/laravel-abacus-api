<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\Reports\AbacusReportsClient;
use Contoweb\AbacusApi\Reports\Exceptions\ReportExecutionException;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class AbacusReportsClientTest extends TestCase
{
    protected AbacusReportsClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new AbacusReportsClient;
    }

    #[Test]
    public function it_builds_report_path(): void
    {
        $path = $this->client->reportPath('mandate%2Freport.avx');

        $this->assertEquals('/api/abareport/v1/report/mandate%2Freport.avx', $path);
    }

    #[Test]
    public function it_builds_job_path(): void
    {
        $path = $this->client->jobPath('job-123-abc');

        $this->assertEquals('/api/abareport/v1/jobs/job-123-abc', $path);
    }

    #[Test]
    public function it_builds_job_output_path(): void
    {
        $path = $this->client->jobOutputPath('job-456-def');

        $this->assertEquals('/api/abareport/v1/jobs/job-456-def/output', $path);
    }

    #[Test]
    public function it_submits_report(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/test-report.avx' => Http::response([
                'id' => 'job-123',
                'state' => 'Running',
                'mandate' => 'test-mandate',
            ], 202),
        ]);

        $result = $this->client->submitReport('test-report.avx', ['param1' => 'value1'], 'json');

        $this->assertIsArray($result);
        $this->assertEquals('job-123', $result['id']);
        $this->assertEquals('Running', $result['state']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->method() === 'POST' &&
                   str_contains($request->url(), '/report/test-report.avx') &&
                   $data['outputType'] === 'json' &&
                   isset($data['parameters']['param1']) &&
                   $data['parameters']['param1'] === 'value1';
        });
    }

    #[Test]
    public function it_submits_report_with_default_output_type(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/simple.avx' => Http::response([
                'id' => 'job-456',
                'state' => 'Running',
            ], 202),
        ]);

        $this->client->submitReport('simple.avx');

        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['outputType']) && $data['outputType'] === 'json';
        });
    }

    #[Test]
    public function it_gets_job_status(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/jobs/job-789' => Http::response([
                'id' => 'job-789',
                'state' => 'Finished',
                'mandate' => 'test-mandate',
            ], 200),
        ]);

        $result = $this->client->getJobStatus('job-789');

        $this->assertIsArray($result);
        $this->assertEquals('job-789', $result['id']);
        $this->assertEquals('Finished', $result['state']);

        Http::assertSent(function ($request) {
            return $request->method() === 'GET' &&
                   str_contains($request->url(), '/jobs/job-789');
        });
    }

    #[Test]
    public function it_gets_job_output(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/jobs/job-999/output' => Http::response([
                'data' => [
                    ['Id' => 1, 'Name' => 'Result 1'],
                    ['Id' => 2, 'Name' => 'Result 2'],
                ],
            ], 200),
        ]);

        $result = $this->client->getJobOutput('job-999');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(2, $result['data']);

        Http::assertSent(function ($request) {
            return $request->method() === 'GET' &&
                   str_contains($request->url(), '/jobs/job-999/output');
        });
    }

    #[Test]
    public function it_returns_empty_array_for_null_output(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/jobs/job-empty/output' => Http::response('', 200),
        ]);

        $result = $this->client->getJobOutput('job-empty');

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function it_polls_job_until_complete(): void
    {
        $callCount = 0;

        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/jobs/job-poll' => function () use (&$callCount) {
                $callCount++;

                /* First two calls return Running state */
                if ($callCount <= 2) {
                    return Http::response([
                        'id' => 'job-poll',
                        'state' => 'Running',
                    ], 200);
                }

                /* Third call returns Finished */
                return Http::response([
                    'id' => 'job-poll',
                    'state' => 'Finished',
                ], 200);
            },
        ]);

        $result = $this->client->pollJobUntilComplete('job-poll', 10000, 10);

        $this->assertEquals('Finished', $result['state']);
        $this->assertEquals(3, $callCount); /* 2 running + 1 finished */
    }

    #[Test]
    public function it_throws_exception_when_polling_times_out(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/jobs/job-timeout' => Http::response([
                'id' => 'job-timeout',
                'state' => 'Running',
            ], 200),
        ]);

        $this->expectException(ReportExecutionException::class);
        $this->expectExceptionMessage('Report job polling timed out after 5 attempts');

        $this->client->pollJobUntilComplete('job-timeout', 10000, 5);
    }

    #[Test]
    public function it_throws_exception_on_403_error(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/jobs/job-forbidden' => Http::response([
                'id' => 'job-forbidden',
                'status' => 403,
                'title' => 'Access forbidden',
            ], 200),
        ]);

        $this->expectException(ReportExecutionException::class);
        $this->expectExceptionMessage('AbaReport failed with message: Access forbidden');

        $this->client->pollJobUntilComplete('job-forbidden', 10000, 10);
    }

    #[Test]
    public function it_throws_exception_on_500_error(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/jobs/job-error' => Http::response([
                'id' => 'job-error',
                'status' => 500,
                'title' => 'Internal server error',
            ], 200),
        ]);

        $this->expectException(ReportExecutionException::class);
        $this->expectExceptionMessage('AbaReport failed with message: Internal server error');

        $this->client->pollJobUntilComplete('job-error', 10000, 10);
    }

    #[Test]
    public function it_handles_error_without_title(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/jobs/job-no-title' => Http::response([
                'id' => 'job-no-title',
                'status' => 500,
            ], 200),
        ]);

        $this->expectException(ReportExecutionException::class);
        $this->expectExceptionMessage('AbaReport failed with message: Unknown error');

        $this->client->pollJobUntilComplete('job-no-title', 10000, 10);
    }

    #[Test]
    public function it_stops_polling_when_state_is_not_running(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/jobs/job-complete' => Http::response([
                'id' => 'job-complete',
                'state' => 'Finished',
            ], 200),
        ]);

        $result = $this->client->pollJobUntilComplete('job-complete', 10000, 10);

        $this->assertEquals('Finished', $result['state']);

        /* Should only call once (token + status check) */
        Http::assertSentCount(2);
    }

    #[Test]
    public function it_submits_report_with_empty_parameters(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/no-params.avx' => Http::response([
                'id' => 'job-no-params',
                'state' => 'Running',
            ], 202),
        ]);

        $this->client->submitReport('no-params.avx', []);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['parameters']) && empty($data['parameters']);
        });
    }

    #[Test]
    public function it_uses_custom_poll_interval(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/jobs/job-custom' => Http::sequence()
                ->push(['id' => 'job-custom', 'state' => 'Running'], 200)
                ->push(['id' => 'job-custom', 'state' => 'Finished'], 200),
        ]);

        /* Using shorter interval for testing */
        $result = $this->client->pollJobUntilComplete('job-custom', 1000, 10);

        $this->assertEquals('Finished', $result['state']);
    }

    #[Test]
    public function it_deletes_job(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/jobs/job-delete-123' => Http::response(null, 204),
        ]);

        $this->client->deleteJob('job-delete-123');

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE' &&
                   str_contains($request->url(), '/jobs/job-delete-123');
        });
    }
}
