<?php

namespace App\Jobs;

use App\Services\GoogleServices\GoogleSheetsService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExportModelToSpreadsheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 120;

    /**
     * @param array<int, string> $excludeColumns
     * @param array<int, array<int, mixed>> $whereConditions
     */
    public function __construct(
        public string $modelClass,
        public string $sheetName,
        public array $excludeColumns = [],
        public array $whereConditions = [],
    ) {
    }

    public function handle(GoogleSheetsService $sheetsService): void
    {
        try {
            $sheetsService->exportModelToSpreadsheet(
                $this->modelClass,
                $this->sheetName,
                $this->excludeColumns,
                null,
                $this->whereConditions,
            );
        } catch (Exception $error) {
            Log::error('[ExportModelToSpreadsheetJob] Failed to export model to spreadsheet.', [
                'model' => $this->modelClass,
                'sheet' => $this->sheetName,
                'error' => $error->getMessage(),
            ]);

            throw $error;
        }
    }
}
