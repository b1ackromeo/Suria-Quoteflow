<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use ZipArchive;

class FileBackupCommand extends Command
{
    protected $signature = 'quoteflow:backup-files
        {--dry-run : Verify file backup settings without creating an archive}
        {--verify= : Verify a specific file backup zip without extracting it}
        {--verify-latest : Verify the newest file backup zip without extracting it}';

    protected $description = 'Create a private zip backup of uploaded QuoteFlow files.';

    private const MANIFEST_PATH = '.quoteflow-file-backup.json';

    public function handle(): int
    {
        try {
            $this->ensureZipAvailable();

            if ($this->option('verify') && $this->option('verify-latest')) {
                throw new RuntimeException('Use either --verify or --verify-latest, not both.');
            }

            $backupDirectory = $this->ensureBackupDirectory();

            if ($this->option('verify') || $this->option('verify-latest')) {
                $backupPath = $this->option('verify')
                    ? $this->resolveBackupFilePath((string) $this->option('verify'))
                    : $this->latestBackupPath($backupDirectory);

                $manifest = $this->verifyArchive($backupPath);
                $this->info('File backup verified.');
                $this->line('File: '.$backupPath);
                $this->line('Size: '.$this->formatBytes((int) filesize($backupPath)));
                $this->line('Archived files: '.$manifest['file_count']);

                return self::SUCCESS;
            }

            $files = $this->collectFiles();
            $totalBytes = array_sum(array_column($files, 'size'));

            if ($this->option('dry-run')) {
                $this->info('File backup dry run passed.');
                $this->line('ZipArchive: available');
                $this->line('Backup directory: '.$backupDirectory);
                $this->line('Files found: '.count($files));
                $this->line('Total size: '.$this->formatBytes($totalBytes));

                return self::SUCCESS;
            }

            $backupPath = $this->backupPath($backupDirectory);
            $this->createArchive($backupPath, $files, $totalBytes);
            $manifest = $this->verifyArchive($backupPath);

            $this->info('File backup created.');
            $this->line('File: '.$backupPath);
            $this->line('Size: '.$this->formatBytes((int) filesize($backupPath)));
            $this->line('Archived files: '.$manifest['file_count']);
            $this->line('Verified: archive manifest and entries are readable.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('File backup failed. '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function ensureZipAvailable(): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive is not available. Enable the PHP zip extension before backing up uploaded files.');
        }
    }

    private function ensureBackupDirectory(): string
    {
        $directory = (string) config('quoteflow_backup.files.directory', storage_path('app/backups'));

        if ($directory === '') {
            throw new RuntimeException('FILE_BACKUP_DIR is empty.');
        }

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create file backup directory: '.$directory);
        }

        if (! is_writable($directory)) {
            throw new RuntimeException('File backup directory is not writable: '.$directory);
        }

