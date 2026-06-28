<?php

namespace App\Console\Commands;

use App\Models\Career;
use App\Services\GoogleServices\GoogleSheetsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExportCareersToSheet extends Command
{
    protected $signature = 'careers:export-to-sheet';

    protected $description = 'Clear and re-export all career applications from the database to the Google Sheet.';

    public function handle(): int
    {
        $this->info('Exporting careers to Google Sheet...');

        try {
            $sheetsService = app(GoogleSheetsService::class);
            $count = Career::count();
            $sheetsService->exportModelToSpreadsheet(Career::class, 'Careers', ['resume']);
        } catch (\Throwable $error) {
            $this->error('Careers export failed: ' . $error->getMessage());

            $this->recordFailedSchedulerRun($error);

            report($error);

            return self::FAILURE;
        }

        $this->info("Done. Exported {$count} career application(s) to the 'Careers' sheet.");

        return self::SUCCESS;
    }

    private function recordFailedSchedulerRun(\Throwable $error): void
    {
        try {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) Str::uuid(),
                'connection' => 'scheduler',
                'queue' => 'careers:export-to-sheet',
                'payload' => json_encode([
                    'displayName' => self::class,
                    'job' => 'artisan-command',
                    'command' => 'careers:export-to-sheet',
                    'failed_at' => now()->toISOString(),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'exception' => (string) $error,
                'failed_at' => now(),
            ]);
        } catch (\Throwable $failedJobError) {
            Log::error('[ExportCareersToSheet] Failed to record error in failed_jobs.', [
                'original_error' => $error->getMessage(),
                'failed_jobs_error' => $failedJobError->getMessage(),
            ]);
        }
    }
}
