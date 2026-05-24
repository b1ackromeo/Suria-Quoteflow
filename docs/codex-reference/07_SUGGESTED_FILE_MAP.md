# Suggested File Map

This map helps Codex find the likely files for workflow changes.

## Routes

- `routes/web.php`

Current workflow routes include document CRUD, transitions, global pending approvals, attachment preview/download, attachment extraction verification, PDF preview/download, CSV export, and cancel actions.

## Main controllers

- `app/Http/Controllers/DocumentController.php`
- `app/Http/Controllers/DocumentPdfController.php`
- `app/Http/Controllers/PaymentController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/CompanyProfileController.php`
- `app/Http/Controllers/ApprovalController.php`
- `app/Http/Controllers/ReportController.php`
- `app/Http/Controllers/SearchController.php`
- `app/Http/Controllers/AuditTrailController.php`

Prefer not to make `DocumentController` much larger. For new business rules, add service classes and call them from the controller.

## Models

- `app/Models/Document.php`
- `app/Models/CompanyProfile.php`
- `app/Models/Attachment.php`
- `app/Models/AttachmentExtraction.php`
- `app/Models/Approval.php`
- `app/Models/Payment.php`
- `app/Models/AuditTrail.php`
- `app/Models/User.php`

## Current service files

Add under `app/Services` or `app/Support` depending on project style.

Current paths:

```text
app/Services/Documents/PaymentEligibilityService.php
app/Services/Documents/DocumentChainService.php
app/Services/Documents/DocumentConversionService.php
app/Services/Documents/BusinessDocumentCaptureService.php
app/Services/Documents/ExternalDocumentExtractionService.php
app/Services/Documents/PaddleOcrDocumentAnalyzer.php
app/Services/Invoices/SupplierInvoiceVerificationService.php
app/Services/Invoices/SupplierInvoiceMatchingService.php
app/Services/Ocr/TesseractInvoiceExtractor.php
```

Keep services synchronous and dependency-light.

## Main document views

- `resources/views/documents/index.blade.php`
- `resources/views/documents/form.blade.php`
- `resources/views/documents/show.blade.php`

## Important partials

- `resources/views/documents/partials/workflow-timeline.blade.php`
- `resources/views/documents/partials/supplier-invoice-file-preview.blade.php`
- `resources/views/documents/partials/generated-pdf-output-preview.blade.php`
- `resources/views/documents/partials/external-document-preview.blade.php`
- `resources/views/documents/partials/external-document-capture-lines.blade.php`
- `resources/views/documents/partials/receipt-live-preview.blade.php`
- `resources/views/documents/partials/quotation-live-preview.blade.php`
- `resources/views/documents/partials/quotation-preview.blade.php`
- `resources/views/documents/partials/business-document-header.blade.php`
- `resources/views/documents/partials/cancel-document-form.blade.php`
- `resources/views/company_profiles/partials/logo-mark.blade.php`

If more workflow panels are split out later, prefer partial names based on the user-facing task, for example:

```text
resources/views/documents/partials/supplier-invoice-verification-panel.blade.php
resources/views/documents/partials/supplier-invoice-matching-checklist.blade.php
```

## Layout/navigation

- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/partials/navigation.blade.php`

The pending approval bell currently lives in `layouts/app.blade.php`.

## Payment views

- `resources/views/payments/index.blade.php`
- `resources/views/payments/form.blade.php`

Payment eligibility changes should affect both `documents/show.blade.php` and `payments/form.blade.php`.

## Config

- `config/ocr.php`
- `.env.example`

If OCR fallback is implemented, update config/docs only if needed.

## Migrations

Look under:

- `database/migrations/`

Potential new migrations:

- add verification fields to supplier invoice / attachment extraction records
- add cancellation reason to documents or audit payloads
- add matching metadata if the checklist stores structured result

Prefer minimal schema changes where safe, but do not overload unrelated fields in a way that will become confusing.

## Tests

Likely locations:

- `tests/Feature/`
- `tests/Unit/`

Suggested new tests:

```text
tests/Feature/DocumentWorkflowTest.php
tests/Feature/DocumentPdfCompanyTextTest.php
tests/Feature/DocumentLocalizationDefaultsTest.php
tests/Feature/CompanyProfileSettingsTest.php
tests/Feature/ExternalDocumentPreviewFormattingTest.php
```

The broad workflow rules are currently covered in `DocumentWorkflowTest.php`; add focused files only when a new behavior becomes large enough to deserve its own test class.

## Search tips for Codex

Search these terms before editing:

```text
approvalBlockingIssues
canMarkMatched
Record payment
attachments.extract
attachment-extractions.verify
source_type
direct_supplier_invoice
direct_receipt
workflow-timeline
pending_approval
```
