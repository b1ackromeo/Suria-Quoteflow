# Task 005: Supplier Invoice Verification Fallback

## Objective

Keep OCR, but add a manual supplier invoice verification fallback so supplier invoice approval does not depend only on successful OCR.

## Current problem

Supplier invoice submission is blocked unless an invoice copy exists, OCR can extract it, and extracted fields are verified. If OCR fails due to scan quality or missing binary support, the workflow can be blocked.

## Business rule

Supplier invoice approval/submission should require verified invoice details, not strictly successful OCR.

Verification may be:

```text
ocr-assisted
manual
external
```

At minimum, verified supplier invoice details should include:

- supplier invoice number
- invoice date
- total or recorded total confirmation
- supplier confirmation
- verification notes when manual/external
- verified by
- verified at
- verification method

## Files to inspect before editing

- `routes/web.php`
- `app/Http/Controllers/DocumentController.php`
- `app/Models/Document.php`
- `app/Models/Attachment.php`
- `app/Models/AttachmentExtraction.php`
- `app/Services/Ocr/TesseractInvoiceExtractor.php`
- `resources/views/documents/show.blade.php`
- `resources/views/documents/partials/supplier-invoice-file-preview.blade.php`
- `database/migrations/`
- `tests/Feature/`

## Expected implementation

- Preserve the current OCR-assisted extraction and verification flow.
- Add a manual verification path for supplier invoices.
- Add or reuse a structured place to store manual verification method, fields, verifier, timestamp, and notes.
- Update `approvalBlockingIssues()` or equivalent rule so verified manual supplier invoice details satisfy the approval/submission requirement.
- Update the supplier invoice preview page so users understand OCR is an assistant, not the only path.
- Record audit events for manual verification.

## Suggested implementation direction

Prefer a small service:

```text
app/Services/Invoices/SupplierInvoiceVerificationService.php
```

The service should answer:

```text
isVerified(Document $document): bool
blockingIssues(Document $document): array
```

If schema change is needed, use MySQL/MariaDB-compatible nullable columns or a dedicated verification table. Do not overload unrelated fields in a confusing way.

## Tests required

Add or update feature tests covering:

- supplier invoice without invoice copy cannot be submitted
- supplier invoice with invoice copy but no verification cannot be submitted
- supplier invoice with OCR verified extraction can be submitted
- supplier invoice with OCR failure can be manually verified by authorized user
- manually verified supplier invoice can be submitted
- unauthorized user cannot manually verify
- audit trail records manual verification

## Acceptance criteria

- OCR remains available.
- Manual verification fallback works.
- Approval/submission checks depend on verified invoice details, not OCR success alone.
- Blade UI explains the path clearly.
- No queue, Redis, external OCR API, or VPS-only dependency is introduced.

## Do not do in this task

- Do not implement full supplier invoice matching checklist.
- Do not change payment eligibility except where necessary to keep tests passing.
- Do not remove OCR.
