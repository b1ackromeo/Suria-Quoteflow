# Task 008: Linked Workflow Timeline

## Objective

Improve the workflow timeline so it shows actual linked document records when available, while keeping the current generic workflow as fallback.

## Current problem

The current workflow timeline shows fixed process steps for outgoing or incoming workflows. It is useful visually, but it does not show the actual related document chain for the current record.

## Business rule

When documents are linked through `related_document_id`, the timeline should help users understand the real chain of records.

Examples:

```text
CQ-2026-00001 -> CPO-2026-00002 -> INV-2026-00003
PR-2026-00001 -> SQ-2026-00002 -> SPO-2026-00003 -> GR-2026-00004 -> SIN-2026-00005
```

If no chain can be discovered, keep the current generic timeline.

## Files to inspect before editing

- `app/Models/Document.php`
- `app/Http/Controllers/DocumentController.php`
- `resources/views/documents/show.blade.php`
- `resources/views/documents/partials/workflow-timeline.blade.php`
- `tests/Feature/`

## Expected implementation

- Add a small chain helper/service if needed, preferably `app/Services/Documents/DocumentChainService.php`.
- Discover upstream chain through `related_document_id`.
- Optionally discover direct downstream records where `related_document_id = current document id`.
- Avoid expensive recursive queries and unbounded traversal.
- Show document number, type label, status, and link to each record.
- Preserve generic timeline as fallback.

## Tests required

Add or update tests covering:

- timeline shows generic steps when no related chain exists
- timeline shows upstream chain when related documents exist
- timeline links to related documents
- chain traversal is bounded and does not loop indefinitely

## Acceptance criteria

- Timeline becomes operational when data exists.
- Generic timeline remains available.
- No N+1-heavy or unbounded queries are introduced.
- No shared-hosting constraint is broken.

## Do not do in this task

- Do not redesign the whole document show page.
- Do not add graph database/search dependencies.
- Do not change core document status vocabulary.
