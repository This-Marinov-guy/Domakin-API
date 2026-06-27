<?php

namespace App\Jobs;

use App\Models\Viewing;
use App\Services\ViewingMailerService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendViewingMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public int $viewingId,
        public string $locale = 'en',
    ) {
    }

    public function handle(ViewingMailerService $viewingMailer): void
    {
        $viewing = Viewing::find($this->viewingId);

        if (! $viewing) {
            Log::warning('[SendViewingMailerJob] Viewing not found, skipping.', [
                'viewing_id' => $this->viewingId,
            ]);
            return;
        }

        try {
            $viewingMailer->sendRegisteredViewing($viewing, $this->locale);
        } catch (Exception $error) {
            Log::error('[SendViewingMailerJob] Failed to send registered viewing email.', [
                'viewing_id' => $this->viewingId,
                'locale' => $this->locale,
                'error' => $error->getMessage(),
            ]);

            throw $error;
        }
    }
}
