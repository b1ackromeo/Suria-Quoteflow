<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

class DatabaseBackupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_dry_run_passes_when_mysqldump_and_directory_are_available(): void
    {
        $directory = storage_path('framework/testing/database-backups');
        File::deleteDirectory($directory);

        config([
            'quoteflow_backup.mysqldump_path' => PHP_BINARY,
            'quoteflow_backup.directory' => $directory,
        ]);

        [$exitCode, $output] = $this->runBackupCommand([
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Database backup dry run passed.', $output);
        $this->assertStringContainsString('mysqldump: '.PHP_BINARY, $output);
        $this->assertDirectoryExists($directory);
        $this->assertSame([], glob($directory.DIRECTORY_SEPARATOR.'*.sql') ?: []);
    }

    public function test_backup_dry_run_fails_when_mysqldump_is_missing(): void
    {
        config([
            'quoteflow_backup.mysqldump_path' => base_path('missing-mysqldump.exe'),
            'quoteflow_backup.directory' => storage_path('framework/testing/database-backups'),
        ]);

        [$exitCode, $output] = $this->runBackupCommand([
            '--dry-run' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Database backup failed. mysqldump was not found.', $output);
    }

    public function test_backup_dry_run_fails_for_non_mysql_connections(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
            'quoteflow_backup.mysqldump_path' => PHP_BINARY,
            'quoteflow_backup.directory' => storage_path('framework/testing/database-backups'),
        ]);

        [$exitCode, $output] = $this->runBackupCommand([
            '--dry-run' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Database backups require a MySQL or MariaDB connection. Current driver: sqlite.', $output);
    }

    public function test_verify_latest_backup_passes_for_valid_dump_file(): void
    {
        $directory = storage_path('framework/testing/database-backups');
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.'quoteflow-testing-20260525-010101.sql';
        File::put($path, $this->validMysqlDumpSql());

        config([
            'quoteflow_backup.directory' => $directory,
        ]);

        [$exitCode, $output] = $this->runBackupCommand([
            '--verify-latest' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Database backup verified.', $output);
        $this->assertStringContainsString('Tables: 14 required table(s) found.', $output);
    }

    public function test_verify_backup_fails_when_no_backup_file_exists(): void
    {
        $directory = storage_path('framework/testing/database-backups');
        File::deleteDirectory($directory);

        config([
            'quoteflow_backup.directory' => $directory,
        ]);

        [$exitCode, $output] = $this->runBackupCommand([
            '--verify-latest' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('No QuoteFlow SQL backups were found', $output);
    }

    public function test_verify_backup_fails_when_required_table_is_missing(): void
    {
        $directory = storage_path('framework/testing/database-backups');
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.'quoteflow-testing-20260525-010101.sql';
        File::put($path, str_replace("CREATE TABLE `payments` (\n", '', $this->validMysqlDumpSql()));

        config([
            'quoteflow_backup.directory' => $directory,
        ]);

        [$exitCode, $output] = $this->runBackupCommand([
            '--verify' => $path,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Backup file is missing required table(s): payments.', $output);
    }

    private function runBackupCommand(array $parameters = []): array
    {
        $this->withoutMockingConsoleOutput();

        $command = Artisan::all()['quoteflow:backup-database'];
        $tester = new CommandTester($command);
        $exitCode = $tester->execute($parameters, [
            'interactive' => false,
        ]);

        return [$exitCode, $tester->getDisplay()];
    }

    private function validMysqlDumpSql(): string
    {
        $tables = [
            'users',
            'company_profiles',
            'customers',
            'suppliers',
            'products',
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

        $sql = "-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)\n";
        $sql .= "--\n";
        $sql .= "-- Host: 127.0.0.1    Database: quoteflow_testing\n";

        foreach ($tables as $table) {
            $sql .= "--\n";
            $sql .= "-- Table structure for table `".$table."`\n";
            $sql .= "--\n";
            $sql .= "CREATE TABLE `".$table."` (\n";
            $sql .= "  `id` bigint unsigned NOT NULL AUTO_INCREMENT\n";
            $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";
        }

        $sql .= "-- Dump completed on 2026-05-25 01:01:01\n";

        return $sql;
    }
}
