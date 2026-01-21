<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\Batch\MultipartEncoder;
use Contoweb\AbacusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MultipartEncoderTest extends TestCase
{
    #[Test]
    public function it_encodes_single_get_request(): void
    {
        $requests = [
            [
                'method' => 'GET',
                'path' => '/api/entity/v1/mandants/9055/Products',
                'body' => null,
            ],
        ];

        $encoded = MultipartEncoder::encode($requests);

        $this->assertStringContainsString('--batch_boundary', $encoded);
        $this->assertStringContainsString('Content-Type: application/http', $encoded);
        $this->assertStringContainsString('Content-Transfer-Encoding: binary', $encoded);
        $this->assertStringContainsString('GET /api/entity/v1/mandants/9055/Products HTTP/1.1', $encoded);
        $this->assertStringContainsString('Content-Type: application/json', $encoded);
        $this->assertStringEndsWith("--batch_boundary--\r\n", $encoded);
    }

    #[Test]
    public function it_encodes_post_request_with_body(): void
    {
        $requests = [
            [
                'method' => 'POST',
                'path' => '/api/entity/v1/mandants/9055/FindProductPrice',
                'body' => [
                    'ProductPricingRequest' => [
                        'RequestKey' => 'test-123',
                        'Currency' => 'CHF',
                    ],
                ],
            ],
        ];

        $encoded = MultipartEncoder::encode($requests);

        $this->assertStringContainsString('POST /api/entity/v1/mandants/9055/FindProductPrice HTTP/1.1', $encoded);
        $this->assertStringContainsString('Content-Type: application/json', $encoded);
        $this->assertStringContainsString('"RequestKey":"test-123"', $encoded);
        $this->assertStringContainsString('"Currency":"CHF"', $encoded);
    }

    #[Test]
    public function it_encodes_multiple_requests(): void
    {
        $requests = [
            [
                'method' => 'GET',
                'path' => '/api/entity/v1/mandants/9055/Products',
                'body' => null,
            ],
            [
                'method' => 'POST',
                'path' => '/api/entity/v1/mandants/9055/Subjects',
                'body' => ['Name' => 'Test'],
            ],
        ];

        $encoded = MultipartEncoder::encode($requests);

        /* Check both requests are included */
        $this->assertStringContainsString('GET /api/entity/v1/mandants/9055/Products HTTP/1.1', $encoded);
        $this->assertStringContainsString('POST /api/entity/v1/mandants/9055/Subjects HTTP/1.1', $encoded);

        /* Count boundaries (should be 3: start, middle, end) */
        $boundaryCount = substr_count($encoded, '--batch_boundary');
        $this->assertEquals(3, $boundaryCount);
    }

    #[Test]
    public function it_returns_correct_boundary(): void
    {
        $boundary = MultipartEncoder::getBoundary();
        $this->assertEquals('batch_boundary', $boundary);
    }

    #[Test]
    public function it_returns_correct_content_type(): void
    {
        $contentType = MultipartEncoder::getContentType();
        $this->assertEquals('multipart/mixed; boundary=batch_boundary', $contentType);
    }
}
