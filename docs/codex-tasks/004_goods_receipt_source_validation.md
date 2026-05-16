# Task 004: Goods Receipt Source Validation

## Objective

Tighten goods receipt validation so normal receiving can only be recorded against an issued supplier purchase order, unless a direct receipt exception is explicitly used.

## Current problem

Goods receipt form copy says normal receiving is done against an issued PO, but backend validation should be stricter about related supplier PO status.

## Business rule

If `goods_receipt` uses `source_type = supplier_po`:

```text
related_document_id is required
related document type must be supplier_po
related document supplier must match selected supplier
related document status must be issued
```

If `goods_receipt` uses `source_type = direct_receipt`:

```text
source_note is required
related_document_id may be empty
```

## Files to inspect before editing

- `app/Http/Controllers/DocumentController.php`
- `app/Models/Document.php`
- `resources/views/documents/form.blade.php`
- `resources/views/documents/show.blade.php`
- `tests/Feature/`

## Expected implementation

- Enforce issued supplier PO requirement server-side.
- Keep the existing direct receipt exception path, but require source note.
- Update form/help copy if needed so users understand the rule.
- Keep goods receipt payment fields hidden as they are not payment documents.

## Tests required

Add or update feature tests covering:

- goods receipt against issued supplier PO succeeds
- goods receipt against draft supplier PO fails
- goods receipt against approved but unissued supplier PO fails
- goods receipt against unrelated supplier fails
- direct receipt without source note fails
- direct receipt with source note succeeds

## Acceptance criteria

- Backend and UI rules match.
- Goods receipt normal flow requires issued supplier PO.
- Direct receipt exception remains possible but audited/explained.
- No shared-hosting constraint is broken.

## Do not do in this task

- Do not implement full three-way invoice matching.
- Do not redesign supplier PO issuance.
- Do not add new infrastructure dependencies.
