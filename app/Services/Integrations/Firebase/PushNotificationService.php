<?php

declare(strict_types=1);

namespace App\Services\Integrations\Firebase;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private string $projectId;
    private string $clientEmail;
    private string $privateKey;

    private ?string $cachedToken = null;
    private int $tokenExpiry = 0;

    public function __construct()
    {
        $credentialsPath = base_path('google-credentials.orange-sa-key.json');

        if (!file_exists($credentialsPath)) {
            throw new \Exception('Google credentials file not found at: ' . $credentialsPath);
        }

        $credentials = json_decode(
            file_get_contents($credentialsPath),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->projectId   = $credentials['project_id'];
        $this->clientEmail = $credentials['client_email'];
        $this->privateKey  = $credentials['private_key'];
    }

    /**
     * Send a push notification to a single FCM token.
     */
    public function sendToToken(string $fcmToken, string $title, string $body, array $data = []): void
    {
        $accessToken = $this->getAccessToken();

        try {
            $this->deliver($accessToken, $fcmToken, $title, $body, $data);
        } catch (\Throwable $e) {
            Log::error('PushNotificationService: failed to send notification', [
                'fcm_token' => $fcmToken,
                'title'     => $title,
                'error'     => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send a push notification to multiple FCM tokens.
     * FCM v1 has no batch endpoint, so we loop and skip failed tokens.
     */
    public function sendToMultipleTokens(array $fcmTokens, string $title, string $body, array $data = []): void
    {
        $accessToken = $this->getAccessToken();

        foreach ($fcmTokens as $token) {
            try {
                $this->deliver($accessToken, $token, $title, $body, $data);
            } catch (\Throwable $e) {
                Log::error('PushNotificationService: failed to send to token', [
                    'fcm_token' => $token,
                    'title'     => $title,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }

    private function deliver(string $accessToken, string $fcmToken, string $title, string $body, array $data): void
    {
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $payload = [
            'message' => [
                'token' => $fcmToken,
                'notification' => [
                    'title' => $title,
                    'body'  => $body,
                ],
            ],
        ];

        // FCM v1 data values must all be strings
        if (!empty($data)) {
            $payload['message']['data'] = array_map('strval', $data);
        }

        $response = Http::withToken($accessToken)->post($url, $payload);

        if (!$response->successful()) {
            throw new \RuntimeException('FCM request failed (' . $response->status() . '): ' . $response->body());
        }
    }

    /**
     * Get a Google OAuth2 access token for the firebase.messaging scope,
     * using the service account credentials and firebase/php-jwt.
     */
    private function getAccessToken(): string
    {
        if ($this->cachedToken && time() < $this->tokenExpiry) {
            return $this->cachedToken;
        }

        $now = time();

        $jwt = JWT::encode([
            'iss'   => $this->clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ], $this->privateKey, 'RS256');

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion'  => $jwt,
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to obtain FCM access token: ' . $response->body());
        }

        // Cache for slightly less than the 1-hour expiry
        $this->cachedToken = $response->json('access_token');
        $this->tokenExpiry = $now + 3500;

        return $this->cachedToken;
    }
}
