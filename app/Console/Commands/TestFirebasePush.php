<?php

namespace App\Console\Commands;

use App\Services\Integrations\Firebase\PushNotificationService;
use Illuminate\Console\Command;

class TestFirebasePush extends Command
{
    protected $signature = 'firebase:test-push
                            {token : FCM device token to send the notification to}
                            {--title=Test Notification : Notification title}
                            {--body=This is a test push notification from Domakin. : Notification body}';

    protected $description = 'Send a test push notification to a given FCM token';

    public function handle(PushNotificationService $pushService): int
    {
        $token = $this->argument('token');
        $title = $this->option('title');
        $body  = $this->option('body');

        $this->info("Sending push notification...");
        $this->line("  Token : {$token}");
        $this->line("  Title : {$title}");
        $this->line("  Body  : {$body}");

        try {
            $pushService->sendToToken($token, $title, $body);
            $this->newLine();
            $this->info('Push notification sent successfully.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Failed to send push notification: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
