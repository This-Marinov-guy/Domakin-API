<?php

namespace App\Services;

use App\Logging\Axiom\AxiomLogEvent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends structured Axiom log events to Axiom ingest API.
 * Uses fixed-field models so payloads never exceed Axiom's column limit.
 */
class AxiomIngestService
{
    private const INGEST_URL_TEMPLATE = 'https://api.axiom.co/v1/datasets/%s/ingest';

    public function ingest(AxiomLogEvent $event): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $token = config('services.axiom.api_token');
        $dataset = config('services.axiom.dataset');

        if (empty($token) || empty($dataset)) {
            Log::warning('Axiom API token or dataset not configured. Skipping ingest.');
            return false;
        }

        try {
            $payload = $event->toAxiomPayload();
            $url = sprintf(self::INGEST_URL_TEMPLATE, $dataset);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ])->post($url, [$payload]);

            if (!$response->successful()) {
                Log::error('Axiom ingest failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Axiom ingest error: ' . $e->getMessage());
            return false;
        }
    }

    public function isEnabled(): bool
    {
        return (bool) config('axiom.enabled', config('services.axiom.enabled', true));
    }
}
