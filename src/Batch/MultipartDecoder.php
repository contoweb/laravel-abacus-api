<?php

namespace Contoweb\AbacusApi\Batch;

use InvalidArgumentException;

class MultipartDecoder
{
    /**
     * Decode multipart/mixed response into array of results
     */
    public static function decode(string $responseBody, string $boundary): array
    {
        if (empty($boundary)) {
            throw new InvalidArgumentException('Boundary cannot be empty');
        }

        $results = [];

        $responseBody = str_replace("\r\n", "\n", $responseBody);

        /* Split by boundary */
        $delimiter = '--'.$boundary;
        $parts = explode($delimiter, $responseBody);

        foreach ($parts as $part) {
            /* Skip empty parts and closing boundary */
            $part = trim($part);

            if (empty($part) || $part === '--' || str_starts_with($part, '--')) {
                continue;
            }

            $result = self::parseResponsePart($part);

            if ($result !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Parse a single response part
     *
     * @return array{success: bool, status: int, headers: array, body: mixed, error: string|null}|null
     */
    protected static function parseResponsePart(string $part): ?array
    {
        /* Split multipart headers from HTTP response */
        $sections = preg_split("/\n\n/", $part, 3);

        if (count($sections) < 2) {
            return null;
        }

        $httpResponse = $sections[1];
        $httpBody = $sections[2] ?? null;

        /* Parse HTTP status line */
        $lines = explode("\n", trim($httpResponse));
        $statusLine = trim($lines[0]);

        if (! preg_match('/HTTP\/\d\.\d\s+(\d+)\s*(.*)/', $statusLine, $matches)) {
            return null;
        }

        $statusCode = (int) $matches[1];
        $statusText = trim($matches[2]);

        /* Parse headers */
        $headers = [];
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) {
                break;
            }

            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        /* Parse body */
        $body = trim($httpBody);
        $parsedBody = null;

        /* Try to decode JSON */
        if (! empty($body)) {
            $decoded = json_decode($body, true);
            $parsedBody = $decoded !== null ? $decoded : $body;
        }

        /* Determine success */
        $success = $statusCode >= 200 && $statusCode < 300;

        return [
            'success' => $success,
            'status' => $statusCode,
            'headers' => $headers,
            'body' => $parsedBody,
            'error' => $success ? null : $statusText,
        ];
    }
}
