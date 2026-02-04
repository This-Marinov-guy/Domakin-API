<?php

namespace App\Logging\Axiom;

/**
 * Builds fixed req/res payloads from raw data. Encodes headers and body as JSON strings
 * so they count as single fields in Axiom.
 */
final class AxiomPayloadFactory
{
    private const MAX_BODY_LENGTH = 32_000;
    private const MAX_HEADER_VALUES = 20;

    public static function request(
        string $method,
        string $url,
        array $headers,
        mixed $body,
        ?string $ip = null,
        ?string $userAgent = null,
        ?int $userId = null,
    ): AxiomRequestPayload {
        $headersStr = self::encodeHeaders($headers);
        $bodyStr = self::encodeBody($body);
        return new AxiomRequestPayload(
            method: $method,
            url: $url,
            ip: $ip,
            user_agent: $userAgent !== null && $userAgent !== '' ? self::truncate($userAgent, 500) : null,
            user_id: $userId,
            headers: $headersStr,
            body: $bodyStr,
        );
    }

    public static function response(
        int $status,
        array $headers,
        mixed $body,
        ?int $durationMs = null,
    ): AxiomResponsePayload {
        $headersStr = self::encodeHeaders($headers);
        $bodyStr = self::encodeBody($body);
        return new AxiomResponsePayload(
            status: $status,
            duration_ms: $durationMs,
            headers: $headersStr,
            body: $bodyStr,
        );
    }

    private static function encodeHeaders(array $headers): string
    {
        $limited = array_slice($headers, 0, self::MAX_HEADER_VALUES, true);
        $normalized = [];
        foreach ($limited as $k => $v) {
            $normalized[$k] = is_array($v) ? implode(', ', array_slice($v, 0, 3)) : $v;
        }
        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return self::truncate($encoded, self::MAX_BODY_LENGTH);
    }

    private static function encodeBody(mixed $body): string
    {
        if ($body === null || $body === '') {
            return '';
        }
        if (is_array($body)) {
            $encoded = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            return self::truncate($encoded, self::MAX_BODY_LENGTH);
        }
        return self::truncate((string) $body, self::MAX_BODY_LENGTH);
    }

    private static function truncate(string $s, int $max): string
    {
        if (strlen($s) <= $max) {
            return $s;
        }
        return substr($s, 0, $max) . '...[truncated]';
    }
}
