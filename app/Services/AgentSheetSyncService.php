<?php

namespace App\Services;

use App\Jobs\SendInternalNotificationJob;
use App\Models\Agent;
use App\Services\GoogleServices\GoogleSheetsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AgentSheetSyncService
{
    private const EXCLUDED_HEADER_PARTS = [
        'experience',
        'message',
    ];

    private const RESERVED_COLUMNS = [
        'id',
        'source_key',
        'metadata',
        'created_at',
        'updated_at',
    ];

    public function __construct(private readonly GoogleSheetsService $sheets)
    {
    }

    /**
     * Sync agents from the configured Google Sheet into the agents table.
     *
     * Missing rows are never deleted from the database.
     *
     * @param callable(string, array<string, mixed>): void|null $progress
     * @return array{sheet_title: string|null, rows_seen: int, created: int, updated: int, skipped: int, dry_run: bool, added_agents: array<int, array<string, mixed>>}
     */
    public function sync(bool $dryRun = false, ?callable $progress = null): array
    {
        $spreadsheetId = AGENTS_SHEET_SPREADSHEET_ID;
        $sheetGid = AGENTS_SHEET_GID;

        $this->progress($progress, 'Starting sync', [
            'dry_run' => $dryRun,
            'spreadsheet_id' => $spreadsheetId,
            'sheet_gid' => $sheetGid,
        ]);

        $this->progress($progress, 'Resolving sheet title from gid');
        $sheetTitle = $this->sheets->getSheetTitleById($spreadsheetId, $sheetGid);

        if (!$sheetTitle) {
            throw new \RuntimeException("Could not find agents sheet tab with gid {$sheetGid}.");
        }

        $this->progress($progress, 'Resolved sheet title', [
            'sheet_title' => $sheetTitle,
        ]);

        $range = $this->quoteSheetTitle($sheetTitle).'!A:ZZ';
        $this->progress($progress, 'Reading sheet values', [
            'range' => $range,
        ]);
        $values = $this->sheets->getValues($spreadsheetId, $range);
        $this->progress($progress, 'Sheet values loaded', [
            'raw_rows' => count($values),
        ]);

        $headerRowIndex = $this->findHeaderRowIndex($values);
        $headers = $this->sheetHeaders($values[$headerRowIndex] ?? []);
        $usableHeaders = array_values(array_filter($headers));
        $columnNames = $this->sheetColumnNames($headers);

        $this->progress($progress, 'Detected header row', [
            'header_row_number' => $headerRowIndex + 1,
            'usable_headers' => $usableHeaders,
            'columns' => array_values(array_unique(array_filter($columnNames))),
        ]);

        $summary = [
            'sheet_title' => $sheetTitle,
            'rows_seen' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'dry_run' => $dryRun,
            'added_agents' => [],
        ];

        if (empty($usableHeaders)) {
            $this->progress($progress, 'No usable headers found, stopping');

            return $summary;
        }

        if (!$dryRun) {
            $this->ensureSheetColumnsExist($columnNames, $progress);
        } else {
            $this->progress($progress, 'Dry-run: skipping dynamic column creation', [
                'columns' => array_values(array_unique(array_filter($columnNames))),
            ]);
        }

        $this->progress($progress, 'Processing rows', [
            'first_data_row_number' => $headerRowIndex + 2,
            'last_raw_row_number' => count($values),
        ]);

        for ($rowIndex = $headerRowIndex + 1; $rowIndex < count($values); $rowIndex++) {
            $record = $this->rowToRecord($headers, $values[$rowIndex] ?? []);
            $columnValues = $this->rowToColumnValues($columnNames, $values[$rowIndex] ?? []);
            $sheetRowNumber = $rowIndex + 1;

            if (!$this->hasUsableData($columnValues)) {
                $summary['skipped']++;
                $this->progress($progress, 'Skipped empty row', [
                    'row' => $sheetRowNumber,
                ]);
                continue;
            }

            $summary['rows_seen']++;

            $email = $this->extractEmail($record);
            $phone = $this->extractPhone($record);
            $name = $this->extractName($record);
            $rowHash = sha1(json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $sourceKey = $this->makeSourceKey($email, $phone, $rowHash);
            $syncedAt = Carbon::now();

            $metadata = [
                'sheet_gid' => (string) $sheetGid,
                'synced_at' => $syncedAt->format('Y-m-d\TH:i:s'),
                'spreadsheet_id' => $spreadsheetId,
                'source_row_hash' => $rowHash,
                'sheet_row_number' => $sheetRowNumber,
            ];

            $payload = array_merge($columnValues, [
                'source_key' => $sourceKey,
                'metadata' => $metadata,
            ]);

            if (!array_key_exists('email', $payload) && $email) {
                $payload['email'] = $email;
            }

            $existing = Agent::query()
                ->where('source_key', $sourceKey)
                ->when($email, fn ($query) => $query->orWhereRaw('LOWER(email) = ?', [strtolower($email)]))
                ->first();
            $action = $existing ? 'update' : 'create';

            $this->progress($progress, $dryRun ? 'Dry-run row decision' : 'Row decision', [
                'row' => $sheetRowNumber,
                'action' => $action,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
            ]);

            if ($dryRun) {
                $summary[$existing ? 'updated' : 'created']++;
                continue;
            }

            if ($existing) {
                $existing->fill($payload)->save();
                $summary['updated']++;
                $this->progress($progress, 'Updated existing agent', [
                    'row' => $sheetRowNumber,
                    'agent_id' => $existing->id,
                    'email' => $email,
                ]);
            } else {
                $agent = Agent::query()->create($payload);
                $summary['created']++;
                $summary['added_agents'][] = $this->formatAgentForNotification($payload);
                $this->progress($progress, 'Created new agent', [
                    'row' => $sheetRowNumber,
                    'agent_id' => $agent->id,
                    'email' => $email,
                ]);
            }
        }

        $this->progress($progress, 'Sync completed', [
            'rows_seen' => $summary['rows_seen'],
            'created' => $summary['created'],
            'updated' => $summary['updated'],
            'skipped' => $summary['skipped'],
        ]);

        if (!$dryRun) {
            $this->progress($progress, 'Sending sync notification email');
            $this->sendSyncNotification($summary);
        }

        return $summary;
    }

    /**
     * @param callable(string, array<string, mixed>): void|null $progress
     * @param array<string, mixed> $context
     */
    private function progress(?callable $progress, string $message, array $context = []): void
    {
        Log::info('[AgentSheetSyncService] '.$message, $context);

        if ($progress) {
            $progress($message, $context);
        }
    }

    /**
     * @param array<string, mixed> $summary
     */
    private function sendSyncNotification(array $summary): void
    {
        try {
            $created = (int) ($summary['created'] ?? 0);
            $subject = sprintf(
                'Agents sync completed: %d new %s',
                $created,
                $created === 1 ? 'agent' : 'agents'
            );

            SendInternalNotificationJob::dispatchSync($subject, 'agents', $summary);
        } catch (\Throwable $error) {
            Log::error('[AgentSheetSyncService] Failed to dispatch agents sync notification.', [
                'error' => $error->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function formatAgentForNotification(array $payload): array
    {
        return [
            'name' => $payload['name'] ?? $payload['full_name'] ?? null,
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? $payload['mobile'] ?? $payload['telephone'] ?? null,
            'sheet_row_number' => $payload['metadata']['sheet_row_number'] ?? null,
        ];
    }

    /**
     * @param array<int, array<int, mixed>> $values
     */
    private function findHeaderRowIndex(array $values): int
    {
        $maxRowsToInspect = min(10, count($values));

        for ($index = 0; $index < $maxRowsToInspect; $index++) {
            $headers = $this->sheetHeaders($values[$index] ?? []);
            $headerKeys = array_values(array_filter(array_map(
                fn (?string $header): ?string => $header ? $this->normaliseHeader($header) : null,
                $headers,
            )));

            if (count($headerKeys) < 2) {
                continue;
            }

            if ($this->containsAnyHeader($headerKeys, ['email', 'phone', 'name', 'agent'])) {
                return $index;
            }
        }

        return 0;
    }

    /**
     * @param array<int, mixed> $headers
     * @return array<int, string|null>
     */
    private function sheetHeaders(array $headers): array
    {
        $seen = [];

        return array_map(function ($header) use (&$seen) {
            $label = trim((string) $header);

            if ($label === '' || $this->shouldExcludeHeader($label)) {
                return null;
            }

            $seen[$label] = ($seen[$label] ?? 0) + 1;

            return $seen[$label] > 1
                ? $label.' ('.$seen[$label].')'
                : $label;
        }, $headers);
    }

    /**
     * @param array<int, string|null> $headers
     * @return array<int, string|null>
     */
    private function sheetColumnNames(array $headers): array
    {
        $seen = [];

        return array_map(function (?string $header) use (&$seen): ?string {
            if (!$header) {
                return null;
            }

            $column = $this->normaliseHeader($header);

            if ($column === '') {
                return null;
            }

            if (in_array($column, self::RESERVED_COLUMNS, true)) {
                $column = 'sheet_'.$column;
            }

            $seen[$column] = ($seen[$column] ?? 0) + 1;

            return $seen[$column] > 1
                ? $column.'_'.$seen[$column]
                : $column;
        }, $headers);
    }

    private function normaliseHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?: '';

        return trim($header, '_');
    }

    /**
     * @param array<int, string|null> $headers
     * @param array<int, mixed> $row
     * @return array<string, mixed>
     */
    private function rowToRecord(array $headers, array $row): array
    {
        $record = [];

        foreach ($headers as $index => $header) {
            if (!$header) {
                continue;
            }

            $value = $row[$index] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            $record[$header] = $value === '' ? null : $value;
        }

        return $record;
    }

    /**
     * @param array<int, string|null> $columnNames
     * @param array<int, mixed> $row
     * @return array<string, mixed>
     */
    private function rowToColumnValues(array $columnNames, array $row): array
    {
        $values = [];

        foreach ($columnNames as $index => $columnName) {
            if (!$columnName) {
                continue;
            }

            $value = $this->normaliseCellValue($row[$index] ?? null);
            $values[$columnName] = $value;
        }

        return $values;
    }

    private function normaliseCellValue(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '') {
            return null;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<int, string|null> $columnNames
     * @param callable(string, array<string, mixed>): void|null $progress
     */
    private function ensureSheetColumnsExist(array $columnNames, ?callable $progress = null): void
    {
        $columns = array_values(array_unique(array_filter($columnNames)));

        foreach ($columns as $column) {
            DB::statement(sprintf(
                'ALTER TABLE agents ADD COLUMN IF NOT EXISTS %s text',
                $this->quoteIdentifier($column),
            ));
        }

        $this->progress($progress, 'Ensured sheet columns exist', [
            'columns' => $columns,
        ]);
    }

    private function shouldExcludeHeader(string $header): bool
    {
        $baseHeader = $this->normaliseHeader($header);
        $baseHeader = preg_replace('/_\d+$/', '', $baseHeader) ?: $baseHeader;

        foreach (self::EXCLUDED_HEADER_PARTS as $excludedPart) {
            if (str_contains($baseHeader, $excludedPart)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function hasUsableData(array $record): bool
    {
        foreach ($record as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function extractEmail(array $record): ?string
    {
        $email = $this->pickFirst($record, [
            'email',
            'email_address',
            'e_mail_address',
            'e_mail',
            'mail',
        ]);

        if (!$email) {
            $email = $this->pickFirstMatchingHeader($record, 'email')
                ?: $this->pickFirstMatchingHeader($record, 'e_mail');
        }

        if (!$email) {
            return null;
        }

        $email = strtolower(trim($email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function extractPhone(array $record): ?string
    {
        return $this->pickFirst($record, [
            'phone',
            'phone_number',
            'mobile_phone',
            'mobile',
            'mobile_number',
            'telephone',
            'whatsapp',
        ]) ?: $this->pickFirstMatchingHeader($record, 'phone')
            ?: $this->pickFirstMatchingHeader($record, 'mobile')
            ?: $this->pickFirstMatchingHeader($record, 'telephone')
            ?: $this->pickFirstMatchingHeader($record, 'whatsapp');
    }

    /**
     * @param array<string, mixed> $record
     */
    private function extractName(array $record): ?string
    {
        $name = $this->pickFirst($record, [
            'name',
            'full_name',
            'agent_name',
            'agent',
        ]) ?: $this->pickFirstMatchingHeader($record, 'name');

        if ($name) {
            return $name;
        }

        $firstName = $this->pickFirst($record, ['first_name', 'firstname']);
        $lastName = $this->pickFirst($record, ['last_name', 'lastname']);
        $combined = trim(implode(' ', array_filter([$firstName, $lastName])));

        return $combined !== '' ? $combined : null;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<int, string> $keys
     */
    private function pickFirst(array $record, array $keys): ?string
    {
        $lookupKeys = array_flip($keys);

        foreach ($record as $recordKey => $value) {
            if (!isset($lookupKeys[$this->normaliseHeader((string) $recordKey)])) {
                continue;
            }

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function pickFirstMatchingHeader(array $record, string $needle): ?string
    {
        foreach ($record as $key => $value) {
            if (!str_contains($this->normaliseHeader((string) $key), $needle)) {
                continue;
            }

            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function makeSourceKey(?string $email, ?string $phone, string $rowHash): string
    {
        if ($email) {
            return sha1($email);
        }

        $normalisedPhone = $this->normalisePhone($phone);

        if ($normalisedPhone) {
            return sha1($normalisedPhone);
        }

        return $rowHash;
    }

    private function normalisePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $normalised = preg_replace('/\D+/', '', $phone) ?: '';

        return $normalised !== '' ? $normalised : null;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, string> $needles
     */
    private function containsAnyHeader(array $headers, array $needles): bool
    {
        foreach ($headers as $header) {
            foreach ($needles as $needle) {
                if (str_contains($header, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function quoteSheetTitle(string $sheetTitle): string
    {
        return "'".str_replace("'", "''", $sheetTitle)."'";
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
}
