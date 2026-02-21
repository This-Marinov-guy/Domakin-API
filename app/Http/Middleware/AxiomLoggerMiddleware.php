<?php

namespace App\Http\Middleware;

use App\Logging\Axiom\AxiomInfoLog;
use App\Logging\Axiom\AxiomPayloadFactory;
use App\Services\AxiomIngestService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AxiomLoggerMiddleware
{
    public function __construct(
        private readonly AxiomIngestService $axiomIngest
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET') && str_contains((string) $request->headers->get('origin'), 'domakin.nl')) {
            return $next($request);
        }

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
            message: 'API request',
            req: $reqPayload,
            res: $resPayload,
            context: [],
        );

        if (!$this->axiomIngest->ingest($event)) {
            Log::warning('Axiom ingest skipped or failed for API request.');
        }

        return $response;
    }

    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'cookie',
            'x-api-key',
            'x-csrf-token',
            'x-xsrf-token',
        ];

        foreach ($sensitiveHeaders as $header) {
            if (isset($headers[$header])) {
                $headers[$header] = ['[REDACTED]'];
            }
        }

        return $headers;
    }

    protected function sanitizeRequestBody(Request $request): array|string
    {
        $sensitiveFields = [
            'password',
            'password_confirmation',
            'current_password',
            'secret',
            'token',
            'api_key',
            'credit_card',
            'card_number',
            'cvv',
        ];

        $input = $request->input();
        if (is_array($input)) {
            foreach ($sensitiveFields as $field) {
                if (isset($input[$field])) {
                    $input[$field] = '[REDACTED]';
                }
            }
        }

        return $input;
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
