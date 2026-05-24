<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentSequence;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;

class SingleCompanyReadinessCommand extends Command
{
    protected $signature = 'quoteflow:single-company-readiness
        {--strict : Return failure when warnings are found}
        {--sync-document-sequences : Raise document sequence rows to match existing document numbers before checking}';

    protected $description = 'Check whether QuoteFlow is ready for single-company UAT or production use.';

    private int $failures = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->line('QuoteFlow single-company readiness');
        $this->line('Environment: '.app()->environment());
        $this->newLine();

        $databaseReady = $this->checkDatabase();
        $this->checkApplicationSettings();
        $this->checkWritablePaths();
        $this->checkStaticAssets();
        $this->checkPdfRenderer();
        $this->checkDatabaseBackupTool();
        $this->checkFileBackupTool();

        if ($databaseReady && $this->checkCoreTables()) {
            $this->checkCompanyProfile();
            $this->checkUsers();
            $this->checkMasterData();
            $this->checkDocumentNumbering();
            $this->checkStoredFiles();
        }

        $this->checkOcrTools();
        $this->newLine();

        if ($this->failures > 0) {
            $this->error($this->failures.' readiness check(s) failed. Fix these before single-company go-live.');

            return self::FAILURE;
        }

        if ($this->warnings > 0) {
            $message = $this->warnings.' readiness warning(s) found.';

            if ($this->option('strict')) {
                $this->error($message.' Strict mode treats warnings as failures.');

                return self::FAILURE;
            }

            $this->warn($message.' Review them before final UAT sign-off.');

            return self::SUCCESS;
        }

        $this->info('All readiness checks passed.');

        return self::SUCCESS;
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            $this->ok('Database connection', 'Database connection is available.');

