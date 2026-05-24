# Single-Company Readiness

Use this check before UAT sign-off and again after deploying to Exabytes/Plesk.

```powershell
php artisan quoteflow:single-company-readiness
```

For the final go-live gate, run strict mode:

```powershell
php artisan quoteflow:single-company-readiness --strict
```

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
- Core workflow database tables exist.
- Exactly one active company profile is configured.
- Company profile fields needed for PDFs and payments are filled.
- Active admin account exists, with warnings for missing UAT roles.
- Active customer, supplier, and product/service records exist.
- Document numbering sequences are not behind imported or demo records.
- OCR binaries are available when OCR is enabled, with manual verification still available as fallback.

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
- Confirm OCR availability on the target hosting only if OCR is enabled.
- Confirm a database backup/export process before production use.

## Current MVP Boundaries

- Approval is single-step.
- Reports are simple operational tables/CSV exports.
- Supplier invoice matching is checklist-driven; it is not a full inventory/accounting three-way match.
- OCR is optional. Manual verification remains the fallback path.
