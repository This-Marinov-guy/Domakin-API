<?php

namespace App\Services\GoogleServices;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\SheetProperties;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\Request;
use Google\Service\Sheets\ClearValuesRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GoogleSheetsService
{
    protected $client;
    protected $service;
    protected $spreadsheetId;

    public function __construct()
    {
        $this->spreadsheetId = SHEET_ID_DATABASE;
        $this->initializeGoogleClient();
    }

    protected function initializeGoogleClient()
    {
        try {
            $credentialsPath = base_path('google-credentials.json');

            if (!file_exists($credentialsPath)) {
                throw new \Exception('Google credentials file not found at: ' . $credentialsPath);
            }

            $this->client = new Client();
            $this->client->setAuthConfig($credentialsPath);
            $this->client->addScope('https://www.googleapis.com/auth/spreadsheets');

            $this->service = new Sheets($this->client);
        } catch (\Exception $e) {
            Log::error('Failed to initialize Google client: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if sheet exists and create if it doesn't
     *
     * @param string $sheetName
     * @return void
     */
    protected function ensureSheetExists(string $sheetName): void
    {
        try {
            // Get all sheets in the spreadsheet
            $spreadsheet = $this->service->spreadsheets->get($this->spreadsheetId);
            $sheets = $spreadsheet->getSheets();

            // Check if sheet exists
            $sheetExists = false;
            foreach ($sheets as $sheet) {
                if ($sheet->getProperties()->getTitle() === $sheetName) {
                    $sheetExists = true;
                    break;
                }
            }

            // If sheet doesn't exist, create it
            if (!$sheetExists) {
                $body = new BatchUpdateSpreadsheetRequest([
                    'requests' => [
                        new Request([
                            'addSheet' => [
                                'properties' => new SheetProperties([
                                    'title' => $sheetName
                                ])
                            ]
                        ])
                    ]
                ]);

                $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $body);
                Log::info("Created new sheet: $sheetName");
            }
        } catch (\Exception $e) {
            Log::error("Error ensuring sheet exists: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Export any model data to Google Spreadsheet
     *
     * @param Model|string $model The model instance or class name
     * @param string $sheetName The name of the sheet to export to
     * @param array $excludeColumns Columns to exclude from export
     * @param callable|null $dataTransformer Optional callback to transform data before export
     * @param array $whereConditions Optional where conditions for the query
     * @return void
     */
    public function exportModelToSpreadsheet(
        Model|string $model,
        string $sheetName,
        array $excludeColumns = [],
        ?callable $dataTransformer = null,
        array $whereConditions = []
    ): void {
        try {
            // Ensure sheet exists before proceeding
            $this->ensureSheetExists($sheetName);

            // Get model class if string was passed
            $modelClass = is_string($model) ? new $model() : $model;

            // Build query with conditions
            $query = $modelClass::query();
            foreach ($whereConditions as $condition) {
                $query->where(...$condition);
            }

            // Get all records
            $records = $query->get();

            // If no records, log and return
            if ($records->isEmpty()) {
                Log::info("No records found for export in model: " . get_class($modelClass));
                return;
            }

            // Get the first record to extract headers
            $firstRecord = $records->first();

            // Get all columns from the model
            $columns = $this->getModelColumns($firstRecord, $excludeColumns);

            // Format headers (convert snake_case to Title Case)
            $headers = $columns->map(function ($column) {
                return Str::title(str_replace('_', ' ', $column));
            })->toArray();

            // Transform records to array format
            $values = $records->map(function ($record) use ($columns, $dataTransformer) {
                $rowData = $columns->map(function ($column) use ($record) {
                    $value = $record->{$column};

                    // Handle different data types
                    if ($value instanceof Carbon) {
                        return $value->format('Y-m-d H:i:s');
                    }

                    if (is_array($value)) {
                        return json_encode($value);
                    }

                    if (is_bool($value)) {
                        return $value ? 'Yes' : 'No';
                    }

                    return $value ?? '';
                })->toArray();

                // Apply custom transformer if provided
                if ($dataTransformer) {
                    $rowData = $dataTransformer($rowData, $record);
                }

                return $rowData;
            })->toArray();

            $clearValuesRequest = new ClearValuesRequest();

            $this->service->spreadsheets_values->clear(
                $this->spreadsheetId,
                $sheetName,
                $clearValuesRequest
            );

            // Append new data
            $valueRange = new \Google\Service\Sheets\ValueRange([
                'values' => [
                    [$sheetName],
                    $headers,
                    ...$values
                ]
            ]);

            $this->service->spreadsheets_values->append(
                $this->spreadsheetId,
                $sheetName,
                $valueRange,
                ['valueInputOption' => 'RAW']
            );

            Log::info(sprintf(
                "Exported %d records from %s to sheet: %s",
                count($values),
                get_class($modelClass),
                $sheetName
            ));
        } catch (\Exception $e) {
            Log::error('Error in exportModelToSpreadsheet: ' . $e->getMessage());

            // do not throw error in order to not break the flow
            // throw $e;
        }
    }

    /**
     * Get model columns excluding specific columns
     *
     * @param Model $model
     * @param array $excludeColumns
     * @return Collection
     */
    protected function getModelColumns(Model $model, array $excludeColumns): Collection
    {
        $defaultExclude = ['id', 'password', 'remember_token', 'created_at', 'updated_at', 'deleted_at'];
        $allExcluded = array_merge($defaultExclude, $excludeColumns);

        return collect($model->getAttributes())
            ->keys()
            ->diff($allExcluded)
            ->values();
    }
}
