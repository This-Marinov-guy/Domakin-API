<?php

namespace App\Jobs;

use App\Models\Property;
use App\Services\ListingMailerService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendRoomCityCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $propertyId,
        public string $language = 'en',
    ) {
    }

    public function handle(ListingMailerService $listingMailer): void
    {
        $property = Property::with('propertyData')->find($this->propertyId);

        if (!$property || !$property->propertyData) {
            Log::warning('[SendRoomCityCampaignJob] Property not found, skipping.', [
                'property_id' => $this->propertyId,
            ]);
            return;
        }

        try {
            $listingMailer->sendNewRoomsForCriteriaCampaign($property, $this->language);
        } catch (Exception $error) {
            Log::error('[SendRoomCityCampaignJob] Failed to send room city campaign.', [
                'property_id' => $this->propertyId,
                'language' => $this->language,
                'error' => $error->getMessage(),
            ]);

            throw $error;
        }
    }
}
