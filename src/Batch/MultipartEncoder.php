<?php

namespace Contoweb\AbacusApi\Batch;

class MultipartEncoder
{
    protected const BOUNDARY = 'batch_boundary';

    /**
     * Encode array of requests into multipart/mixed format
     *
     * @param  BatchRequestItem[]  $requests
     */
    public static function encode(array $requests): string
    {
        $parts = [];

        foreach ($requests as $request) {
            $parts[] = self::encodeRequest($request);
        }

        /* Join all parts with boundary and add final boundary */
        $body = '--'.self::BOUNDARY."\r\n";
        $body .= implode("\r\n--".self::BOUNDARY."\r\n", $parts);
        $body .= "\r\n--".self::BOUNDARY."--\r\n";

        return $body;
    }

    /**
     * Get the boundary string
     */
    public static function getBoundary(): string
    {
        return self::BOUNDARY;
    }

    /**
     * Get the full content-type header value
     */
    public static function getContentType(): string
    {
        return 'multipart/mixed; boundary='.self::BOUNDARY;
    }

    /**
     * Encode a single request into multipart format
     */
    protected static function encodeRequest(BatchRequestItem $request): string
    {
        $method = strtoupper($request->method);
        $path = $request->path;
        $body = $request->body ?? null;

        /* Start with multipart headers */
        $part = "Content-Type: application/http\r\n";
        $part .= "Content-Transfer-Encoding: binary\r\n";
        $part .= "\r\n";

        /* HTTP request line */
        $part .= "{$method} {$path} HTTP/1.1\r\n";

        /* Always add Content-Type header */
        $part .= "Content-Type: application/json\r\n";
        $part .= "\r\n";

        /* Add body if exists */
        if ($body !== null) {
            $part .= json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $part;
    }
}
