<?php

namespace App\Logging\Axiom;

/**
 * Fixed request payload object. All request-related data grouped under one field.
 * Headers and body stored as JSON strings to avoid field explosion.
 */
final class AxiomRequestPayload
{
    public function __construct(
        public string $method,
        public string $url,
        public ?string $ip = null,
        public ?string $user_agent = null,
        public ?int $user_id = null,
        /** @var string JSON-encoded headers (object) */
        public string $headers = '{}',
        /** @var string Request body - JSON string or truncated plain text */
        public string $body = '',
    ) {
    }

    public function toArray(): array
    {
        return array_filter([
            'method' => $this->method,
            'url' => $this->url,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'user_id' => $this->user_id,
            'headers' => $this->headers,
            'body' => $this->body,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
