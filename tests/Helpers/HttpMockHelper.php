<?php

namespace Contoweb\AbacusApi\Tests\Helpers;

use Contoweb\AbacusApi\Tests\Fixtures\ResponseFixtures;
use Illuminate\Support\Facades\Http;

/* Helper class for common HTTP mocking scenarios */
class HttpMockHelper
{
    /**
     * Mock a complete successful API workflow
     */
    public static function mockSuccessfulWorkflow(array $customResponses = []): void
    {
        $defaultResponses = [
            '*/oauth/oauth2/v1/token' => Http::response(ResponseFixtures::successfulTokenResponse(), 200),
        ];

        Http::fake(array_merge($defaultResponses, $customResponses));
    }

    /**
     * Mock a CRUD workflow
     */
    public static function mockCrudWorkflow(string $resource = 'Subjects', int $entityId = 1): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response(ResponseFixtures::successfulTokenResponse(), 200),

            /* Create */
            "*/api/entity/v1/mandants/*/{$resource}" => Http::response(
                ResponseFixtures::createdEntityResponse(['Name' => 'Created']),
                201
            ),

            /* Read */
            "*/api/entity/v1/mandants/*/{$resource}({$entityId})" => Http::response(
                ResponseFixtures::singleEntityResponse($entityId),
                200
            ),

            /* Update */
            "*/api/entity/v1/mandants/*/{$resource}({$entityId})" => Http::response(
                ResponseFixtures::singleEntityResponse($entityId),
                200
            ),

            /* Delete */
            "*/api/entity/v1/mandants/*/{$resource}({$entityId})" => Http::response(null, 204),

            /* List */
            "*/api/entity/v1/mandants/*/{$resource}*" => Http::response(
                ResponseFixtures::entityListResponse(3),
                200
            ),
        ]);
    }

    /**
     * Mock pagination workflow
     */
    public static function mockPagination(int $totalPages = 3): void
    {
        $responses = [
            '*/oauth/oauth2/v1/token' => Http::response(ResponseFixtures::successfulTokenResponse(), 200),
        ];

        for ($page = 1; $page <= $totalPages; $page++) {
            $hasNext = $page < $totalPages;

            if ($page === 1) {
                $responses['*/api/entity/v1/mandants/*/Subjects*'] = Http::response(
                    ResponseFixtures::paginatedResponse($page, $hasNext),
                    200
                );
            } else {
                $responses['https://api.example.com/api/entities?skip='.(($page - 1) * 10)] = Http::response(
                    ResponseFixtures::paginatedResponse($page, $hasNext),
                    200
                );
            }
        }

        Http::fake($responses);
    }

    /**
     * Mock token refresh scenario
     */
    public static function mockTokenRefresh(): void
    {
        $callCount = 0;

        Http::fake(function () use (&$callCount) {
            $callCount++;

            if (str_contains(request()->url(), 'oauth/token')) {
                return Http::response(ResponseFixtures::successfulTokenResponse(), 200);
            }

            /* Second API call returns 401 */
            if ($callCount === 3) {
                return Http::response(ResponseFixtures::unauthorizedResponse(), 401);
            }

            return Http::response(ResponseFixtures::entityListResponse(1), 200);
        });
    }

    /**
     * Mock report execution workflow
     */
    public static function mockReportExecution(
        string $reportName = 'test-report.avx',
        string $jobId = 'job-123',
        int $pollAttempts = 2
    ): void {
        $responses = [
            '*/oauth/oauth2/v1/token' => Http::response(ResponseFixtures::successfulTokenResponse(), 200),
            "*/api/abareport/v1/report/{$reportName}" => Http::response(
                ResponseFixtures::reportJobSubmittedResponse($jobId),
                202
            ),
            "*/api/abareport/v1/jobs/{$jobId}/output" => Http::response(
                ResponseFixtures::reportOutputResponse(5),
                200
            ),
        ];

        /* Create polling responses */
        $sequence = Http::sequence();
        for ($i = 0; $i < $pollAttempts - 1; $i++) {
            $sequence->push(ResponseFixtures::reportJobRunningResponse($jobId), 200);
        }
        $sequence->push(ResponseFixtures::reportJobFinishedResponse($jobId), 200);

        $responses["*/api/abareport/v1/jobs/{$jobId}"] = $sequence;

        Http::fake($responses);
    }

    /**
     * Mock failed report execution
     */
    public static function mockFailedReport(string $error = 'Report execution failed'): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response(ResponseFixtures::successfulTokenResponse(), 200),
            '*/api/abareport/v1/report/*' => Http::response(
                ResponseFixtures::reportJobSubmittedResponse('job-fail'),
                202
            ),
            '*/api/abareport/v1/jobs/job-fail' => Http::response(
                ResponseFixtures::reportJobFailedResponse('job-fail', $error),
                200
            ),
        ]);
    }

    /**
     * Mock metadata request
     */
    public static function mockMetadata(): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response(ResponseFixtures::successfulTokenResponse(), 200),
            '*/api/entity/v1/mandants/*/$metadata' => Http::response(
                ResponseFixtures::metadataResponse(),
                200,
                ['Content-Type' => 'application/xml']
            ),
        ]);
    }

    /**
     * Mock error responses
     */
    public static function mockError(int $statusCode = 400, string $message = 'Bad Request'): void
    {
        Http::fake([
            '*/oauth/oauth2/v1/token' => Http::response(ResponseFixtures::successfulTokenResponse(), 200),
            '*' => Http::response(ResponseFixtures::errorResponse($statusCode, $message), $statusCode),
        ]);
    }
}
