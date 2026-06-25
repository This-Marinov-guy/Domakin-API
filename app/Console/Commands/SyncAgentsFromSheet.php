<?php

namespace App\Console\Commands;

use App\Services\AgentSheetSyncService;
use Illuminate\Console\Command;

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
}
