<?php

namespace App\Services\Integrations;

use App\Services\ExternalRequestLogger;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GitHubActionsIntegrationService
{
    private const API_BASE_URL = 'https://api.github.com';
    private const DEFAULT_OWNER = 'This-Marinov-guy';
    private const DEFAULT_REPO = 'Domakin-v3';
    private const DEFAULT_EVENT_TYPE = 'update-sitemap';

    private ExternalRequestLogger $externalRequestLogger;
    private string $githubApiToken;
    private string $webhookToken;

    public function __construct(ExternalRequestLogger $externalRequestLogger)
    {
        $this->externalRequestLogger = $externalRequestLogger;

        // GitHub auth token used for Authorization header when calling GitHub API.
        // Supports classic or fine-grained tokens; we send it using the "token" scheme to match the curl example.
        $this->githubApiToken = (string) env('GITHUB_API_TOKEN', env('GITHUB_TOKEN', ''));

        // Token passed in client_payload to your workflow dispatch handler.
        $this->webhookToken = (string) env('GITHUB_WEBHOOK_TOKEN', '');

        if (empty($this->githubApiToken)) {
            Log::warning('GitHub Actions Integration: GitHub API token not configured (set GITHUB_API_TOKEN or GITHUB_TOKEN)');
        }

        if (empty($this->webhookToken)) {
            Log::warning('GitHub Actions Integration: webhook token not configured (set GITHUB_WEBHOOK_TOKEN)');
        }
    }

    /**
     * Trigger a GitHub Actions repository dispatch event (update sitemap).
     *
     * Mirrors:
     * curl -X POST \
     *  -H "Accept: application/vnd.github.v3+json" \
     *  -H "Authorization: token YOUR_GITHUB_TOKEN" \
     *  https://api.github.com/repos/{owner}/{repo}/dispatches \
     *  -d '{ "event_type": "update-sitemap", "client_payload": { "token": "YOUR_WEBHOOK_TOKEN" } }'
     *
     * @param string|null $owner
     * @param string|null $repo
     * @param string|null $eventType
     * @return array Response JSON (empty array when GitHub returns no content)
     * @throws Exception
     */
    public function triggerUpdateSitemap(?string $owner = null, ?string $repo = null, ?string $eventType = null): array
    {
        $owner = $owner ?: self::DEFAULT_OWNER;
        $repo = $repo ?: self::DEFAULT_REPO;
        $eventType = $eventType ?: self::DEFAULT_EVENT_TYPE;

        if (empty($this->githubApiToken)) {
            throw new Exception('GitHub API token is not configured (set GITHUB_API_TOKEN or GITHUB_TOKEN)');
        }

        if (empty($this->webhookToken)) {
            throw new Exception('GitHub webhook token is not configured (set GITHUB_WEBHOOK_TOKEN)');
        }

        $endpoint = self::API_BASE_URL . "/repos/{$owner}/{$repo}/dispatches";

        $requestHeaders = [
            'Accept' => 'application/vnd.github.v3+json',
            'Authorization' => 'token ' . $this->githubApiToken,
            'Content-Type' => 'application/json',
            // GitHub API strongly prefers a User-Agent header
            'User-Agent' => config('app.name', 'Domakin-API'),
        ];

        $payload = [
            'event_type' => $eventType,
            'client_payload' => [
                'token' => $this->webhookToken,
            ],
        ];

        try {
            $response = Http::withHeaders($requestHeaders)
                ->timeout(30)
                ->post($endpoint, $payload);

            $this->externalRequestLogger->log(
                'POST',
                $endpoint,
                $requestHeaders,
                $payload,
                $response,
                'GitHubActionsIntegrationService',
                [
                    'owner' => $owner,
                    'repo' => $repo,
                    'event_type' => $eventType,
                ]
            );

            if (!$response->successful()) {
                Log::error('GitHub Actions dispatch request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'owner' => $owner,
                    'repo' => $repo,
                    'event_type' => $eventType,
                ]);
                throw new Exception('GitHub Actions dispatch request failed: ' . $response->body());
            }

            // GitHub returns 204 No Content on success for repository_dispatch.
            return $response->json() ?? [];
        } catch (Exception $e) {
            Log::error('Error triggering GitHub Actions dispatch: ' . $e->getMessage(), [
                'owner' => $owner,
                'repo' => $repo,
                'event_type' => $eventType,
            ]);
            throw $e;
        }
    }
}

