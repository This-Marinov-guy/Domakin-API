<?php

namespace App\Logging\Axiom;

/**
 * Fixed response payload object. All response-related data grouped under one field.
 * Headers and body stored as JSON strings to avoid field explosion.
 */
final class AxiomResponsePayload
{
    public function __construct(
        public int $status,
        public ?int $duration_ms = null,
        /** @var string JSON-encoded headers (object) */
        public string $headers = '{}',
        /** @var string Response body - JSON string or truncated plain text */
        public string $body = '',
    ) {
    }

    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status,
            'duration_ms' => $this->duration_ms,
            'headers' => $this->headers,
            'body' => $this->body,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
