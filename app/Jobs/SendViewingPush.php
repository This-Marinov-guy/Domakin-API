<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Integrations\Firebase\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendViewingPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $viewing) {}

    public function handle(PushNotificationService $pushService): void
    {
        $tokens = User::where('roles', 'like', '%admin%')
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) {
            Log::info('[SendViewingPush] No admin FCM tokens found, skipping push.');
            return;
        }

        $pushService->sendToMultipleTokens(
            $tokens,
            'New Viewing Request',
            'A new viewing has been booked',
        );
    }
}
