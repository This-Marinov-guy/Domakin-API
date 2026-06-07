<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ConfirmSupabaseUserPhones extends Command
{
    protected $signature = 'supabase:confirm-phones
                            {--dry-run : List affected users without writing}
                            {--strip : Clear the phone (phone="") instead of confirming it}';

    protected $description = 'Confirm (or strip) unconfirmed phone numbers in Supabase auth.users so password login stops returning "Phone not confirmed".';

    public function handle(): int
    {
        $url = rtrim((string) config('supabase.url'), '/');
        $key = (string) config('supabase.service_role_key');

        if ($url === '' || $key === '') {
            $this->error('SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY must be set in .env.');
            return 1;
        }

        $dryRun = (bool) $this->option('dry-run');
        $strip = (bool) $this->option('strip');

        if ($dryRun) {
            $this->warn('Dry run – no changes will be written.');
        }

        // Collect all auth users with a phone that isn't confirmed (paginated).
        $affected = [];
        $page = 1;
        do {
            $response = Http::withHeaders([
                'apikey' => $key,
                'Authorization' => 'Bearer ' . $key,
            ])->get($url . '/auth/v1/admin/users', [
                'page' => $page,
                'per_page' => 1000,
            ]);

            if (!$response->successful()) {
                $this->error("Failed to list users (page {$page}): " . $response->status() . ' ' . $response->body());
                return 1;
            }

            $users = $response->json('users') ?? [];
            foreach ($users as $user) {
                $phone = $user['phone'] ?? null;
                $confirmedAt = $user['phone_confirmed_at'] ?? null;
                if (!empty($phone) && empty($confirmedAt)) {
                    $affected[] = $user;
                }
            }

            $page++;
        } while (count($users) === 1000);

        $total = count($affected);
        if ($total === 0) {
            $this->info('No users with an unconfirmed phone. Nothing to do.');
            return 0;
        }

        $action = $strip ? 'strip phone from' : 'confirm phone for';
        $this->info(($dryRun ? 'Would ' : '') . "{$action} {$total} user(s).");

        $payload = $strip ? ['phone' => ''] : ['phone_confirm' => true];

        $ok = 0;
        $fail = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($affected as $user) {
            $id = $user['id'];
            $email = $user['email'] ?? '(no email)';

            if ($dryRun) {
                $this->newLine();
                $this->line("  {$id} | {$email} | {$user['phone']}");
                $bar->advance();
                continue;
            }

            $update = Http::withHeaders([
                'apikey' => $key,
                'Authorization' => 'Bearer ' . $key,
                'Content-Type' => 'application/json',
            ])->put($url . '/auth/v1/admin/users/' . $id, $payload);

            if ($update->successful()) {
                $ok++;
            } else {
                $fail++;
                $this->newLine();
                $this->warn("  {$email} ({$id}): " . $update->status() . ' ' . $update->body());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        if ($dryRun) {
            $this->info("Done (dry run). {$total} user(s) would be updated.");
        } else {
            $this->info("Done. Success: {$ok}, failed: {$fail}.");
        }

        return $fail > 0 ? 1 : 0;
    }
}
