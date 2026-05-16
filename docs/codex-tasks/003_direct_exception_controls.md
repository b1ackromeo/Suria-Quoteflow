# Task 003: Direct Exception Controls

## Objective

Require clear reason/audit context when users choose direct or exception workflow paths.

## Current problem

The form supports direct paths such as direct customer PO, direct customer invoice, direct supplier PO, direct receipt, and direct supplier invoice. These can bypass the normal quotation/PO/receipt chain without consistently requiring a reason.

## Business rule

When one of these source types is selected, `source_note` is required:

```text
direct_customer_po
direct_invoice
direct_supplier_po
direct_receipt
direct_supplier_invoice
```

The source note should explain why the normal workflow chain is not being used.

## Files to inspect before editing

- `app/Http/Controllers/DocumentController.php`
- `app/Models/Document.php`
- `resources/views/documents/form.blade.php`
- `resources/views/documents/show.blade.php`
- `tests/Feature/`

## Expected implementation

- Add backend validation requiring `source_note` for direct exception source types.
- Update form copy to clearly state that direct exception notes are required.
- Preserve normal source paths without requiring an exception note unless already required by business rule.
- Audit direct exception creation/update context where practical.
- Keep validation messages user-friendly.

## Tests required

Add or update feature tests covering:

- direct customer PO without source note fails
- direct customer invoice without source note fails
- direct supplier PO without source note fails
- direct receipt without source note fails
- direct supplier invoice without source note fails
- each direct source type succeeds with source note
- normal source path still works without exception note

## Acceptance criteria

- Direct exception paths cannot be saved silently without reason.
- Form copy matches backend validation.
- Audit trail preserves enough context to review why an exception path was used.
- No shared-hosting constraint is broken.

## Do not do in this task

- Do not redesign approvals.
- Do not implement matching checklist.
- Do not remove direct exception paths.
