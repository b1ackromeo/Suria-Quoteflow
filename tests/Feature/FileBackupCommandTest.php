<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;
use ZipArchive;

class FileBackupCommandTest extends TestCase
{
    public function test_file_backup_dry_run_reports_source_files(): void
    {
        [$backupDirectory, $attachmentSource, $logoSource] = $this->prepareFileBackupDirectories();
        File::put($attachmentSource.DIRECTORY_SEPARATOR.'invoice.pdf', 'supplier invoice');
        File::put($logoSource.DIRECTORY_SEPARATOR.'logo.png', 'logo');
        $this->configureFileBackup($backupDirectory, $attachmentSource, $logoSource);

        [$exitCode, $output] = $this->runFileBackupCommand([
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('File backup dry run passed.', $output);
        $this->assertStringContainsString('Files found: 2', $output);
        $this->assertSame([], glob($backupDirectory.DIRECTORY_SEPARATOR.'*.zip') ?: []);
    }

    public function test_file_backup_creates_and_verifies_archive(): void
    {
        [$backupDirectory, $attachmentSource, $logoSource] = $this->prepareFileBackupDirectories();
        File::ensureDirectoryExists($attachmentSource.DIRECTORY_SEPARATOR.'supplier-invoices');
        File::put($attachmentSource.DIRECTORY_SEPARATOR.'supplier-invoices'.DIRECTORY_SEPARATOR.'invoice.pdf', 'supplier invoice');
        File::put($logoSource.DIRECTORY_SEPARATOR.'logo.png', 'logo');
        $this->configureFileBackup($backupDirectory, $attachmentSource, $logoSource);

        [$createExitCode, $createOutput] = $this->runFileBackupCommand();
        [$verifyExitCode, $verifyOutput] = $this->runFileBackupCommand([
            '--verify-latest' => true,
        ]);

        $this->assertSame(0, $createExitCode);
        $this->assertStringContainsString('File backup created.', $createOutput);
        $this->assertStringContainsString('Archived files: 2', $createOutput);
        $this->assertStringContainsString('Verified: archive manifest and entries are readable.', $createOutput);
        $this->assertSame(0, $verifyExitCode);
        $this->assertStringContainsString('File backup verified.', $verifyOutput);
        $this->assertStringContainsString('Archived files: 2', $verifyOutput);
        $this->assertCount(1, glob($backupDirectory.DIRECTORY_SEPARATOR.'quoteflow-files-*.zip') ?: []);
    }

    public function test_file_backup_verify_fails_when_archive_is_missing_manifest(): void
    {
        [$backupDirectory, $attachmentSource, $logoSource] = $this->prepareFileBackupDirectories();
        $this->configureFileBackup($backupDirectory, $attachmentSource, $logoSource);
        $path = $backupDirectory.DIRECTORY_SEPARATOR.'quoteflow-files-testing-20260525-010101.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('attachments/invoice.pdf', 'supplier invoice');
        $zip->close();

        [$exitCode, $output] = $this->runFileBackupCommand([
            '--verify' => $path,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('File backup archive is missing its QuoteFlow manifest.', $output);
    }

    public function test_file_backup_verify_latest_fails_when_no_archive_exists(): void
    {
        [$backupDirectory, $attachmentSource, $logoSource] = $this->prepareFileBackupDirectories();
        $this->configureFileBackup($backupDirectory, $attachmentSource, $logoSource);

        [$exitCode, $output] = $this->runFileBackupCommand([
            '--verify-latest' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('No QuoteFlow file backups were found', $output);
    }

    private function prepareFileBackupDirectories(): array
    {
        $base = storage_path('framework/testing/file-backups');
        File::deleteDirectory($base);

        $backupDirectory = $base.DIRECTORY_SEPARATOR.'archives';
        $attachmentSource = $base.DIRECTORY_SEPARATOR.'sources'.DIRECTORY_SEPARATOR.'attachments';
        $logoSource = $base.DIRECTORY_SEPARATOR.'sources'.DIRECTORY_SEPARATOR.'company-logos';

        File::ensureDirectoryExists($backupDirectory);
        File::ensureDirectoryExists($attachmentSource);
        File::ensureDirectoryExists($logoSource);

        return [$backupDirectory, $attachmentSource, $logoSource];
    }

    private function configureFileBackup(string $backupDirectory, string $attachmentSource, string $logoSource): void
    {
        config([
            'quoteflow_backup.files.directory' => $backupDirectory,
            'quoteflow_backup.files.sources' => [
                [
                    'path' => $attachmentSource,
                    'prefix' => 'attachments',
                ],
                [
                    'path' => $logoSource,
                    'prefix' => 'public/company-logos',
                ],
            ],
        ]);
    }

    private function runFileBackupCommand(array $parameters = []): array
    {
        $this->withoutMockingConsoleOutput();

        $command = Artisan::all()['quoteflow:backup-files'];
        $tester = new CommandTester($command);
        $exitCode = $tester->execute($parameters, [
            'interactive' => false,
        ]);

        return [$exitCode, $tester->getDisplay()];
    }
}
