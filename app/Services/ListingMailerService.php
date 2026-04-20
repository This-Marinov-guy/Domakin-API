<?php

namespace App\Services;

use App\Constants\Emails;
use App\Models\ListingApplication;
use App\Models\Property;
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

    /**
     * Notify mailer to send "approved listing" email.
     * Logs errors and does not throw.
     */
    public function sendApprovedListing(Property $property): void
    {
        try {
            $personal = $property->personalData;
            $this->mailerApi->post('/listing/send-approved-listing', [
                'email'   => $personal->email ?? '',
                'id'      => $property->id,
                'name'    => trim(($personal->name ?? '') . ' ' . ($personal->surname ?? '')),
                'address' => $property->propertyData->address ?? '',
                'city'    => $property->propertyData->city ?? '',
            ]);
        } catch (Exception $e) {
            Log::error('Mailer send-approved-listing failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Notify mailer to send "rejected listing" email with a reason.
     * Logs errors and does not throw.
     */
    public function sendRejectedListing(Property $property, string $reason): void
    {
        try {
            $personal = $property->personalData;
            $this->mailerApi->post('/listing/send-reject-listing', [
                'email'   => $personal->email ?? '',
                'id'      => $property->id,
                'name'    => trim(($personal->name ?? '') . ' ' . ($personal->surname ?? '')),
                'address' => $property->propertyData->address ?? '',
                'city'    => $property->propertyData->city ?? '',
                'reason'  => $reason,
            ]);
        } catch (Exception $e) {
            Log::error('Mailer send-reject-listing failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Trigger the "new room" campaign for a single room property.
     *
     * @return array<string,mixed>
     * @throws Exception
     */
    public function sendNewRoomsForCriteriaCampaign(Property $property, string $language = 'en'): array
    {
        return $this->mailerApi->post('/room/send-new-room-city-campaign', [
            'property_id' => $property->id,
            'language' => $language,
        ]);
    }

    /**
     * Fetch the recipient preview for the "new room" campaign of a single property.
     *
     * @return array<string,mixed>
     * @throws Exception
     */
    public function previewNewRoomsForCriteriaCampaign(Property $property, string $language = 'en'): array
    {
        return $this->mailerApi->post('/room/preview-new-room-city-campaign', [
            'property_id' => $property->id,
            'language' => $language,
        ]);
    }
}
