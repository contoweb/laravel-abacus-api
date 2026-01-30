<?php

namespace Contoweb\AbacusApi\Tests\Helpers;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Assert;

/* Helper class for common test assertions */
class AssertionHelper
{
    /**
     * Assert that a request was sent with specific OData parameters
     */
    public static function assertODataParametersInRequest(array $expectedParams): void
    {
        Http::assertSent(function ($request) use ($expectedParams) {
            $url = $request->url();

            foreach ($expectedParams as $key => $value) {
                /* Handle special characters in OData parameters */
                $encodedKey = urlencode($key);

                if (! str_contains($url, $encodedKey)) {
                    return false;
                }

                /* Check if the parameter value is present */
                if (is_string($value) && ! str_contains($url, $value)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Assert that an entity was created with specific data
     */
    public static function assertEntityCreated(string $resource, array $expectedData): void
    {
        Http::assertSent(function ($request) use ($resource, $expectedData) {
            if ($request->method() !== 'POST') {
                return false;
            }

            if (! str_contains($request->url(), $resource)) {
                return false;
            }

            $data = $request->data();
            foreach ($expectedData as $key => $value) {
                if (! isset($data[$key]) || $data[$key] !== $value) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Assert that an entity was updated with specific data
     */
    public static function assertEntityUpdated(string $resource, int $id, array $expectedData): void
    {
        Http::assertSent(function ($request) use ($resource, $id, $expectedData) {
            if (! in_array($request->method(), ['PATCH', 'PUT'])) {
                return false;
            }

            if (! str_contains($request->url(), $resource.'('.$id.')')) {
                return false;
            }

            $data = $request->data();
            foreach ($expectedData as $key => $value) {
                if (! isset($data[$key]) || $data[$key] !== $value) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Assert that an entity was deleted
     */
    public static function assertEntityDeleted(string $resource, int $id): void
    {
        Http::assertSent(function ($request) use ($resource, $id) {
            return $request->method() === 'DELETE' &&
                   str_contains($request->url(), $resource.'('.$id.')');
        });
    }

    /**
     * Assert that a report was submitted with specific parameters
     */
    public static function assertReportSubmitted(string $reportName, array $expectedParams = []): void
    {
        Http::assertSent(function ($request) use ($reportName, $expectedParams) {
            if ($request->method() !== 'POST') {
                return false;
            }

            if (! str_contains($request->url(), $reportName)) {
                return false;
            }

            $data = $request->data();
            if (empty($expectedParams)) {
                return true;
            }

            foreach ($expectedParams as $key => $value) {
                if (! isset($data['parameters'][$key]) || $data['parameters'][$key] !== $value) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Assert that token was refreshed during the request
     */
    public static function assertTokenRefreshed(int $expectedTokenRequests = 2): void
    {
        $tokenRequests = 0;

        Http::assertSent(function ($request) use (&$tokenRequests) {
            if (str_contains($request->url(), 'oauth/token')) {
                $tokenRequests++;
            }

            return true;
        });

        Assert::assertEquals(
            $expectedTokenRequests,
            $tokenRequests,
            "Expected {$expectedTokenRequests} token requests, but got {$tokenRequests}"
        );
    }

    /**
     * Assert that a collection has specific structure
     */
    public static function assertCollectionStructure(array $collection, array $requiredKeys): void
    {
        Assert::assertNotEmpty($collection, 'Collection should not be empty');

        foreach ($collection as $item) {
            foreach ($requiredKeys as $key) {
                Assert::assertArrayHasKey(
                    $key,
                    $item,
                    "Collection item is missing required key: {$key}"
                );
            }
        }
    }

    /**
     * Assert that request has Bearer token
     */
    public static function assertRequestHasBearerToken(): void
    {
        Http::assertSent(function ($request) {
            $authHeader = $request->header('Authorization');

            return $authHeader && str_starts_with($authHeader[0], 'Bearer ');
        });
    }

    /**
     * Assert that request has specific header
     */
    public static function assertRequestHasHeader(string $header, string $value): void
    {
        Http::assertSent(function ($request) use ($header, $value) {
            return $request->hasHeader($header, $value);
        });
    }

    /**
     * Assert exact number of requests sent
     */
    public static function assertRequestCount(int $expected): void
    {
        Http::assertSentCount($expected);
    }

    /**
     * Assert model attributes match expected values
     */
    public static function assertModelAttributes($model, array $expectedAttributes): void
    {
        foreach ($expectedAttributes as $key => $value) {
            Assert::assertEquals(
                $value,
                $model->getAttribute($key),
                "Model attribute '{$key}' does not match expected value"
            );
        }
    }

    /**
     * Assert model is dirty
     */
    public static function assertModelIsDirty($model, ?string $attribute = null): void
    {
        Assert::assertTrue(
            $model->isDirty($attribute),
            $attribute
                ? "Model attribute '{$attribute}' should be dirty"
                : 'Model should be dirty'
        );
    }

    /**
     * Assert model is not dirty
     */
    public static function assertModelIsClean($model, ?string $attribute = null): void
    {
        Assert::assertFalse(
            $model->isDirty($attribute),
            $attribute
                ? "Model attribute '{$attribute}' should not be dirty"
                : 'Model should not be dirty'
        );
    }
}
