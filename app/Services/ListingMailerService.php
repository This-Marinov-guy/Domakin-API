<?php

namespace App\Services;

use App\Constants\Emails;
use App\Models\ListingApplication;
use Exception;
use Illuminate\Support\Facades\Log;

class ListingMailerService
{
    public function __construct(
        private MailerApiService $mailerApi
    ) {
    }

    /**
     * Notify mailer to send "finish application" email (draft saved).
     * Logs errors and does not throw.
     */
    public function sendFinishApplication(ListingApplication $application): void
    {
        try {
            $this->mailerApi->post('/room/send-finish-application', [
                'email'   => $application->email,
                'id'      => $application->id,
                'name'    => trim(($application->name ?? '') . ' ' . ($application->surname ?? '')),
                'address' => $application->address ?? '',
                'city'    => $application->city ?? '',
                'step'    => $application->step,
                'link'    => Emails::LINKS['application_finish'] . $application->reference_id,
            ]);
        } catch (Exception $e) {
            Log::error('Mailer send-finish-application failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Notify mailer to send "submitted listing" email.
     * Logs errors and does not throw.
     *
     * @param int $id Property ID
     * @param string $email
     * @param string $name Full name
     * @param string $address
     * @param string $city
     */
    public function sendSubmittedListing(int $id, string $email, string $name, string $address, string $city): void
    {
        try {
            $this->mailerApi->post('/listing/send-submitted-listing', [
                'email'   => $email,
                'id'      => $id,
                'name'    => $name,
                'address' => $address,
                'city'    => $city,
            ]);
        } catch (Exception $e) {
            Log::error('Mailer send-submitted-listing failed', ['error' => $e->getMessage()]);
        }
    }
}
