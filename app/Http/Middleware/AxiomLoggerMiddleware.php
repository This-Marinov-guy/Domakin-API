<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AxiomLoggerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip logging for GET requests
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        // Process the request
        $response = $next($request);

        // Clone the response to avoid issues with streaming responses
        $responseContent = $response->getContent();

        // Capture request data
        $requestData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'body' => $this->sanitizeRequestBody($request),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ];

        // Add user ID if authenticated
        if ($request->user()) {
            $requestData['user_id'] = $request->user()->id;
        }

        // Capture response data
        $responseData = [
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
            'body' => $this->sanitizeResponseBody($responseContent),
            'processing_time_ms' => defined('LARAVEL_START') ? round((microtime(true) - LARAVEL_START) * 1000) : null,
        ];

        // Combine request and response for logging
        $logData = [
            'request' => $requestData,
            'response' => $responseData,
            'application' => config('app.name'),
            'environment' => config('app.env'),
        ];

        // Send to Axiom
        $this->sendToAxiom($logData);

        return $response;
    }

    /**
     * Sanitize request headers to remove sensitive information.
     *
     * @param array $headers
     * @return array
     */
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

    /**
     * Sanitize request body to remove sensitive information.
     *
     * @param Request $request
     * @return array|string
     */
    protected function sanitizeRequestBody(Request $request)
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

    /**
     * Sanitize response body - try to parse JSON or return as string.
     *
     * @param string $content
     * @return array|string
     */
    protected function sanitizeResponseBody($content)
    {
        if (empty($content)) {
            return '';
        }

        try {
            // Try to decode JSON
            $decoded = json_decode($content, true);

            // If it's valid JSON, return the decoded version
            if (json_last_error() === JSON_ERROR_NONE) {
                // If the response is too large, truncate it
                if (strlen($content) > 1000000) {
                    return [
                        'message' => 'Response content too large to log (truncated)',
                        'size' => strlen($content)
                    ];
                }
                return $decoded;
            }

            // Not valid JSON, return as string with length limit
            if (strlen($content) > 1000) {
                return substr($content, 0, 1000) . '... [truncated]';
            }

            return $content;
        } catch (\Exception $e) {
            return 'Failed to parse response body: ' . $e->getMessage();
        }
    }

    /**
     * Send the log data to Axiom.
     *
     * @param array $data
     * @return void
     */
    protected function sendToAxiom(array $data)
    {
        try {
            $axiomApiToken = config('services.axiom.api_token');
            $axiomDataset = config('services.axiom.dataset');

            if (empty($axiomApiToken) || empty($axiomDataset)) {
                Log::warning('Axiom API token or dataset not configured. Skipping API request logging.');
                return;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $axiomApiToken,
                'Content-Type' => 'application/json',
            ])->post("https://api.axiom.co/v1/datasets/{$axiomDataset}/ingest", [$data]);

            if (!$response->successful()) {
                Log::error('Failed to log to Axiom: ' . $response->body(), [
                    'status' => $response->status(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending logs to Axiom: ' . $e->getMessage());
        }
    }
}
