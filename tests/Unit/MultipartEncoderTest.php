<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\Batch\BatchRequestItem;
use Contoweb\AbacusApi\Batch\MultipartEncoder;
use Contoweb\AbacusApi\Tests\Fixtures\TestSubject;
use Contoweb\AbacusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MultipartEncoderTest extends TestCase
{
    #[Test]
    public function it_encodes_single_get_request(): void
    {
        $item = new BatchRequestItem(
            TestSubject::class,
            'GET',
            '/api/entity/v1/mandants/test-mandate/Products',
            null
        );

        $encoded = MultipartEncoder::encode([$item]);

        $this->assertStringContainsString('--batch_boundary', $encoded);
        $this->assertStringContainsString('Content-Type: application/http', $encoded);
        $this->assertStringContainsString('Content-Transfer-Encoding: binary', $encoded);
        $this->assertStringContainsString('GET /api/entity/v1/mandants/test-mandate/Products HTTP/1.1', $encoded);
        $this->assertStringContainsString('Content-Type: application/json', $encoded);
        $this->assertStringEndsWith("--batch_boundary--\r\n", $encoded);
    }

    #[Test]
    public function it_encodes_post_request_with_body(): void
    {
        $item = new BatchRequestItem(
            TestSubject::class,
            'POST',
            '/api/entity/v1/mandants/test-mandate/FindProductPrice',
            [
                'ProductPricingRequest' => [
                    'RequestKey' => 'test-123',
                    'Currency' => 'CHF',
                ],
            ]
        );

        $encoded = MultipartEncoder::encode([$item]);

        $this->assertStringContainsString('POST /api/entity/v1/mandants/test-mandate/FindProductPrice HTTP/1.1', $encoded);
        $this->assertStringContainsString('Content-Type: application/json', $encoded);
        $this->assertStringContainsString('"RequestKey":"test-123"', $encoded);
        $this->assertStringContainsString('"Currency":"CHF"', $encoded);
    }

    #[Test]
    public function it_encodes_patch_request_with_body(): void
    {
        $item = new BatchRequestItem(
            TestSubject::class,
            'PATCH',
            '/api/entity/v1/mandants/test-mandate/Subjects(123)',
            ['FirstName' => 'Updated', 'LastName' => 'Name']
        );

        $encoded = MultipartEncoder::encode([$item]);

        $this->assertStringContainsString('PATCH /api/entity/v1/mandants/test-mandate/Subjects(123) HTTP/1.1', $encoded);
        $this->assertStringContainsString('"FirstName":"Updated"', $encoded);
        $this->assertStringContainsString('"LastName":"Name"', $encoded);
    }

    #[Test]
    public function it_encodes_delete_request_without_body(): void
    {
        $item = new BatchRequestItem(
            TestSubject::class,
            'DELETE',
            '/api/entity/v1/mandants/test-mandate/Subjects(456)',
            null
        );

        $encoded = MultipartEncoder::encode([$item]);

        $this->assertStringContainsString('DELETE /api/entity/v1/mandants/test-mandate/Subjects(456) HTTP/1.1', $encoded);
        $this->assertStringContainsString('Content-Type: application/json', $encoded);
        
        /* DELETE should not have a body */
        $this->assertStringNotContainsString('{', $encoded);
    }

    #[Test]
    public function it_encodes_multiple_requests(): void
    {
        $item1 = new BatchRequestItem(
            TestSubject::class,
            'GET',
            '/api/entity/v1/mandants/test-mandate/Products',
            null
        );

        $item2 = new BatchRequestItem(
            TestSubject::class,
            'POST',
            '/api/entity/v1/mandants/test-mandate/Subjects',
            ['FirstName' => 'Test', 'LastName' => 'User']
        );

        $item3 = new BatchRequestItem(
            TestSubject::class,
            'PATCH',
            '/api/entity/v1/mandants/test-mandate/Subjects(100)',
            ['FirstName' => 'Updated']
        );

        $encoded = MultipartEncoder::encode([$item1, $item2, $item3]);

        /* Check all requests are included */
        $this->assertStringContainsString('GET /api/entity/v1/mandants/test-mandate/Products HTTP/1.1', $encoded);
        $this->assertStringContainsString('POST /api/entity/v1/mandants/test-mandate/Subjects HTTP/1.1', $encoded);
        $this->assertStringContainsString('PATCH /api/entity/v1/mandants/test-mandate/Subjects(100) HTTP/1.1', $encoded);

        /* Count boundaries (should be 4: start + 2 middle + end) */
        $boundaryCount = substr_count($encoded, '--batch_boundary');
        $this->assertEquals(4, $boundaryCount);
    }

    #[Test]
    public function it_encodes_requests_in_correct_order(): void
    {
        $item1 = new BatchRequestItem(TestSubject::class, 'GET', '/path1', null);
        $item2 = new BatchRequestItem(TestSubject::class, 'POST', '/path2', ['data' => 'test']);
        $item3 = new BatchRequestItem(TestSubject::class, 'DELETE', '/path3', null);

        $encoded = MultipartEncoder::encode([$item1, $item2, $item3]);

        /* Extract positions */
        $pos1 = strpos($encoded, 'GET /path1');
        $pos2 = strpos($encoded, 'POST /path2');
        $pos3 = strpos($encoded, 'DELETE /path3');

        /* Verify order */
        $this->assertLessThan($pos2, $pos1, 'GET should come before POST');
        $this->assertLessThan($pos3, $pos2, 'POST should come before DELETE');
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

    #[Test]
    public function it_uses_uppercase_http_methods(): void
    {
        $item1 = new BatchRequestItem(TestSubject::class, 'get', '/test1', null);
        $item2 = new BatchRequestItem(TestSubject::class, 'Post', '/test2', null);
        $item3 = new BatchRequestItem(TestSubject::class, 'patch', '/test3', null);

        $encoded = MultipartEncoder::encode([$item1, $item2, $item3]);

        $this->assertStringContainsString('GET /test1 HTTP/1.1', $encoded);
        $this->assertStringContainsString('POST /test2 HTTP/1.1', $encoded);
        $this->assertStringContainsString('PATCH /test3 HTTP/1.1', $encoded);
    }

    #[Test]
    public function it_encodes_json_with_correct_flags(): void
    {
        $item = new BatchRequestItem(
            TestSubject::class,
            'POST',
            '/test',
            [
                'url' => 'https://example.com/path',
                'text' => 'Hello 世界',
            ]
        );

        $encoded = MultipartEncoder::encode([$item]);

        /* JSON_UNESCAPED_SLASHES should keep slashes */
        $this->assertStringContainsString('https://example.com/path', $encoded);
        $this->assertStringNotContainsString('https:\\/\\/example.com\\/path', $encoded);

        /* JSON_UNESCAPED_UNICODE should keep unicode characters */
        $this->assertStringContainsString('世界', $encoded);
    }

    #[Test]
    public function it_handles_empty_array_of_requests(): void
    {
        $encoded = MultipartEncoder::encode([]);

        /* Should have boundaries but no content between them */
        $this->assertStringContainsString('--batch_boundary', $encoded);
        $this->assertStringEndsWith("--batch_boundary--\r\n", $encoded);
        
        /* Should only have 2 boundaries: start and end */
        $boundaryCount = substr_count($encoded, '--batch_boundary');
        $this->assertEquals(2, $boundaryCount);
    }

    #[Test]
    public function it_encodes_request_with_complex_json_body(): void
    {
        $item = new BatchRequestItem(
            TestSubject::class,
            'POST',
            '/api/test',
            [
                'nested' => [
                    'array' => [1, 2, 3],
                    'object' => ['key' => 'value'],
                ],
                'boolean' => true,
                'null' => null,
                'number' => 42.5,
            ]
        );

        $encoded = MultipartEncoder::encode([$item]);

        $this->assertStringContainsString('"nested":', $encoded);
        $this->assertStringContainsString('"array":[1,2,3]', $encoded);
        $this->assertStringContainsString('"boolean":true', $encoded);
        $this->assertStringContainsString('"null":null', $encoded);
        $this->assertStringContainsString('"number":42.5', $encoded);
    }

    #[Test]
    public function it_includes_all_required_multipart_headers(): void
    {
        $item = new BatchRequestItem(
            TestSubject::class,
            'GET',
            '/test',
            null
        );

        $encoded = MultipartEncoder::encode([$item]);

        /* Check for all required headers */
        $this->assertStringContainsString('Content-Type: application/http', $encoded);
        $this->assertStringContainsString('Content-Transfer-Encoding: binary', $encoded);
        $this->assertStringContainsString('Content-Type: application/json', $encoded);
    }

    #[Test]
    public function it_uses_correct_line_endings(): void
    {
        $item = new BatchRequestItem(TestSubject::class, 'GET', '/test', null);
        $encoded = MultipartEncoder::encode([$item]);

        /* Should use \r\n (CRLF) for HTTP protocol */
        $this->assertStringContainsString("\r\n", $encoded);
        
        /* Should not use just \n */
        $withoutCR = str_replace("\r\n", "\n", $encoded);
        $this->assertNotEquals($encoded, $withoutCR);
    }
}