            return true;
        } catch (\Throwable $exception) {
            $this->fail('Database connection', $exception->getMessage());

            return false;
        }
    }

    private function checkApplicationSettings(): void
    {
        $this->check(
            'Application key',
            filled((string) config('app.key')),
            'APP_KEY is configured.',
            'APP_KEY is missing. Run php artisan key:generate before production use.'
        );

        $this->check(
            'Application URL',
            filled((string) config('app.url')) && ! str_contains((string) config('app.url'), 'example.com'),
            'APP_URL is configured as '.config('app.url').'.',
            'APP_URL is missing or still points to example.com.'
        );

        if (app()->environment('production')) {
            $this->check('Debug mode', ! (bool) config('app.debug'), 'APP_DEBUG is disabled.', 'APP_DEBUG must be false in production.');
            $this->check('Cache driver', config('cache.default') === 'file', 'CACHE_DRIVER uses file storage.', 'Use CACHE_DRIVER=file for shared hosting.');
            $this->check('Session driver', config('session.driver') === 'file', 'SESSION_DRIVER uses file storage.', 'Use SESSION_DRIVER=file for shared hosting.');
            $this->check('Queue connection', config('queue.default') === 'sync', 'QUEUE_CONNECTION is sync.', 'Use QUEUE_CONNECTION=sync for the single-company shared-hosting build.');
        } elseif (app()->environment('testing')) {
            $this->ok('Production settings', 'Production-only driver checks are skipped during automated tests.');
        } else {
            $this->warnCheck('Production settings', 'Run this command on the production environment after deployment as the final check.');
        }
    }

    private function checkWritablePaths(): void
    {
        foreach ([storage_path(), storage_path('framework/cache'), storage_path('framework/sessions'), storage_path('framework/views'), base_path('bootstrap/cache')] as $path) {
            $this->check(
                'Writable path: '.$this->relativePath($path),
                is_dir($path) && is_writable($path),
                'Directory is writable.',
                'Directory must exist and be writable by PHP.'
            );
        }
    }

    private function checkStaticAssets(): void
    {
        $this->check(
            'Compiled CSS',
            is_file(public_path('css/app.css')) && filesize(public_path('css/app.css')) > 0,
            'Compiled CSS exists at public/css/app.css.',
            'Compiled CSS is missing. Build locally and deploy public/css/app.css.'
        );
    }

    private function checkPdfRenderer(): void
    {
        $this->check(
            'PDF renderer',
            class_exists(\Barryvdh\DomPDF\Facade\Pdf::class),
            'DomPDF is installed.',
            'DomPDF is missing; document PDF preview/download will fail.'
        );
    }

    private function checkDatabaseBackupTool(): void
    {
        $connection = config('database.connections.'.config('database.default'), []);
        $driver = is_array($connection) ? (string) ($connection['driver'] ?? '') : '';

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->warnCheck('Database backup tool', 'Database backups require MySQL/MariaDB; current driver is '.$driver.'.');

            return;
        }

        $this->check(
            'Database backup tool',
            $this->databaseDumpBinaryAvailable(),
            'mysqldump is available for database backups.',
            'mysqldump was not found. Set MYSQLDUMP_PATH before production use.',
            'warn'
        );

        $directory = (string) config('quoteflow_backup.directory', storage_path('app/backups'));
        $parent = dirname($directory);

        $this->check(
            'Database backup directory',
            (is_dir($directory) && is_writable($directory)) || (is_dir($parent) && is_writable($parent)),
            'Backup directory can be written under '.$this->relativePath($directory).'.',
            'Backup directory or its parent is not writable: '.$directory,
            'warn'
        );
    }

    private function checkFileBackupTool(): void
    {
        $this->check(
            'File backup tool',
            class_exists(\ZipArchive::class),
            'PHP ZipArchive is available for uploaded file backups.',
            'PHP ZipArchive is missing. Enable the PHP zip extension before production use.',
            'warn'
        );

        $directory = (string) config('quoteflow_backup.files.directory', storage_path('app/backups'));
        $parent = dirname($directory);

        $this->check(
            'File backup directory',
            (is_dir($directory) && is_writable($directory)) || (is_dir($parent) && is_writable($parent)),
            'File backup directory can be written under '.$this->relativePath($directory).'.',
            'File backup directory or its parent is not writable: '.$directory,
            'warn'
        );
    }

    private function checkCoreTables(): bool
    {
        $allPresent = true;

        foreach (['users', 'company_profiles', 'customers', 'suppliers', 'products', 'documents', 'document_items', 'document_sequences', 'payments', 'approvals', 'attachments', 'attachment_extractions', 'audit_trails', 'document_billing_stages'] as $table) {
            $exists = Schema::hasTable($table);
            $allPresent = $allPresent && $exists;

            $this->check(
                'Database table: '.$table,
                $exists,
                'Table exists.',
                'Table is missing. Run migrations before use.'
            );
        }

        return $allPresent;
    }

    private function checkCompanyProfile(): void
    {
        $activeCount = CompanyProfile::query()->where('is_active', true)->count();
        $company = CompanyProfile::active();
        $fallbackName = CompanyProfile::defaults()['name'];

        $this->check('Active company profile', $activeCount === 1, 'Exactly one active company profile is configured.', 'Single-company use requires exactly one active company profile.');

        foreach ([
            'Company name' => $company->displayName() !== $fallbackName,
            'Company address' => filled($company->address),
            'Company email' => filled($company->email),
            'Company registration/tax reference' => filled($company->registration_number) || filled($company->tax_registration_number),
            'Payment instructions' => filled($company->payment_instructions),
        ] as $label => $ready) {
            $this->check($label, $ready, 'Configured.', 'Complete this company profile field before final UAT.', 'warn');
        }
    }

    private function checkUsers(): void
    {
        $this->check('Active admin user', User::query()->where('role', 'admin')->where('is_active', true)->exists(), 'Active admin user exists.', 'Create at least one active admin user.');

        foreach (['manager', 'sales', 'procurement', 'accounts', 'viewer'] as $role) {
            $this->check(
                'Active '.$role.' user',
                User::query()->where('role', $role)->where('is_active', true)->exists(),
                'User exists.',
                'Create an active '.$role.' user for full UAT coverage.',
                'warn'
            );
        }
    }

    private function checkMasterData(): void
    {
        $this->check('Active customers', Customer::query()->where('is_active', true)->exists(), 'At least one active customer exists.', 'Add at least one real customer before UAT.', 'warn');
        $this->check('Active suppliers', Supplier::query()->where('is_active', true)->exists(), 'At least one active supplier exists.', 'Add at least one real supplier before UAT.', 'warn');
        $this->check('Active products/services', Product::query()->where('is_active', true)->exists(), 'At least one active product or service exists.', 'Add products/services before document testing.', 'warn');
    }

    private function checkDocumentNumbering(): void
    {
        foreach (Document::TYPES as $meta) {
            $year = (int) now()->format('Y');
            $latestExisting = $this->latestExistingNumber($meta['type'], $meta['prefix'], $year);
            $sequenceNumber = (int) (DocumentSequence::query()
                ->where('type', $meta['type'])
                ->where('year', $year)
                ->value('last_number') ?? 0);
            $synced = false;

            if ($sequenceNumber < $latestExisting && $this->option('sync-document-sequences')) {
                DocumentSequence::query()->updateOrCreate(
                    [
                        'type' => $meta['type'],
                        'year' => $year,
                    ],
                    [
                        'last_number' => $latestExisting,
                    ]
                );

                $sequenceNumber = $latestExisting;
                $synced = true;
            }

            $this->check(
                'Document numbering: '.$meta['singular'],
                $sequenceNumber >= $latestExisting,
                $synced
                    ? 'Sequence was synced to existing '.$year.' documents.'
                    : ($latestExisting === 0 ? 'No existing '.$year.' documents require sequence sync.' : 'Sequence is aligned with existing '.$year.' documents.'),
                'Sequence is behind existing documents. Create the next document once after deployment or sync document_sequences before go-live.'
            );
        }
    }

    private function checkStoredFiles(): void
    {
        $missingAttachments = [];
        $missingAttachmentCount = 0;
        $attachmentCount = 0;

        Attachment::query()
            ->select(['id', 'path', 'original_name'])
            ->orderBy('id')
            ->chunkById(500, function ($attachments) use (&$attachmentCount, &$missingAttachments, &$missingAttachmentCount): void {
                foreach ($attachments as $attachment) {
                    $attachmentCount++;

                    if (filled($attachment->path) && Storage::disk('local')->exists($attachment->path)) {
                        continue;
                    }

                    $missingAttachmentCount++;

                    if (count($missingAttachments) < 5) {
                        $missingAttachments[] = '#'.$attachment->id.' '.$attachment->original_name.' ('.$attachment->path.')';
                    }
                }
            });

        $this->check(
            'Uploaded evidence files',
            $missingAttachments === [],
            $attachmentCount === 0
                ? 'No uploaded evidence files are recorded yet.'
                : $attachmentCount.' uploaded file record(s) point to files in storage.',
            $missingAttachmentCount.' uploaded file record(s) are missing from storage: '.implode('; ', $missingAttachments),
            'warn'
        );

        $missingLogos = [];
        $missingLogoCount = 0;
        $logoCount = 0;

        CompanyProfile::query()
            ->select(['id', 'name', 'logo_path'])
            ->whereNotNull('logo_path')
            ->where('logo_path', '<>', '')
            ->orderBy('id')
            ->chunkById(100, function ($companies) use (&$logoCount, &$missingLogos, &$missingLogoCount): void {
                foreach ($companies as $company) {
                    $logoCount++;

                    if (Storage::disk('public')->exists($company->logo_path)) {
                        continue;
                    }

                    $missingLogoCount++;

                    if (count($missingLogos) < 5) {
                        $missingLogos[] = '#'.$company->id.' '.$company->name.' ('.$company->logo_path.')';
                    }
                }
            });

        $this->check(
            'Company logo files',
            $missingLogos === [],
            $logoCount === 0
                ? 'No uploaded company logo files are configured.'
                : $logoCount.' company logo file(s) exist in storage.',
            $missingLogoCount.' company logo file(s) are missing from storage: '.implode('; ', $missingLogos),
            'warn'
        );
    }

    private function checkOcrTools(): void
    {
        if (! (bool) config('ocr.enabled')) {
            $this->ok('OCR setting', 'OCR is disabled; manual verification remains available.');

            return;
        }

        foreach ([
            'pdftotext' => (string) config('ocr.pdftotext_path', 'pdftotext'),
            'tesseract' => (string) config('ocr.tesseract_path', 'tesseract'),
        ] as $label => $binary) {
            $this->check(
                'OCR binary: '.$label,
                $this->binaryAvailable($binary),
                $binary.' is available.',
                $binary.' was not found. OCR may fail, but manual verification can still be used.',
                'warn'
            );
        }

        $ghostscript = (string) config('ocr.ghostscript_path', 'gswin64c');

        $this->check(
            'OCR binary: ghostscript',
            $this->binaryAvailable($ghostscript) || $this->binaryAvailable('gs') || $this->binaryAvailable('gswin64c') || $this->binaryAvailable('gswin32c'),
            $ghostscript.' is available.',
            $ghostscript.' was not found. PDF OCR conversion may fail, but uploaded PDFs can still be reviewed manually.',
            'warn'
        );
    }

    private function binaryAvailable(string $binary): bool
    {
        $binary = trim($binary);

        if ($binary === '') {
            return false;
        }

        if (is_file($binary)) {
            return true;
        }

        return (bool) (new ExecutableFinder())->find($binary);
    }

    private function databaseDumpBinaryAvailable(): bool
    {
        $binary = trim((string) config('quoteflow_backup.mysqldump_path', 'mysqldump'));

        if ($this->binaryAvailable($binary)) {
            return true;
        }

        if ($binary !== 'mysqldump') {
            return false;
        }

        foreach (glob('C:\laragon\bin\mysql\*\bin\mysqldump.exe') ?: [] as $candidate) {
            if (is_file($candidate)) {
                return true;
            }
        }

        return false;
    }

    private function latestExistingNumber(string $type, string $prefix, int $year): int
    {
        $latestNumber = Document::query()
            ->where('type', $type)
            ->where('document_number', 'like', $prefix.'-'.$year.'-%')
            ->orderByDesc('document_number')
            ->value('document_number');

        if (! is_string($latestNumber)) {
            return 0;
        }

        $sequence = substr($latestNumber, strrpos($latestNumber, '-') + 1);

        return ctype_digit($sequence) ? (int) $sequence : 0;
    }

    private function check(string $label, bool $condition, string $passMessage, string $failMessage, string $severity = 'fail'): void
    {
        if ($condition) {
            $this->ok($label, $passMessage);

            return;
        }

        if ($severity === 'warn') {
            $this->warnCheck($label, $failMessage);

            return;
        }

        $this->fail($label, $failMessage);
    }

    private function ok(string $label, string $message): void
    {
        $this->line('<info>OK</info>   '.$label.' - '.$message);
    }

    private function warnCheck(string $label, string $message): void
    {
        $this->warnings++;
        $this->line('<comment>WARN</comment> '.$label.' - '.$message);
    }

    private function fail(string $label, string $message): void
    {
        $this->failures++;
        $this->line('<error>FAIL</error> '.$label.' - '.$message);
    }

    private function relativePath(string $path): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);
    }
}
