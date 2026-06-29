<?php

namespace App\Jobs;

use App\Models\SearchRenting;
use App\Services\SearchRentingMailerService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendSearchRentingMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $searchRentingId,
    ) {
    }

    public function handle(SearchRentingMailerService $searchRentingMailer): void
    {
        $searchRenting = SearchRenting::find($this->searchRentingId);

        if (!$searchRenting) {
            Log::warning('[SendSearchRentingMailerJob] Search renting not found, skipping.', [
                'search_renting_id' => $this->searchRentingId,
            ]);
            return;
        }

        try {
            $searchRentingMailer->sendRoomSearchingApplied($searchRenting);
        } catch (Exception $error) {
            Log::error('[SendSearchRentingMailerJob] Failed to send room searching applied email.', [
                'search_renting_id' => $this->searchRentingId,
                'error' => $error->getMessage(),
            ]);

            throw $error;
        }
    }
}
