<?php

namespace App\Services;

use App\Logging\Axiom\AxiomInfoLog;
use App\Logging\Axiom\AxiomPayloadFactory;
use Illuminate\Support\Facades\Log;

/**
 * Logs external HTTP requests to Axiom using fixed-field models.
 */
class ExternalRequestLogger
{
    public function __construct(
        private readonly AxiomIngestService $axiomIngest
    ) {
    }

    /**
     * Log external request to Axiom with fixed-field structure.
     *
     * @param string $method
     * @param string $url
     * @param array<string, mixed> $headers
     * @param array|string $body
     * @param \Illuminate\Http\Client\Response $response
     * @param string $serviceName
     * @param array<string, mixed> $additionalData
     */
    public function log(
        string $method,
        string $url,
        array $headers,
        $body,
        $response,
        string $serviceName,
        array $additionalData = []
    ): void {
        if (!$this->axiomIngest->isEnabled()) {
            return;
        }

        try {
            $sanitizedHeaders = $this->sanitizeHeaders($headers);
            $sanitizedBody = $this->sanitizeRequestBody($body);
            $responseBody = $this->sanitizeResponseBody($response->body());

            $reqPayload = AxiomPayloadFactory::request(
                method: $method,
                url: $url,
                headers: $sanitizedHeaders,
                body: $sanitizedBody,
            );

            $resPayload = AxiomPayloadFactory::response(
                status: $response->status(),
                headers: $response->headers(),
                body: $responseBody,
            );

            $context = array_merge(
                ['tag' => 'external', 'service' => $serviceName],
                $additionalData
            );

            $event = AxiomInfoLog::make(
                message: 'External request: ' . $serviceName,
                req: $reqPayload,
                res: $resPayload,
                context: $context,
            );

            $this->axiomIngest->ingest($event);
        } catch (\Throwable $e) {
            Log::error('Error logging external request to Axiom: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, mixed>
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'cookie', 'x-api-key', 'x-csrf-token', 'x-xsrf-token'];
        $out = [];
        foreach ($headers as $key => $value) {
            $lower = strtolower($key);
            $out[$key] = in_array($lower, $sensitive) ? '[REDACTED]' : (is_array($value) ? $value : [$value]);
        }
        return $out;
    }

    /**
     * @param array|string $body
     * @return array|string
     */
    protected function sanitizeRequestBody($body)
    {
        if (is_string($body)) {
            try {
                $decoded = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $this->sanitizeRequestBody($decoded);
                }
            } catch (\Throwable) {
            }
            return $body;
        }
        if (!is_array($body)) {
            return $body;
        }
        $sensitive = ['password', 'password_confirmation', 'current_password', 'secret', 'token', 'api_key', 'credit_card', 'card_number', 'cvv'];
        $out = [];
        foreach ($body as $key => $value) {
            $out[$key] = in_array(strtolower($key), $sensitive) ? '[REDACTED]' : (is_array($value) ? $this->sanitizeRequestBody($value) : $value);
        }
        return $out;
    }

    protected function sanitizeResponseBody(?string $content): array|string
    {
        if (empty($content)) {
            return '';
        }
        try {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $maxSize = config('axiom.max_content_size', config('services.axiom.max_content_size', 1_000_000));
                if (strlen($content) > $maxSize) {
                    return ['_truncated' => true, 'size' => strlen($content)];
                }
                return $decoded;
            }
            return strlen($content) > 1000 ? substr($content, 0, 1000) . '... [truncated]' : $content;
        } catch (\Throwable) {
            return 'Failed to parse response body';
        }
    }

}
