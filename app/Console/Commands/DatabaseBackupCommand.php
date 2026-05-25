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
        {--dry-run : Verify backup settings without creating a dump file}
        {--verify= : Verify a specific backup SQL file without restoring it}
        {--verify-latest : Verify the newest backup SQL file without restoring it}';

    protected $description = 'Create a MySQL/MariaDB database backup using mysqldump.';

    private const REQUIRED_TABLES = [
        'users',
        'company_profiles',
        'customers',
        'suppliers',
        'products',
        'projects',
        'wbs_items',
        'documents',
        'document_items',
        'document_sequences',
        'payments',
        'approvals',
        'attachments',
        'attachment_extractions',
        'audit_trails',
        'document_billing_stages',
    ];

    public function handle(): int
    {
        try {
            if ($this->option('verify') && $this->option('verify-latest')) {
                throw new RuntimeException('Use either --verify or --verify-latest, not both.');
            }

            if ($this->option('verify') || $this->option('verify-latest')) {
                $backupDirectory = $this->ensureBackupDirectory();
                $backupPath = $this->option('verify')
                    ? $this->resolveBackupFilePath((string) $this->option('verify'))
                    : $this->latestBackupPath($backupDirectory);

                $this->verifyBackupFile($backupPath);
                $this->info('Database backup verified.');
                $this->line('File: '.$backupPath);
                $this->line('Size: '.$this->formatBytes((int) filesize($backupPath)));
                $this->line('Tables: '.count(self::REQUIRED_TABLES).' required table(s) found.');

                return self::SUCCESS;
            }

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

            $this->verifyBackupFile($backupPath);
            $this->info('Database backup created.');
            $this->line('File: '.$backupPath);
            $this->line('Size: '.$this->formatBytes((int) filesize($backupPath)));
            $this->line('Verified: '.count(self::REQUIRED_TABLES).' required table(s) found.');

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

    private function resolveBackupFilePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            throw new RuntimeException('Backup file path is empty.');
        }

        if (! preg_match('/^(?:[A-Za-z]:[\/\\\\]|[\/\\\\])/', $path)) {
            $path = base_path($path);
        }

        return $path;
    }

    private function latestBackupPath(string $backupDirectory): string
    {
        $files = glob(rtrim($backupDirectory, DIRECTORY_SEPARATOR.'/\\').DIRECTORY_SEPARATOR.'quoteflow-*.sql') ?: [];

        if ($files === []) {
            throw new RuntimeException('No QuoteFlow SQL backups were found in '.$backupDirectory.'.');
        }

        usort($files, fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));

        return $files[0];
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

    private function verifyBackupFile(string $backupPath): void
    {
        if (! is_file($backupPath) || ! is_readable($backupPath)) {
            throw new RuntimeException('Backup file is not readable: '.$backupPath);
        }

        if (filesize($backupPath) === 0) {
            throw new RuntimeException('Backup file is empty: '.$backupPath);
        }

        $foundTables = [];
        $hasMysqlHeader = false;
        $hasCompletionMarker = false;
        $file = new \SplFileObject($backupPath, 'r');

        while (! $file->eof()) {
            $line = (string) $file->fgets();

            if (! $hasMysqlHeader && str_starts_with($line, '-- MySQL dump')) {
                $hasMysqlHeader = true;
            }

            if (! $hasCompletionMarker && str_starts_with($line, '-- Dump completed on')) {
                $hasCompletionMarker = true;
            }

            if (preg_match('/^CREATE TABLE `([^`]+)` /', $line, $matches)) {
                $foundTables[$matches[1]] = true;
            }
        }

        if (! $hasMysqlHeader) {
            throw new RuntimeException('Backup file does not look like a mysqldump SQL file.');
        }

        if (! $hasCompletionMarker) {
            throw new RuntimeException('Backup file is missing the mysqldump completion marker.');
        }

        $missingTables = array_values(array_diff(self::REQUIRED_TABLES, array_keys($foundTables)));

        if ($missingTables !== []) {
            throw new RuntimeException('Backup file is missing required table(s): '.implode(', ', $missingTables).'.');
        }
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
