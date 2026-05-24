# Single-Company Readiness

Use this check before UAT sign-off and again after deploying to Exabytes/Plesk.

```powershell
php artisan quoteflow:single-company-readiness
```

For the final go-live gate, run strict mode:

```powershell
php artisan quoteflow:single-company-readiness --strict
```

After OCR paths are configured, run a real OCR smoke test:

```powershell
php artisan quoteflow:ocr-smoke-test
```

The smoke test creates a small temporary supplier invoice image, runs QuoteFlow's document capture through the configured OCR tools, checks that key invoice fields are extracted, and deletes the temporary file.

Before UAT data changes or production handover, verify and create a database backup:

```powershell
php artisan quoteflow:backup-database --dry-run
php artisan quoteflow:backup-database
php artisan quoteflow:backup-database --verify-latest
php artisan quoteflow:backup-files --dry-run
php artisan quoteflow:backup-files
php artisan quoteflow:backup-files --verify-latest
```

The backup command uses the configured MySQL/MariaDB connection and writes a private SQL dump under `storage/app/backups` by default. The verify mode checks the SQL dump header, completion marker, and required QuoteFlow tables without restoring or modifying any database.

The file backup command writes a private zip archive of uploaded document evidence from `storage/app/attachments` and uploaded company logos from `storage/app/public/company-logos`. The verify mode checks the archive manifest and entries without extracting files.

If imported or demo documents already exist and `document_sequences` is behind them, sync the counters upward:

```powershell
php artisan quoteflow:single-company-readiness --sync-document-sequences
```

This only raises sequence counters to the latest existing document number for the current year. It does not rename documents or lower counters.

For the local Laragon OCR setup, point `.env` at the Laragon-owned binaries:

```env
PDFTOTEXT_PATH=C:/laragon/bin/ocr/poppler/poppler-25.07.0/Library/bin/pdftotext.exe
TESSERACT_PATH=C:/laragon/bin/ocr/tesseract/tesseract.exe
GHOSTSCRIPT_PATH=C:/laragon/bin/ocr/ghostscript/bin/gswin64c.exe
MYSQLDUMP_PATH=C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqldump.exe
```

On this Laragon machine, the project URL should be:

```text
http://suria-quoteflow.test
```

## What The Command Checks

- Laravel app key and app URL are configured.
- Production shared-hosting settings use file cache, file sessions, and sync queue.
- Storage and bootstrap cache directories are writable.
- Prebuilt CSS exists at `public/css/app.css`.
- DomPDF is installed for generated business documents.
- `mysqldump` is available and the database backup directory can be written.
- PHP ZipArchive is available and the uploaded-file backup directory can be written.
- Core workflow database tables exist.
- Exactly one active company profile is configured.
- Company profile fields needed for PDFs and payments are filled.
- Active admin account exists, with warnings for missing UAT roles.
- Active customer, supplier, and product/service records exist.
- Document numbering sequences are not behind imported or demo records.
- OCR binaries are available when OCR is enabled, with manual verification still available as fallback.
- OCR smoke test can read a generated supplier invoice image through the Laravel runtime.

## Result Meaning

- `OK`: ready for that check.
- `WARN`: usable, but review before final UAT sign-off.
- `FAIL`: fix before go-live.

Strict mode returns failure when any warning is present. Use strict mode for the final single-company go-live decision.

## Manual Checks Still Required

The command does not replace business UAT. Before saying the single-company setup is 100% ready:

- Confirm real company profile, logo, tax label, payment instructions, and footer text.
- Import or key in the real opening customers, suppliers, products/services, and active documents.
- Run the workflow checklist in `docs/END_USER_WORKFLOW_TESTING.md`.
- Preview and download one real customer quotation, customer invoice, purchase request, purchase order, goods receipt, and supplier invoice.
- Upload and preview at least one PDF and one image attachment.
- Run `php artisan quoteflow:ocr-smoke-test` on Laragon and again on production if OCR is enabled there.
- Confirm OCR availability on the target hosting only if OCR is enabled.
- Run `php artisan quoteflow:backup-database --dry-run`, create one real database backup, and verify it with `php artisan quoteflow:backup-database --verify-latest`.
- Run `php artisan quoteflow:backup-files --dry-run`, create one real uploaded-file backup, and verify it with `php artisan quoteflow:backup-files --verify-latest` before production use.

## Current MVP Boundaries

- Approval is single-step.
- Reports are simple operational tables/CSV exports.
- Supplier invoice matching is checklist-driven; it is not a full inventory/accounting three-way match.
- OCR is optional. Manual verification remains the fallback path.
