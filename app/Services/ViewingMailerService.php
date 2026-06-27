<?php

namespace App\Services;

use App\Models\Viewing;
use Exception;
use Illuminate\Support\Facades\Log;

class ViewingMailerService
{
    public function __construct(
        private MailerApiService $mailerApi
    ) {
    }

    public function sendRegisteredViewing(Viewing $viewing, string $locale = 'en'): void
    {
        try {
            $this->mailerApi->post('/viewing/send-registered-viewing', [
                'email' => $viewing->email,
                'id' => (string) $viewing->id,
                'language' => $this->normalizeLocale($locale),
                'name' => trim(($viewing->name ?? '') . ' ' . ($viewing->surname ?? '')),
                'city' => $viewing->city ?? '',
                'address' => $viewing->address ?? '',
                'date' => $viewing->date ?? '',
                'time' => $viewing->time ?? '',
                'link' => $this->buildInfoMailLink($viewing),
            ]);
        } catch (Exception $error) {
            Log::error('Mailer send-registered-viewing failed', [
                'viewing_id' => $viewing->id,
                'email' => $viewing->email,
                'locale' => $locale,
                'error' => $error->getMessage(),
            ]);

            throw $error;
        }
    }

    private function normalizeLocale(string $locale): string
    {
        return str_starts_with(strtolower($locale), 'bg') ? 'bg' : 'en';
    }

    private function buildInfoMailLink(Viewing $viewing): string
    {
        $subject = sprintf(
            'Viewing %s %s - %s automation',
            trim((string) $viewing->date),
            trim((string) $viewing->time),
            trim((string) $viewing->address)
        );

        return 'mailto:info@domakin.nl?' . http_build_query([
            'subject' => trim(preg_replace('/\s+/', ' ', $subject) ?? $subject),
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