        return $directory;
    }

    private function collectFiles(): array
    {
        $files = [];

        foreach ($this->sourcePaths() as $source) {
            $root = rtrim((string) ($source['path'] ?? ''), DIRECTORY_SEPARATOR.'/\\');
            $prefix = trim(str_replace('\\', '/', (string) ($source['prefix'] ?? '')), '/');

            if ($root === '' || $prefix === '') {
                throw new RuntimeException('File backup source path and prefix are required.');
            }

            if (! is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file instanceof \SplFileInfo || ! $file->isFile()) {
                    continue;
                }

                if ($file->getFilename() === '.gitignore') {
                    continue;
                }

                $absolutePath = $file->getPathname();
                $relativePath = ltrim(str_replace('\\', '/', substr($absolutePath, strlen($root))), '/');
                $archivePath = $prefix.'/'.$relativePath;

                $files[$archivePath] = [
                    'absolute' => $absolutePath,
                    'archive' => $archivePath,
                    'size' => $file->getSize(),
                ];
            }
        }

        ksort($files);

        return array_values($files);
    }

    private function sourcePaths(): array
    {
        $sources = config('quoteflow_backup.files.sources', []);

        if (! is_array($sources) || $sources === []) {
            throw new RuntimeException('No file backup sources are configured.');
        }

        return $sources;
    }

    private function createArchive(string $backupPath, array $files, int $totalBytes): void
    {
        $zip = new ZipArchive();

        if ($zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create file backup archive: '.$backupPath);
        }

        foreach ($files as $file) {
            if (! $zip->addFile($file['absolute'], $file['archive'])) {
                $zip->close();
                @unlink($backupPath);

                throw new RuntimeException('Cannot add file to archive: '.$file['archive']);
            }
        }

        $manifest = [
            'format' => 'quoteflow-file-backup-v1',
            'created_at' => now()->toIso8601String(),
            'environment' => app()->environment(),
            'app_url' => (string) config('app.url'),
            'file_count' => count($files),
            'total_bytes' => $totalBytes,
            'files' => array_map(
                fn (array $file): array => [
                    'path' => $file['archive'],
                    'size' => $file['size'],
                ],
                $files
            ),
        ];

        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (! is_string($manifestJson) || ! $zip->addFromString(self::MANIFEST_PATH, $manifestJson)) {
            $zip->close();
            @unlink($backupPath);

            throw new RuntimeException('Cannot add QuoteFlow manifest to file backup archive.');
        }

        if (! $zip->close()) {
            @unlink($backupPath);

            throw new RuntimeException('Cannot finalize file backup archive: '.$backupPath);
        }
    }

    private function resolveBackupFilePath(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            throw new RuntimeException('File backup path is empty.');
        }

        if (! preg_match('/^(?:[A-Za-z]:[\/\\\\]|[\/\\\\])/', $path)) {
            $path = base_path($path);
        }

        return $path;
    }

    private function latestBackupPath(string $backupDirectory): string
    {
        $files = glob(rtrim($backupDirectory, DIRECTORY_SEPARATOR.'/\\').DIRECTORY_SEPARATOR.'quoteflow-files-*.zip') ?: [];

        if ($files === []) {
            throw new RuntimeException('No QuoteFlow file backups were found in '.$backupDirectory.'.');
        }

        usort($files, fn (string $left, string $right): int => filemtime($right) <=> filemtime($left));

        return $files[0];
    }

    private function backupPath(string $backupDirectory): string
    {
        $environment = preg_replace('/[^A-Za-z0-9_-]+/', '-', app()->environment()) ?: 'app';

        return rtrim($backupDirectory, DIRECTORY_SEPARATOR.'/\\')
            .DIRECTORY_SEPARATOR
            .'quoteflow-files-'.$environment.'-'.now()->format('Ymd-His').'.zip';
    }

    private function verifyArchive(string $backupPath): array
    {
        if (! is_file($backupPath) || ! is_readable($backupPath)) {
            throw new RuntimeException('File backup archive is not readable: '.$backupPath);
        }

        if (filesize($backupPath) === 0) {
            throw new RuntimeException('File backup archive is empty: '.$backupPath);
        }

        $zip = new ZipArchive();

        if ($zip->open($backupPath) !== true) {
            throw new RuntimeException('File backup archive cannot be opened: '.$backupPath);
        }

        $manifestJson = $zip->getFromName(self::MANIFEST_PATH);

        if (! is_string($manifestJson) || $manifestJson === '') {
            $zip->close();

            throw new RuntimeException('File backup archive is missing its QuoteFlow manifest.');
        }

        $manifest = json_decode($manifestJson, true);

        if (! is_array($manifest) || ($manifest['format'] ?? null) !== 'quoteflow-file-backup-v1') {
            $zip->close();

            throw new RuntimeException('File backup manifest is not valid.');
        }

        $files = $manifest['files'] ?? [];

        if (! is_array($files) || (int) ($manifest['file_count'] ?? -1) !== count($files)) {
            $zip->close();

            throw new RuntimeException('File backup manifest file count does not match its entries.');
        }

        foreach ($files as $file) {
            $path = (string) ($file['path'] ?? '');
            $expectedSize = (int) ($file['size'] ?? -1);
            $stat = $zip->statName($path);

            if (! is_array($stat)) {
                $zip->close();

                throw new RuntimeException('File backup archive is missing entry: '.$path);
            }

            if ((int) ($stat['size'] ?? -1) !== $expectedSize) {
                $zip->close();

                throw new RuntimeException('File backup archive entry size changed: '.$path);
            }
        }

        $zip->close();

        return $manifest;
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
