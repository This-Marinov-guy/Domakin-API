<?php

namespace App\Http\Middleware;

use App\Logging\Axiom\AxiomInfoLog;
use App\Logging\Axiom\AxiomPayloadFactory;
use App\Services\AxiomIngestService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Logs webhook requests to Axiom using the same req/res structure as API requests
 * (AxiomPayloadFactory + AxiomInfoLog), so they can be queried in Axiom with the same
 * schema. Use context.type = "webhook" or message = "Webhook request" to filter.
 */
class AxiomWebhookLoggerMiddleware
{
    public function __construct(
        private readonly AxiomIngestService $axiomIngest
    ) {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);
        $responseContent = $response->getContent();

        $reqPayload = AxiomPayloadFactory::request(
            method: $request->method(),
            url: $request->fullUrl(),
            headers: $this->sanitizeHeaders($request->headers->all()),
            body: $this->sanitizeRequestBody($request),
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            userId: $request->user()?->id,
        );

        $responseData = [
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'body' => $this->sanitizeResponseBody($responseContent),
        ];

        $resPayload = AxiomPayloadFactory::response(
            status: $responseData['status'],
            headers: $responseData['headers'],
            body: $responseData['body'],
            durationMs: defined('LARAVEL_START') ? (int) round((microtime(true) - LARAVEL_START) * 1000) : null,
        );

        $event = AxiomInfoLog::make(
            message: 'Webhook request',
            req: $reqPayload,
            res: $resPayload,
            context: [
                'type' => 'webhook',
                'webhook_path' => $request->path(),
            ],
        );

        if (!$this->axiomIngest->ingest($event) && !$this->isDevOrTesting()) {
            Log::warning('Axiom ingest skipped or failed for webhook request.');
        }

        return $response;
    }

    private function isDevOrTesting(): bool
    {
        $env = config('app.env', env('APP_ENV', 'prod'));
        return in_array($env, ['local', 'dev', 'testing'], true);
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['cookie', 'x-csrf-token', 'x-xsrf-token'];
        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['[REDACTED]'];
            }
        }
        return $headers;
    }

    /**
     * Field names whose values must never be logged in plaintext. Matched
     * case-insensitively against keys at any depth of the request/response body.
     */
    private const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'secret',
        'token',
        'api_key',
        'credit_card',
        'card_number',
        'cvv',
    ];

    private const MASK = '****';

    private function sanitizeRequestBody(Request $request): array|string
    {
        $input = $request->input();

        return is_array($input) ? $this->redactSensitive($input) : $input;
    }

    private function sanitizeResponseBody(?string $content): array|string
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
                return is_array($decoded) ? $this->redactSensitive($decoded) : $decoded;
            }
            return strlen($content) > 1000 ? substr($content, 0, 1000) . '... [truncated]' : $content;
        } catch (\Throwable) {
            return 'Failed to parse response body';
        }
    }

    /**
     * Recursively replace the values of sensitive keys with a mask, so secrets
     * (e.g. passwords, plaintext or hashed) are never sent to Axiom.
     */
    private function redactSensitive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_FIELDS, true)) {
                $data[$key] = self::MASK;
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redactSensitive($value);
            }
        }

        return $data;
    }
}
