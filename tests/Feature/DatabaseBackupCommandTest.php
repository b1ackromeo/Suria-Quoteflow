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
}
