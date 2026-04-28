<?php

namespace App\Jobs;

use App\Models\ListingApplication;
use App\Models\Property;
use App\Services\ListingMailerService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendListingMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const ACTION_FINISH_APPLICATION = 'finish_application';
    public const ACTION_SUBMITTED_LISTING = 'submitted_listing';
    public const ACTION_APPROVED_LISTING = 'approved_listing';
    public const ACTION_REJECTED_LISTING = 'rejected_listing';

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $action,
        public array $payload = [],
    ) {
    }

    public function handle(ListingMailerService $listingMailer): void
    {
        try {
            match ($this->action) {
                self::ACTION_FINISH_APPLICATION => $this->sendFinishApplication($listingMailer),
                self::ACTION_SUBMITTED_LISTING => $this->sendSubmittedListing($listingMailer),
                self::ACTION_APPROVED_LISTING => $this->sendApprovedListing($listingMailer),
                self::ACTION_REJECTED_LISTING => $this->sendRejectedListing($listingMailer),
                default => Log::warning('[SendListingMailerJob] Unsupported action, skipping.', [
                    'action' => $this->action,
                ]),
            };
        } catch (Exception $error) {
            Log::error('[SendListingMailerJob] Failed to send listing mailer action.', [
                'action' => $this->action,
                'payload' => $this->payload,
                'error' => $error->getMessage(),
            ]);

            throw $error;
        }
    }

    private function sendFinishApplication(ListingMailerService $listingMailer): void
    {
        $applicationId = (int) ($this->payload['listing_application_id'] ?? 0);
        $application = ListingApplication::find($applicationId);

        if (!$application) {
            Log::warning('[SendListingMailerJob] Listing application not found, skipping.', [
                'listing_application_id' => $applicationId,
            ]);
            return;
        }

        $listingMailer->sendFinishApplication($application);
    }

    private function sendSubmittedListing(ListingMailerService $listingMailer): void
    {
        $listingMailer->sendSubmittedListing(
            (int) ($this->payload['id'] ?? 0),
            (string) ($this->payload['email'] ?? ''),
            (string) ($this->payload['name'] ?? ''),
            (string) ($this->payload['address'] ?? ''),
            (string) ($this->payload['city'] ?? ''),
        );
    }

    private function sendApprovedListing(ListingMailerService $listingMailer): void
    {
        $property = $this->findProperty();

        if (!$property) {
            return;
        }

        $listingMailer->sendApprovedListing($property);
    }

    private function sendRejectedListing(ListingMailerService $listingMailer): void
    {
        $property = $this->findProperty();

        if (!$property) {
            return;
        }

        $listingMailer->sendRejectedListing(
            $property,
            (string) ($this->payload['reason'] ?? ''),
        );
    }

    private function findProperty(): ?Property
    {
        $propertyId = (int) ($this->payload['property_id'] ?? 0);
        $property = Property::with(['personalData', 'propertyData'])->find($propertyId);

        if (!$property) {
            Log::warning('[SendListingMailerJob] Property not found, skipping.', [
                'property_id' => $propertyId,
                'action' => $this->action,
            ]);
            return null;
        }

        return $property;
    }
}
