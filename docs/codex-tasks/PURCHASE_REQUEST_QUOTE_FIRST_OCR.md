# Purchase Request quote-first OCR

Use this file as the focused task contract for Purchase Request supplier quotation OCR work.

## Source of truth

Reference docs override current implementation. Current code may be wrong.

Read first:
- AGENTS.md
- docs/codex-reference/09_SYSTEM_ARCHITECTURE.md
- this file

## Workflow

Supplier quotation comes before Purchase Request line items.

Flow:
1. User starts Purchase Request from a supplier quotation PDF or image.
2. File is attached as category supplier_quote.
3. OCR reads the file using supplier quotation parsing.
4. User verifies or corrects quote header and line items.
5. Verified quote line items populate Purchase Request document_items.
6. PR can then be submitted for approval.
7. Approval requires verified supplier quote evidence or quote_exception with source_note.

## Rules

- Supplier quote OCR is an input for creating PR lines, not only post-save evidence.
- Do not require manual PR line-item entry before OCR.
- For purchase_request plus supplier_quote, parse as supplier_quotation, not supplier_invoice.
- Keep supplier quote attachment and extraction linked to the PR.
- Preserve existing layout unless a task explicitly asks for layout work.
- Do not change unrelated document types.
- Do not make cosmetic changes.

## Acceptance tests

- PR can start from supplier quote upload.
- OCR output includes quote fields and line items.
- Verified quote lines become PR document_items.
- Submit is blocked without verified quote evidence or quote exception.
- Submit succeeds after verified quote evidence.
- Supplier invoice OCR still works.

## Future Codex prompt

Read AGENTS.md and docs/codex-tasks/PURCHASE_REQUEST_QUOTE_FIRST_OCR.md. Implement the next smallest step. Do not redesign layout.
