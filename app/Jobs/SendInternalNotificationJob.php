<?php

namespace App\Jobs;

use App\Mail\Notification;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendInternalNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $subjectLine,
        public string $templateUuid,
        public array $data,
    ) {
    }

    public function handle(): void
    {
        try {
            (new Notification($this->subjectLine, $this->templateUuid, $this->data))->sendNotification();
        } catch (Exception $error) {
            Log::error('[SendInternalNotificationJob] Failed to send internal notification.', [
                'subject' => $this->subjectLine,
                'template' => $this->templateUuid,
                'error' => $error->getMessage(),
            ]);

            throw $error;
        }
    }
}
