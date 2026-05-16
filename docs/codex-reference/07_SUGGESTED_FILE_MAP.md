# Suggested File Map

This map helps Codex find the likely files for workflow changes.

## Routes

- `routes/web.php`

Add new routes here for pending approvals, manual verification, matching checks, or cancellation actions if needed.

## Main controllers

- `app/Http/Controllers/DocumentController.php`
- `app/Http/Controllers/PaymentController.php`
- `app/Http/Controllers/ReportController.php`
- `app/Http/Controllers/AuditTrailController.php`

Prefer not to make `DocumentController` much larger. For new business rules, add service classes and call them from the controller.

## Models

- `app/Models/Document.php`
- `app/Models/Attachment.php`
- `app/Models/AttachmentExtraction.php`
- `app/Models/Approval.php`
- `app/Models/Payment.php`
- `app/Models/AuditTrail.php`
- `app/Models/User.php`

## Suggested new services

Add under `app/Services` or `app/Support` depending on project style.

Suggested paths:

```text
app/Services/Documents/DocumentWorkflowService.php
app/Services/Documents/PaymentEligibilityService.php
app/Services/Invoices/SupplierInvoiceVerificationService.php
app/Services/Invoices/SupplierInvoiceMatchingService.php
app/Services/Documents/DirectExceptionPolicy.php
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
- `resources/views/documents/partials/receipt-live-preview.blade.php`
- `resources/views/documents/partials/quotation-live-preview.blade.php`

Suggested new partials:

```text
resources/views/documents/partials/supplier-invoice-verification-panel.blade.php
resources/views/documents/partials/supplier-invoice-matching-checklist.blade.php
resources/views/documents/partials/document-chain-timeline.blade.php
resources/views/documents/partials/cancel-document-form.blade.php
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
tests/Feature/SupplierInvoiceVerificationTest.php
tests/Feature/PaymentEligibilityTest.php
tests/Feature/SupplierInvoiceMatchingTest.php
tests/Feature/PendingApprovalsTest.php
tests/Feature/DirectExceptionWorkflowTest.php
tests/Feature/GoodsReceiptWorkflowTest.php
```

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
