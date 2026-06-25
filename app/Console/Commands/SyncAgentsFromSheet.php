<?php

namespace App\Console\Commands;

use App\Services\AgentSheetSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncAgentsFromSheet extends Command
{
    protected $signature = 'agents:sync-from-sheet {--dry-run : Show what would be synced without writing to the database}';

    protected $description = 'Sync agents from the configured Google Sheet into the agents table.';

    public function handle(AgentSheetSyncService $syncService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $summary = $syncService->sync($dryRun);
        } catch (\Throwable $error) {
            $this->error('Agents sheet sync failed: '.$error->getMessage());

            $this->recordFailedSchedulerRun($error, $dryRun);

            report($error);

            return self::FAILURE;
        }

        $this->info($dryRun ? 'Agents sheet dry run completed.' : 'Agents sheet sync completed.');
        $this->table(
            ['Sheet', 'Rows seen', 'Created', 'Updated', 'Skipped'],
            [[
                $summary['sheet_title'] ?? '-',
                $summary['rows_seen'],
                $summary['created'],
                $summary['updated'],
                $summary['skipped'],
            ]]
        );

        return self::SUCCESS;
    }

    private function recordFailedSchedulerRun(\Throwable $error, bool $dryRun): void
    {
        try {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) Str::uuid(),
                'connection' => 'scheduler',
                'queue' => 'agents:sync-from-sheet',
                'payload' => json_encode([
                    'displayName' => self::class,
                    'job' => 'artisan-command',
                    'command' => 'agents:sync-from-sheet',
                    'dry_run' => $dryRun,
                    'failed_at' => now()->toISOString(),
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'exception' => (string) $error,
                'failed_at' => now(),
            ]);
        } catch (\Throwable $failedJobError) {
            Log::error('[SyncAgentsFromSheet] Failed to record scheduler error in failed_jobs.', [
                'original_error' => $error->getMessage(),
                'failed_jobs_error' => $failedJobError->getMessage(),
            ]);
        }
    }
}
