<?php

namespace App\Services;

use App\Models\SearchRenting;
use Exception;
use Illuminate\Support\Facades\Log;

class SearchRentingMailerService
{
    public function __construct(
        private MailerApiService $mailerApi
    ) {
    }

    public function sendRoomSearchingApplied(SearchRenting $searchRenting): void
    {
        try {
            $this->mailerApi->post('/room/send-room-searching-applied', [
                'email' => $searchRenting->email,
                'id' => (string) $searchRenting->id,
                'language' => $this->normalizeLocale((string) ($searchRenting->locale ?: 'en')),
            ]);
        } catch (Exception $error) {
            Log::error('Mailer send-room-searching-applied failed', [
                'search_renting_id' => $searchRenting->id,
                'email' => $searchRenting->email,
                'locale' => $searchRenting->locale,
                'error' => $error->getMessage(),
            ]);

            throw $error;
        }
    }

    public function sendRoomSearchingAppliedEmail(string $email, string $locale = 'en', ?string $id = null): void
    {
        $this->mailerApi->post('/room/send-room-searching-applied', [
            'email' => $email,
            'id' => $id ?? '',
            'language' => $this->normalizeLocale($locale),
        ]);
    }

    private function normalizeLocale(string $locale): string
    {
        return str_starts_with(strtolower($locale), 'bg') ? 'bg' : 'en';
    }
}
