<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\Batch\MultipartDecoder;
use Contoweb\AbacusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class BatchResponseHelperTest extends TestCase
{
    #[Test]
    public function it_creates_valid_batch_response(): void
    {
        $responses = [
            ['Id' => 1, 'Name' => 'First'],
            ['Id' => 2, 'Name' => 'Second'],
        ];

        $boundary = 'batch_boundary';
        $parts = [];

        foreach ($responses as $index => $responseData) {
            $statusCode = 200;
            $statusText = 'OK';
            $json = json_encode($responseData);

            $part = "Content-Type: application/http\r\n";
            $part .= "Content-Transfer-Encoding: binary\r\n";
            $part .= "\r\n";
            $part .= "HTTP/1.1 {$statusCode} {$statusText}\r\n";
            $part .= "Content-Type: application/json\r\n";
            $part .= "\r\n";
            $part .= $json;

            $parts[] = $part;
        }

        $body = '--' . $boundary . "\r\n";
        $body .= implode("\r\n--" . $boundary . "\r\n", $parts);
        $body .= "\r\n--" . $boundary . "--\r\n";

        /* Test decoding */
        $results = MultipartDecoder::decode($body, $boundary);

        $this->assertCount(2, $results, "Should decode 2 parts. Raw body:\n" . $body);
        $this->assertEquals(200, $results[0]['status']);
        $this->assertEquals(['Id' => 1, 'Name' => 'First'], $results[0]['body']);
        $this->assertEquals(200, $results[1]['status']);
        $this->assertEquals(['Id' => 2, 'Name' => 'Second'], $results[1]['body']);
    }

    #[Test]
    public function it_creates_valid_batch_response_with_empty_body(): void
    {
        $responses = [null, null];

        $boundary = 'batch_boundary';
        $parts = [];

        foreach ($responses as $responseData) {
            $statusCode = 204;
            $statusText = 'No Content';
            $json = $responseData !== null ? json_encode($responseData) : '';

            $part = "Content-Type: application/http\r\n";
            $part .= "Content-Transfer-Encoding: binary\r\n";
            $part .= "\r\n";
            $part .= "HTTP/1.1 {$statusCode} {$statusText}\r\n";
            $part .= "Content-Type: application/json\r\n";
            $part .= "\r\n";
            $part .= $json;

            $parts[] = $part;
        }

        $body = '--' . $boundary . "\r\n";
        $body .= implode("\r\n--" . $boundary . "\r\n", $parts);
        $body .= "\r\n--" . $boundary . "--\r\n";

        /* Test decoding */
        $results = MultipartDecoder::decode($body, $boundary);

        $this->assertCount(2, $results, "Should decode 2 parts with empty body. Raw body:\n" . $body);
        $this->assertEquals(204, $results[0]['status']);
        $this->assertEquals(204, $results[1]['status']);
    }
}
