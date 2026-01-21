<?php

namespace Contoweb\AbacusApi\Batch;

class MultipartDecoder
{
    /**
     * Decode multipart/mixed response into array of results
     *
     * @param string $responseBody
     * @param string $boundary
     * @return array
     */
    public static function decode(string $responseBody, string $boundary): array
    {
        $results = [];

        /* Split by boundary */
        $parts = explode('--' . $boundary, $responseBody);

        foreach ($parts as $part) {
            /* Skip empty parts and closing boundary */
            $part = trim($part);
            if (empty($part) || $part === '--') {
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
        $sections = preg_split("/\r?\n\r?\n/", $part, 3);

        if (count($sections) < 2) {
            return null;
        }

        $httpResponse = $sections[1] ?? '';
        $httpBody = $sections[2] ?? '';

        /* Parse HTTP status line */
        $lines = explode("\n", trim($httpResponse));
        $statusLine = trim($lines[0]);

        if (!preg_match('/HTTP\/\d\.\d\s+(\d+)\s*(.*)/', $statusLine, $matches)) {
            return null;
        }

        $statusCode = (int) $matches[1];
        $statusText = $matches[2] ?? '';

        /* Parse headers */
        $headers = [];
        for ($i = 1; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) {
                break;
            }

            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }

        /* Parse body */
        $body = trim($httpBody);
        $parsedBody = null;

        /* Try to decode JSON */
        if (!empty($body)) {
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
