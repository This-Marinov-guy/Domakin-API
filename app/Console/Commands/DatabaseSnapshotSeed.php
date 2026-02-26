<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DatabaseSnapshotSeed extends Command
{
    protected $signature = 'db:snapshot-seed
                            {file : Path to the snapshot SQL file}
                            {--max-rows=5 : Maximum rows to import per table}
                            {--host= : Target database host}
                            {--port=5432 : Target database port}
                            {--username= : Target database username}
                            {--password= : Target database password}
                            {--database= : Target database name}
                            {--output= : Save filtered SQL to file instead of executing it}';

    protected $description = 'Import a snapshot SQL file into a database, capping each table at --max-rows rows';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: {$file}");
            return 1;
        }

        $maxRows = (int) $this->option('max-rows');
        $outputPath = $this->option('output');

        // Decide mode: write filtered SQL to file, or pipe into psql
        if ($outputPath) {
            return $this->writeFiltered($file, $maxRows, $outputPath);
        }

        return $this->pipeToPostgres($file, $maxRows);
    }

    private function writeFiltered(string $file, int $maxRows, string $outputPath): int
    {
        $dir = dirname($outputPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $this->error("Cannot create output directory: {$dir}");
            return 1;
        }

        $out = fopen($outputPath, 'w');
        if (!$out) {
            $this->error("Cannot open output file for writing: {$outputPath}");
            return 1;
        }

        $stats = $this->processStream($file, $maxRows, function (string $line) use ($out) {
            fwrite($out, $line);
        });

        fclose($out);

        $size = $this->humanSize(filesize($outputPath));
        $this->info("Filtered SQL written to: {$outputPath} ({$size})");
        $this->printStats($stats);
        return 0;
    }

    private function pipeToPostgres(string $file, int $maxRows): int
    {
        $host     = $this->option('host') ?: '127.0.0.1';
        $port     = $this->option('port') ?: '5432';
        $username = $this->option('username');
        $password = $this->option('password') ?? '';
        $database = $this->option('database');

        if (!$username || !$database) {
            $this->error('--username and --database are required when not using --output.');
            return 1;
        }

        $psql = $this->findBinary('psql');
        if (!$psql) {
            $this->error('psql not found. Install PostgreSQL client tools.');
            return 1;
        }

        $cmd = sprintf(
            'PGPASSWORD=%s %s --host=%s --port=%s --username=%s --dbname=%s --no-password',
            escapeshellarg($password),
            escapeshellarg($psql),
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($username),
            escapeshellarg($database)
        );

        $this->info("Connecting to {$username}@{$host}:{$port}/{$database} …");

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            $this->error('Failed to start psql process.');
            return 1;
        }

        $stdin  = $pipes[0];
        $stdout = $pipes[1];
        $stderr = $pipes[2];

        // Stream filtered SQL into psql stdin
        $stats = $this->processStream($file, $maxRows, function (string $line) use ($stdin) {
            fwrite($stdin, $line);
        });

        fclose($stdin);

        $out = stream_get_contents($stdout);
        $err = stream_get_contents($stderr);
        fclose($stdout);
        fclose($stderr);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $this->error("psql failed (exit {$exitCode}):");
            if ($err) {
                $this->error(trim($err));
            }
            return 1;
        }

        $this->info('Import complete.');
        $this->printStats($stats);
        return 0;
    }

    /**
     * Stream the SQL file line by line, capping COPY blocks at $maxRows.
     * Calls $write(string $line) for every line that should be emitted.
     * Returns stats: ['tables' => int, 'rows_written' => int, 'rows_skipped' => int]
     */
    private function processStream(string $file, int $maxRows, callable $write): array
    {
        $handle = fopen($file, 'r');
        if (!$handle) {
            throw new \RuntimeException("Cannot open file: {$file}");
        }

        $inCopy      = false;
        $rowsWritten = 0;
        $rowsSkipped = 0;
        $tables      = 0;
        $tableRows   = 0;
        $currentTable = '';

        while (($line = fgets($handle)) !== false) {
            if (!$inCopy) {
                // Detect start of a COPY block
                if (preg_match('/^COPY\s+\S+\s+\(.*\)\s+FROM\s+stdin;/i', $line)) {
                    $inCopy    = true;
                    $tableRows = 0;
                    $tables++;
                    // Extract table name for progress display
                    preg_match('/^COPY\s+(\S+)/i', $line, $m);
                    $currentTable = $m[1] ?? '?';
                }
                $write($line);
            } else {
                // Inside a COPY block
                if (rtrim($line) === '\\.') {
                    // End-of-COPY marker — always emit
                    $write($line);
                    $inCopy = false;
                    $this->line("  {$currentTable}: {$tableRows} rows");
                } elseif ($tableRows < $maxRows) {
                    $write($line);
                    $tableRows++;
                    $rowsWritten++;
                } else {
                    // Skip excess rows but keep counting
                    $rowsSkipped++;
                }
            }
        }

        fclose($handle);

        return [
            'tables'       => $tables,
            'rows_written' => $rowsWritten,
            'rows_skipped' => $rowsSkipped,
        ];
    }

    private function printStats(array $stats): void
    {
        $this->line('');
        $this->info("Tables processed : {$stats['tables']}");
        $this->info("Rows imported    : {$stats['rows_written']}");
        $this->info("Rows skipped     : {$stats['rows_skipped']}");
    }

    private function findBinary(string $name): ?string
    {
        $locations = [];
        foreach ([17, 16, 15, 14] as $v) {
            $locations[] = "/opt/homebrew/opt/postgresql@{$v}/bin/{$name}";
            $locations[] = "/usr/lib/postgresql/{$v}/bin/{$name}";
        }
        $locations[] = '/usr/local/bin/' . $name;
        $locations[] = '/opt/homebrew/bin/' . $name;
        $locations[] = '/usr/bin/' . $name;

        foreach ($locations as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        $which = shell_exec('which ' . escapeshellarg($name) . ' 2>/dev/null');
        $which = $which ? trim($which) : null;

        return ($which && is_executable($which)) ? $which : null;
    }

    private function humanSize(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, 1) . ' ' . $unit;
            }
            $bytes /= 1024;
        }
        return round($bytes, 1) . ' TB';
    }
}
