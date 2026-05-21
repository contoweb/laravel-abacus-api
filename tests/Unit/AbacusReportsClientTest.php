<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\Reports\AbacusReportsClient;
use Contoweb\AbacusApi\Tests\TestCase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class AbacusReportsClientTest extends TestCase
{
    protected AbacusReportsClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new AbacusReportsClient($this->makeCredentialsProvider());
    }

    #[Test]
    public function it_builds_report_path(): void
    {
        $path = $this->client->reportPath('mandate%2Freport.avx');

        $this->assertEquals('/api/abareport/v1/report/1212/mandate%2Freport.avx', $path);
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
            '*/api/abareport/v1/report/1212/test-report.avx' => Http::response([
                'id' => 'job-123',
                'state' => 'Running',
                'mandate' => '1212',
            ], 202),
        ]);

        $result = $this->client->startReport('test-report.avx', ['param1' => 'value1'], 'json');

        $this->assertIsArray($result);
        $this->assertEquals('job-123', $result['id']);
        $this->assertEquals('Running', $result['state']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->method() === 'POST' &&
                   str_contains($request->url(), '/report/1212/test-report.avx') &&
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
            '*/api/abareport/v1/report/1212/simple.avx' => Http::response([
                'id' => 'job-456',
                'state' => 'Running',
            ], 202),
        ]);

        $this->client->startReport('simple.avx');

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
                'mandate' => '1212',
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

        $this->assertIsString($result);
        $this->assertStringContainsString('"Result 1"', $result);
        $this->assertStringContainsString('"Result 2"', $result);

        Http::assertSent(function ($request) {
            return $request->method() === 'GET' &&
                   str_contains($request->url(), '/jobs/job-999/output');
        });
    }

    #[Test]
    public function it_returns_empty_string_for_empty_output(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/jobs/job-empty/output' => Http::response('', 200),
        ]);

        $result = $this->client->getJobOutput('job-empty');

        $this->assertIsString($result);
        $this->assertSame('', $result);
    }

    #[Test]
    public function it_throws_exception_when_start_report_fails(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/1212/test-report.avx' => Http::response('Access denied', 403),
        ]);

        $this->expectException(RequestException::class);

        $this->client->startReport('test-report.avx');
    }

    #[Test]
    public function it_throws_exception_when_get_job_status_fails(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/jobs/job-error' => Http::response('Internal Server Error', 500),
        ]);

        $this->expectException(RequestException::class);

        $this->client->getJobStatus('job-error');
    }

    #[Test]
    public function it_throws_exception_when_get_job_output_fails(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/jobs/job-error/output' => Http::response('Internal Server Error', 500),
        ]);

        $this->expectException(RequestException::class);

        $this->client->getJobOutput('job-error');
    }

    #[Test]
    public function it_submits_report_with_empty_parameters(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in' => 3600,
            ], 200),
            '*/api/abareport/v1/report/1212/no-params.avx' => Http::response([
                'id' => 'job-no-params',
                'state' => 'Running',
            ], 202),
        ]);

        $this->client->startReport('no-params.avx', []);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return isset($data['parameters']) && empty($data['parameters']);
        });
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
