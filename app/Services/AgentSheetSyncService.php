<?php

namespace App\Services;

use App\Jobs\SendInternalNotificationJob;
use App\Models\Agent;
use App\Services\GoogleServices\GoogleSheetsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AgentSheetSyncService
{
    private const EXCLUDED_HEADER_PARTS = [
        'experience',
        'message',
    ];

    public function __construct(private readonly GoogleSheetsService $sheets)
    {
    }

    /**
     * Sync agents from the configured Google Sheet into the agents table.
     *
     * Missing rows are never deleted from the database.
     *
     * @return array{sheet_title: string|null, rows_seen: int, created: int, updated: int, skipped: int, dry_run: bool, added_agents: array<int, array<string, mixed>>}
     */
    public function sync(bool $dryRun = false): array
    {
        $spreadsheetId = AGENTS_SHEET_SPREADSHEET_ID;
        $sheetGid = AGENTS_SHEET_GID;
        $sheetTitle = $this->sheets->getSheetTitleById($spreadsheetId, $sheetGid);

        if (!$sheetTitle) {
            throw new \RuntimeException("Could not find agents sheet tab with gid {$sheetGid}.");
        }

        $range = $this->quoteSheetTitle($sheetTitle).'!A:ZZ';
        $values = $this->sheets->getValues($spreadsheetId, $range);
        $headerRowIndex = $this->findHeaderRowIndex($values);
        $headers = $this->normaliseHeaders($values[$headerRowIndex] ?? []);

        $summary = [
            'sheet_title' => $sheetTitle,
            'rows_seen' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'dry_run' => $dryRun,
            'added_agents' => [],
        ];

        if (empty($headers)) {
            return $summary;
        }

        for ($rowIndex = $headerRowIndex + 1; $rowIndex < count($values); $rowIndex++) {
            $record = $this->rowToRecord($headers, $values[$rowIndex] ?? []);

            if (!$this->hasUsableData($record)) {
                $summary['skipped']++;
                continue;
            }

            $summary['rows_seen']++;

            $email = $this->extractEmail($record);
            $phone = $this->extractPhone($record);
            $name = $this->extractName($record);
            $rowHash = sha1(json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $sourceKey = $this->makeSourceKey($email, $phone, $rowHash);

            $payload = [
                'source_key' => $sourceKey,
                'spreadsheet_id' => $spreadsheetId,
                'sheet_gid' => (string) $sheetGid,
                'sheet_row_number' => $rowIndex + 1,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'data' => $record,
                'source_row_hash' => $rowHash,
                'synced_at' => Carbon::now(),
            ];

            $existing = Agent::query()->where('source_key', $sourceKey)->first();

            if ($dryRun) {
                $summary[$existing ? 'updated' : 'created']++;
                continue;
            }

            if ($existing) {
                $existing->fill($payload)->save();
                $summary['updated']++;
            } else {
                Agent::query()->create($payload);
                $summary['created']++;
                $summary['added_agents'][] = $this->formatAgentForNotification($payload);
            }
        }

        Log::info('[AgentSheetSyncService] Agents sheet sync completed.', $summary);

        if (!$dryRun) {
            $this->sendSyncNotification($summary);
        }

        return $summary;
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
            'name' => $payload['name'] ?? null,
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'sheet_row_number' => $payload['sheet_row_number'] ?? null,
        ];
    }

    /**
     * @param array<int, array<int, mixed>> $values
     */
    private function findHeaderRowIndex(array $values): int
    {
        $maxRowsToInspect = min(10, count($values));

        for ($index = 0; $index < $maxRowsToInspect; $index++) {
            $headers = $this->normaliseHeaders($values[$index] ?? []);
            $headerKeys = array_filter($headers);

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
    private function normaliseHeaders(array $headers): array
    {
        $seen = [];

        return array_map(function ($header) use (&$seen) {
            $normalised = $this->normaliseHeader((string) $header);

            if ($normalised === '') {
                return null;
            }

            $seen[$normalised] = ($seen[$normalised] ?? 0) + 1;

            return $seen[$normalised] > 1
                ? $normalised.'_'.$seen[$normalised]
                : $normalised;
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
            if (!$header || $this->shouldExcludeHeader($header)) {
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

    private function shouldExcludeHeader(string $header): bool
    {
        $baseHeader = preg_replace('/_\d+$/', '', $header) ?: $header;

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
        foreach ($keys as $key) {
            $value = $record[$key] ?? null;

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
            if (!str_contains($key, $needle)) {
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
            return 'email:'.sha1($email);
        }

        $normalisedPhone = $this->normalisePhone($phone);

        if ($normalisedPhone) {
            return 'phone:'.sha1($normalisedPhone);
        }

        return 'row:'.$rowHash;
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
}
