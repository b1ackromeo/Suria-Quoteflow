# Task 006: Supplier Invoice Matching Checklist

## Objective

Make supplier invoice matching a real business control instead of only a status button.

## Current problem

The supplier invoice page can show `Mark matched`, and the backend can transition a supplier invoice to `matched`, but the current matching action does not clearly enforce supplier, source, duplicate invoice number, verification, or amount checks.

## Business rule

A supplier invoice can be marked `matched` only when a matching checklist passes, or when an authorized override is explicitly recorded.

Minimum checklist:

- supplier invoice details are verified
- source document exists unless `source_type = direct_supplier_invoice`
- related source document belongs to the same supplier
- supplier invoice number is not duplicated for the same supplier
- invoice total is checked against source document or manual tolerance rule
- direct/exception source has source note
- matching notes are required for overrides

## Files to inspect before editing

- `routes/web.php`
- `app/Http/Controllers/DocumentController.php`
- `app/Models/Document.php`
- `app/Models/AttachmentExtraction.php`
- `resources/views/documents/show.blade.php`
- `resources/views/documents/partials/supplier-invoice-file-preview.blade.php`
- `database/migrations/`
- `tests/Feature/`

## Expected implementation

- Add a small service, preferably `app/Services/Invoices/SupplierInvoiceMatchingService.php`.
- Service should return a structured checklist result with pass/fail items and messages.
- Show the checklist on supplier invoice show page.
- Hide or disable `Mark matched` until checklist passes.
- Enforce the same rule server-side in the transition action.
- If override is implemented, restrict it to admin/manager and require reason/audit.

## Tests required

Add or update feature tests covering:

- unverified supplier invoice cannot be matched
- supplier invoice with missing source cannot be matched unless valid direct exception
- supplier mismatch blocks matching
- duplicate supplier invoice number blocks or flags according to policy
- checklist appears on supplier invoice show page
- direct POST to match is rejected when checklist fails
- invoice can be matched when checklist passes
- override, if implemented, requires reason and audit trail

## Acceptance criteria

- `matched` status means business checks passed or an authorized override exists.
- UI and backend rules match.
- Matching checklist is visible and understandable.
- No shared-hosting constraint is broken.

## Do not do in this task

- Do not redesign all approval workflows.
- Do not implement payment eligibility unless not already done.
- Do not introduce background jobs or external matching services.
