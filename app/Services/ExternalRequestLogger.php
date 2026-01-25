<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalRequestLogger
{
    /**
     * Log external request to Axiom with "external" tag
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.)
     * @param string $url The full URL of the external request
     * @param array $headers Request headers
     * @param array|string $body Request body
     * @param \Illuminate\Http\Client\Response $response The HTTP response
     * @param string $serviceName Name of the service making the request (e.g., 'SignalIntegrationService')
     * @param array $additionalData Additional metadata to include in the log
     * @return void
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
        try {
            $axiomApiToken = config('axiom.api_token');
            $axiomDataset = config('axiom.dataset');

            if (empty($axiomApiToken) || empty($axiomDataset)) {
                return;
            }

            // Capture request data
            $requestData = [
                'method' => $method,
                'url' => $url,
                'headers' => $this->sanitizeHeaders($headers),
                'body' => $this->sanitizeRequestBody($body),
                'timestamp' => now()->toIso8601String(),
            ];

            // Capture response data
            $responseBody = $response->body();
            $responseData = [
                'status' => $response->status(),
                'headers' => $this->sanitizeHeaders($response->headers()),
                'body' => $this->sanitizeResponseBody($responseBody),
            ];

            // Combine request and response for logging
            $logData = array_merge([
                'request' => $requestData,
                'response' => $responseData,
                'application' => config('app.name'),
                'environment' => config('app.env'),
                'tag' => 'external',
                'service' => $serviceName,
            ], $additionalData);

            // Truncate all string values to 250 characters to avoid Axiom column limit
            $logData = $this->truncateStringsForAxiom($logData);
            
            // Limit and simplify structure to avoid exceeding Axiom's 257 column limit
            $logData = $this->limitFieldsForAxiom($logData);

            // Send to Axiom
            $this->sendToAxiom($logData, $axiomApiToken, $axiomDataset);
        } catch (\Exception $e) {
            Log::error('Error logging external request to Axiom: ' . $e->getMessage());
        }
    }

    /**
     * Sanitize headers to remove sensitive information.
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

        $sanitized = [];
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveHeaders)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = is_array($value) ? $value : [$value];
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize request body to remove sensitive information.
     *
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
            } catch (\Exception $e) {
                // If it's not JSON, return as is
            }
            return $body;
        }

        if (!is_array($body)) {
            return $body;
        }

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

        $sanitized = [];
        foreach ($body as $key => $value) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeRequestBody($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
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
                $maxSize = config('axiom.max_content_size', 1000000);
                if (strlen($content) > $maxSize) {
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
     * @param string $axiomApiToken
     * @param string $axiomDataset
     * @return void
     */
    protected function sendToAxiom(array $data, string $axiomApiToken, string $axiomDataset): void
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $axiomApiToken,
                'Content-Type' => 'application/json',
            ])->post("https://api.axiom.co/v1/datasets/{$axiomDataset}/ingest", [$data]);

            if (!$response->successful()) {
                Log::error('Failed to log external request to Axiom: ' . $response->body(), [
                    'status' => $response->status(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error sending external request logs to Axiom: ' . $e->getMessage());
        }
    }
}
