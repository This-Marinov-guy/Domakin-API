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

    public function sendApprovedViewing(Viewing $viewing, ?string $locale = null): void
    {
        $this->sendViewingStatusEmail(
            '/viewing/send-approved-viewing',
            'Mailer send-approved-viewing failed',
            $viewing,
            $locale,
        );
    }

    public function sendRejectedViewing(Viewing $viewing, ?string $reason = null, ?string $locale = null): void
    {
        $this->sendViewingStatusEmail(
            '/viewing/send-rejected-viewing',
            'Mailer send-rejected-viewing failed',
            $viewing,
            $locale,
            ['reason' => $reason ?? ''],
        );
    }

    public function sendApprovedViewingEmail(string $email, string $locale = 'en', ?string $id = null): void
    {
        $this->sendDirectViewingEmail('/viewing/send-approved-viewing', $email, $locale, $id);
    }

    public function sendRejectedViewingEmail(string $email, string $locale = 'en', ?string $id = null, ?string $reason = null): void
    {
        $this->sendDirectViewingEmail('/viewing/send-rejected-viewing', $email, $locale, $id, [
            'reason' => $reason ?? '',
        ]);
    }

    private function normalizeLocale(string $locale): string
    {
        return str_starts_with(strtolower($locale), 'bg') ? 'bg' : 'en';
    }

    /**
     * @param array<string,mixed> $extra
     */
    private function sendViewingStatusEmail(
        string $path,
        string $logMessage,
        Viewing $viewing,
        ?string $locale = null,
        array $extra = [],
    ): void {
        try {
            $this->mailerApi->post($path, array_merge([
                'email' => $viewing->email,
                'id' => (string) $viewing->id,
                'language' => $this->normalizeLocale($locale ?: (string) ($viewing->locale ?: 'en')),
                'name' => trim(($viewing->name ?? '') . ' ' . ($viewing->surname ?? '')),
                'city' => $viewing->city ?? '',
                'address' => $viewing->address ?? '',
                'date' => $viewing->date ?? '',
                'time' => $viewing->time ?? '',
                'link' => $viewing->payment_link ?? '',
            ], $extra));
        } catch (Exception $error) {
            Log::error($logMessage, [
                'viewing_id' => $viewing->id,
                'email' => $viewing->email,
                'locale' => $locale,
                'error' => $error->getMessage(),
            ]);

            throw $error;
        }
    }

    /**
     * @param array<string,mixed> $extra
     */
    private function sendDirectViewingEmail(
        string $path,
        string $email,
        string $locale = 'en',
        ?string $id = null,
        array $extra = [],
    ): void {
        $this->mailerApi->post($path, array_merge([
            'email' => $email,
            'id' => $id ?? '',
            'language' => $this->normalizeLocale($locale),
        ], $extra));
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
