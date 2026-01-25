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

        // Truncate all string values to 250 characters to avoid Axiom column limit
        $logData = $this->truncateStringsForAxiom($logData);
        
        // Limit and simplify structure to avoid exceeding Axiom's 257 column limit
        $logData = $this->limitFieldsForAxiom($logData);

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
     * Recursively truncate all string values to 250 characters to avoid Axiom column limit.
     *
     * @param mixed $data
     * @return mixed
     */
    protected function truncateStringsForAxiom($data)
    {
        if (is_string($data)) {
            return strlen($data) > 250 ? substr($data, 0, 250) . '... [truncated]' : $data;
        }

        if (is_array($data)) {
            $truncated = [];
            foreach ($data as $key => $value) {
                $truncated[$key] = $this->truncateStringsForAxiom($value);
            }
            return $truncated;
        }

        return $data;
    }

    /**
     * Limit and simplify data structure to avoid exceeding Axiom's 257 column limit.
     * Limits array sizes, reduces nesting depth, and combines fields where possible.
     *
     * @param array $data
     * @param int $maxDepth Maximum nesting depth (default: 3)
     * @param int $maxArraySize Maximum items in arrays (default: 10)
     * @param int $currentDepth Current depth (internal use)
     * @return array
     */
    protected function limitFieldsForAxiom(array $data, int $maxDepth = 3, int $maxArraySize = 10, int $currentDepth = 0): array
    {
        if ($currentDepth >= $maxDepth) {
            return ['[truncated: max depth reached]'];
        }

        $limited = [];
        $fieldCount = 0;
        $maxFields = 200; // Leave room for Axiom's 257 limit

        foreach ($data as $key => $value) {
            if ($fieldCount >= $maxFields) {
                $limited['_truncated_fields'] = count($data) - $fieldCount . ' more fields were truncated';
                break;
            }

            if (is_array($value)) {
                // For headers arrays, limit to most important ones
                if ($key === 'headers' && is_array($value)) {
                    $importantHeaders = ['content-type', 'user-agent', 'accept', 'authorization', 'x-requested-with'];
                    $limitedHeaders = [];
                    $headerCount = 0;
                    
                    foreach ($value as $headerKey => $headerValue) {
                        $lowerKey = strtolower($headerKey);
                        // Always include important headers
                        if (in_array($lowerKey, $importantHeaders) || $headerCount < $maxArraySize) {
                            $limitedHeaders[$headerKey] = is_array($headerValue) 
                                ? (count($headerValue) > 1 ? implode(', ', array_slice($headerValue, 0, 3)) : ($headerValue[0] ?? ''))
                                : $headerValue;
                            $headerCount++;
                        }
                    }
                    
                    if (count($value) > $headerCount) {
                        $limitedHeaders['_other_headers_count'] = count($value) - $headerCount;
                    }
                    
                    $limited[$key] = $limitedHeaders;
                } else {
                    // Limit array size
                    if (count($value) > $maxArraySize) {
                        $limited[$key] = array_slice($value, 0, $maxArraySize);
                        $limited[$key]['_truncated_items'] = count($value) - $maxArraySize;
                    } else {
                        $limited[$key] = $this->limitFieldsForAxiom($value, $maxDepth, $maxArraySize, $currentDepth + 1);
                    }
                }
            } else {
                $limited[$key] = $value;
            }

            $fieldCount++;
        }

        return $limited;
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
