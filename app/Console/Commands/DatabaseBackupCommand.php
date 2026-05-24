<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'quoteflow:backup-database
        {--dry-run : Verify backup settings without creating a dump file}';

    protected $description = 'Create a MySQL/MariaDB database backup using mysqldump.';

    public function handle(): int
    {
        try {
            $connectionName = (string) config('database.default');
            $connection = $this->connectionConfig($connectionName);
            $mysqldump = $this->resolveMysqldumpPath();
            $backupDirectory = $this->ensureBackupDirectory();

            DB::connection($connectionName)->getPdo();

            if ($this->option('dry-run')) {
                $this->info('Database backup dry run passed.');
                $this->line('Connection: '.$connectionName);
                $this->line('Database: '.$connection['database']);
                $this->line('mysqldump: '.$mysqldump);
                $this->line('Backup directory: '.$backupDirectory);

                return self::SUCCESS;
            }

            $backupPath = $this->backupPath($backupDirectory);
            $process = $this->dumpProcess($mysqldump, $connection, $backupPath);
            $process->run();

            if (! $process->isSuccessful()) {
                @unlink($backupPath);

                throw new RuntimeException(trim($process->getErrorOutput()) ?: 'mysqldump did not complete successfully.');
            }

            if (! is_file($backupPath) || filesize($backupPath) === 0) {
                @unlink($backupPath);

                throw new RuntimeException('mysqldump finished, but the backup file was not created.');
            }

            $this->info('Database backup created.');
            $this->line('File: '.$backupPath);
            $this->line('Size: '.$this->formatBytes((int) filesize($backupPath)));

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Database backup failed. '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function connectionConfig(string $connectionName): array
    {
        $connection = config('database.connections.'.$connectionName);

        if (! is_array($connection)) {
            throw new RuntimeException('Database connection ['.$connectionName.'] is not configured.');
        }

        $driver = (string) ($connection['driver'] ?? '');

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Database backups require a MySQL or MariaDB connection. Current driver: '.$driver.'.');
        }

        foreach (['database', 'username'] as $key) {
            if (blank($connection[$key] ?? null)) {
                throw new RuntimeException('Database connection ['.$connectionName.'] is missing '.$key.'.');
            }
        }

        return $connection;
    }

    private function resolveMysqldumpPath(): string
    {
        $configured = trim((string) config('quoteflow_backup.mysqldump_path', 'mysqldump'));

        if ($configured === '') {
            throw new RuntimeException('MYSQLDUMP_PATH is empty.');
        }

        if (is_file($configured)) {
            return $configured;
        }

        $found = (new ExecutableFinder())->find($configured);

        if ($found) {
            return $found;
        }

        if ($configured === 'mysqldump') {
            $laragonCandidates = glob('C:\laragon\bin\mysql\*\bin\mysqldump.exe') ?: [];
            rsort($laragonCandidates, SORT_NATURAL);

            foreach ($laragonCandidates as $candidate) {
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        throw new RuntimeException('mysqldump was not found. Set MYSQLDUMP_PATH in .env.');
    }

    private function ensureBackupDirectory(): string
    {
        $directory = (string) config('quoteflow_backup.directory', storage_path('app/backups'));

        if ($directory === '') {
            throw new RuntimeException('DATABASE_BACKUP_DIR is empty.');
        }

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create backup directory: '.$directory);
        }

        if (! is_writable($directory)) {
            throw new RuntimeException('Backup directory is not writable: '.$directory);
        }

        return $directory;
    }

    private function backupPath(string $backupDirectory): string
    {
        $environment = preg_replace('/[^A-Za-z0-9_-]+/', '-', app()->environment()) ?: 'app';

        return rtrim($backupDirectory, DIRECTORY_SEPARATOR.'/\\')
            .DIRECTORY_SEPARATOR
            .'quoteflow-'.$environment.'-'.now()->format('Ymd-His').'.sql';
    }

    private function dumpProcess(string $mysqldump, array $connection, string $backupPath): Process
    {
        $arguments = [
            $mysqldump,
            '--default-character-set=utf8mb4',
            '--single-transaction',
            '--quick',
            '--no-tablespaces',
            '--result-file='.$backupPath,
            '--user='.(string) $connection['username'],
        ];

        if (filled($connection['host'] ?? null)) {
            $arguments[] = '--host='.(string) $connection['host'];
        }

        if (filled($connection['port'] ?? null)) {
            $arguments[] = '--port='.(string) $connection['port'];
        }

        if (filled($connection['unix_socket'] ?? null)) {
            $arguments[] = '--socket='.(string) $connection['unix_socket'];
        }

        $arguments[] = (string) $connection['database'];

        $environment = null;

        if (filled($connection['password'] ?? null)) {
            $environment = ['MYSQL_PWD' => (string) $connection['password']];
        }

        $process = new Process($arguments, base_path(), $environment);
        $process->setTimeout((int) config('quoteflow_backup.timeout', 300));

        return $process;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }
}
