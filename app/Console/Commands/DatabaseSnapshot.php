<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DatabaseSnapshot extends Command
{
    protected $signature = 'db:snapshot
                            {--output= : Output file path (default: storage/snapshots/snapshot_<timestamp>.sql)}
                            {--connection= : Database connection to use (default: DB_CONNECTION)}';

    protected $description = 'Export all tables and data to a single SQL file for import into a new database';

    public function handle(): int
    {
        $connection = $this->option('connection') ?: config('database.default');
        $config = config("database.connections.{$connection}");

        if (!$config) {
            $this->error("Unknown connection: {$connection}");
            return 1;
        }

        if ($config['driver'] !== 'pgsql') {
            $this->error("Only PostgreSQL connections are supported. Driver: {$config['driver']}");
            return 1;
        }

        $outputPath = $this->option('output') ?: storage_path('snapshots/snapshot_' . now()->format('Y-m-d_His') . '.sql');

        $dir = dirname($outputPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $this->error("Failed to create output directory: {$dir}");
            return 1;
        }

        $host     = $config['host'] ?? '127.0.0.1';
        $port     = $config['port'] ?? 5432;
        $database = $config['database'];
        $username = $config['username'];
        $password = $config['password'] ?? '';

        $pgDump = $this->findBinary('pg_dump');
        if (!$pgDump) {
            $this->error('pg_dump not found. Make sure PostgreSQL client tools are installed.');
            return 1;
        }

        $cmd = sprintf(
            'PGPASSWORD=%s %s --host=%s --port=%s --username=%s --no-password --clean --if-exists --no-owner --no-acl --format=plain %s',
            escapeshellarg($password),
            escapeshellarg($pgDump),
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($username),
            escapeshellarg($database)
        );

        $this->info("Connecting to {$username}@{$host}:{$port}/{$database} …");

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $outputPath, 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            $this->error('Failed to start pg_dump process.');
            return 1;
        }

        fclose($pipes[0]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $this->error("pg_dump failed (exit {$exitCode}):");
            if ($stderr) {
                $this->error(trim($stderr));
            }
            if (file_exists($outputPath)) {
                unlink($outputPath);
            }
            return 1;
        }

        $size = $this->humanSize(filesize($outputPath));
        $this->info("Snapshot saved to: {$outputPath} ({$size})");
        $this->line('');
        $this->line('To import into a new database run:');
        $this->line("  psql -h <host> -U <user> -d <dbname> -f {$outputPath}");

        return 0;
    }

    private function findBinary(string $name): ?string
    {
        // Prefer newer versioned binaries first (server is v15+)
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

        // Fall back to PATH lookup
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
