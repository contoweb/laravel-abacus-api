<?php

namespace Contoweb\AbacusApi\Tests\Unit;

use Contoweb\AbacusApi\Batch\MultipartDecoder;
use Contoweb\AbacusApi\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MultipartDecoderTest extends TestCase
{
    #[Test]
    public function it_decodes_simple_batch_response(): void
    {
        $response = "--batch_boundary\r\n".
            "Content-Type: application/http\r\n".
            "Content-Transfer-Encoding: binary\r\n".
            "\r\n".
            "HTTP/1.1 200 OK\r\n".
            "Content-Type: application/json\r\n".
            "\r\n".
            '{"Id":1,"Name":"Test"}'."\r\n".
            "--batch_boundary--\r\n";

        $results = MultipartDecoder::decode($response, 'batch_boundary');

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertEquals(200, $results[0]['status']);
        $this->assertEquals(['Id' => 1, 'Name' => 'Test'], $results[0]['body']);
    }

    #[Test]
    public function it_decodes_multiple_parts(): void
    {
        $response = "--batch_boundary\r\n".
            "Content-Type: application/http\r\n".
            "Content-Transfer-Encoding: binary\r\n".
            "\r\n".
            "HTTP/1.1 200 OK\r\n".
            "Content-Type: application/json\r\n".
            "\r\n".
            '{"Id":1}'."\r\n".
            "--batch_boundary\r\n".
            "Content-Type: application/http\r\n".
            "Content-Transfer-Encoding: binary\r\n".
            "\r\n".
            "HTTP/1.1 201 Created\r\n".
            "Content-Type: application/json\r\n".
            "\r\n".
            '{"Id":2}'."\r\n".
            "--batch_boundary--\r\n";

        $results = MultipartDecoder::decode($response, 'batch_boundary');

        $this->assertCount(2, $results);
        $this->assertEquals(200, $results[0]['status']);
        $this->assertEquals(201, $results[1]['status']);
    }

    #[Test]
    public function it_handles_empty_body(): void
    {
        $response = "--batch_boundary\r\n".
            "Content-Type: application/http\r\n".
            "Content-Transfer-Encoding: binary\r\n".
            "\r\n".
            "HTTP/1.1 204 No Content\r\n".
            "Content-Type: application/json\r\n".
            "\r\n".
            "\r\n".
            "--batch_boundary--\r\n";

        $results = MultipartDecoder::decode($response, 'batch_boundary');

        $this->assertCount(1, $results);
        $this->assertEquals(204, $results[0]['status']);
        $this->assertNull($results[0]['body']);
    }

    #[Test]
    public function it_handles_error_responses(): void
    {
        $response = "--batch_boundary\r\n".
            "Content-Type: application/http\r\n".
            "Content-Transfer-Encoding: binary\r\n".
            "\r\n".
            "HTTP/1.1 400 Bad Request\r\n".
            "Content-Type: application/json\r\n".
            "\r\n".
            '{"error":"Invalid data"}'."\r\n".
            "--batch_boundary--\r\n";

        $results = MultipartDecoder::decode($response, 'batch_boundary');

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertEquals(400, $results[0]['status']);
        $this->assertEquals('Bad Request', $results[0]['error']);
    }
}
