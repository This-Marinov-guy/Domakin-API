<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MailerApiService
{
    private string $baseUrl;

    public function __construct()
    {
        $base = env('MAILER_API_URL');
        if (empty($base)) {
            throw new Exception('MAILER_API_URL is not configured.');
        }

        $this->baseUrl = rtrim($base, '/');
    }

    /**
     * Send a JSON POST request to the mailer API.
     *
     * @param string $path Relative path (e.g. '/send') or empty string for base URL.
     * @param array<string,mixed> $payload JSON payload to send.
     * @return array<string,mixed>
     * @throws Exception when the HTTP request fails.
     */
    public function post(string $path, array $payload = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');

        $response = Http::asJson()->post($url, $payload);

        if (! $response->successful()) {
            Log::error('Mailer API request failed', [
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new Exception('Mailer API request failed with status ' . $response->status());
        }

        return $response->json() ?? [];
    }
}

