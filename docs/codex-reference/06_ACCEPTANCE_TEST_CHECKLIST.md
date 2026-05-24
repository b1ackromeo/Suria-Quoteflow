# Acceptance Test Checklist

Use this checklist before accepting Codex-generated changes.

## General checks

- App boots without errors.
- `composer install --no-dev --optimize-autoloader` still works.
- `php artisan route:list` works.
- `php artisan config:cache` works.
- `php artisan route:cache` works, unless closures were intentionally added and documented.
- `php artisan quoteflow:single-company-readiness` works.
- `php artisan quoteflow:ocr-smoke-test` works when OCR is enabled.
- `php artisan quoteflow:backup-database --dry-run` works.
- `php artisan quoteflow:backup-database --verify-latest` works after a backup exists.
- No production Node/Vite dependency is introduced.
- No Redis, Horizon, WebSocket, queue worker, Docker, or VPS-only dependency is introduced.
- CSS remains prebuilt/static for production.

## Workflow page consistency

For every changed workflow action:

- The Blade page shows the action only when backend allows it.
- The backend rejects the action when the page should not show it.
- Error messages are user-friendly.
- Success messages are clear.
- Audit trail records important business events.
- Document numbering resumes from existing records if sequence rows are missing or behind imported/demo data.
- If imported/demo data exists before UAT, `php artisan quoteflow:single-company-readiness --sync-document-sequences` raises numbering counters without renaming records.

## Next document draft creation

Test cases:

- Approved customer quotation shows Create customer PO received for write-authorized users.
- Create customer PO received creates a draft linked to the quotation, copies customer, project, currency, payment terms, and line items, and leaves the customer's PO reference ready for user entry.
- Fulfilled Customer PO received shows Create customer invoice.
- Create customer invoice creates a draft linked to the Customer PO received, copies customer and line items, and carries the PO/document reference into the invoice reference.
- Direct POST attempts for unavailable status paths are rejected server-side.
- Viewer/read-only users do not see next-document creation actions.
- Audit trail records `document_converted` for the created draft.

## Document index and preview workbench

Test cases:

- List rows expose separate Preview and Open actions.
- The selected row is visually obvious and has accurate `aria-current` and preview button state.
- Desktop document lists render as list + selected preview at `1366x768`.
- Mobile and tablet do not force the full two-pane preview; preview is collapsed until requested.
- Generated PDF preview, uploaded PDF/image preview, and unavailable-preview states fit inside the preview pane without creating a large blank panel.
- Only the selected generated PDF iframe is loaded initially; hidden rows must not eagerly load every PDF.
- Browser check includes at least laptop `1366x768`, mobile around `390px`, and zoom-like reduced widths for full-screen workspaces.

## Supplier invoice verification

Test cases:

- Supplier invoice with no invoice copy cannot be submitted.
- Supplier invoice with invoice copy but no verification cannot be submitted.
- Supplier invoice with successful OCR and verified fields can be submitted.
- Supplier invoice with OCR failure can still be manually verified by authorized user.
- Supplier invoice manually verified can be submitted.
- Unauthorized user cannot verify supplier invoice fields.
- Verified invoice fields update relevant document fields where intended.
- Audit trail records OCR-assisted and manual verification.

## Purchase request quote-first capture

Test cases:

- Purchase request can start from supplier quotation upload.
- Supplier quotation OCR/extraction is reviewed as supplier quotation evidence.
- Only selected verified quotation lines become purchase request items.
- Purchase request approval is blocked until supplier quotation evidence is verified or a quote exception reason is recorded.
- Quote exception requires approver comment before approval.

## Payment eligibility

Test cases:

- Draft customer invoice cannot receive payment.
- Pending customer invoice cannot receive payment.
- Approved but unissued customer invoice cannot receive payment.
- Issued customer invoice can receive payment.
- Part-paid customer invoice can receive additional payment.
- Draft supplier invoice cannot receive payment.
- Approved but unmatched supplier invoice cannot receive payment.
- Matched supplier invoice can receive payment.
- Part-paid supplier invoice can receive additional payment.
- Fully paid invoice becomes `paid`.
- Payment route rejects direct URL attempts for ineligible invoices.

## Supplier invoice matching

Test cases:

- Unverified supplier invoice cannot be matched.
- Supplier invoice without source document cannot be matched unless direct exception is valid.
- Supplier mismatch blocks matching.
- Duplicate supplier invoice number blocks or warns according to implemented policy.
- Matching checklist appears on supplier invoice show page.
- `Mark matched` is hidden or disabled until matching passes.
- Backend rejects direct POST to match if checklist fails.
- Override, if implemented, requires reason and audit trail.
- Amount tolerance messages use the active company/document money format instead of hardcoded RM defaults.

## Pending approvals

Test cases:

- Header badge count matches pending approvals query.
- Bell link opens global pending approvals page.
- Page lists pending approvals across all document modules.
- Each item has an Open link.
- Admin/manager can approve from document page.
- Non-approvers cannot approve even if they can view.

## Direct exception paths

Test cases:

- Direct customer PO requires source note.
- Direct customer invoice requires source note.
- Direct supplier PO requires source note.
- Direct receipt requires source note.
- Direct supplier invoice requires source note.
- Normal source paths do not require exception note unless business rule says so.
- Audit trail records direct exception context.

## Goods receipt

Test cases:

- Goods receipt against supplier PO requires related supplier PO.
- Related supplier PO must be status `issued`.
- Direct receipt can be saved only with source note.
- Receiving page still hides payment fields.
- Received quantity can be zero only for goods receipt flow.

## Cancel action

Test cases if cancel is implemented:

- Allowed statuses can be cancelled.
- Disallowed statuses cannot be cancelled.
- Cancellation requires reason.
- Cancelled document cannot be edited or transitioned further.
- Audit trail includes reason and user.

## Company profile and localization defaults

Test cases:

- First-run company profile defaults are neutral: `Your Company Name`, `Other`, `UTC`, `USD`, code-based currency display, no default logo.
- Malaysia/MYR behavior remains available when explicitly configured.
- Logo-less company surfaces show initials instead of broken empty images.
- Dashboard, reports, search, pending approvals, document lists, document show pages, and previews use active company date/money formatting.
- Demo/UAT seeding may create an explicit RC Technology Malaysia profile, but global fallback defaults must remain neutral.

## Generated PDF output

Test cases:

- Compact generated business documents render as one page, without a blank extra page.
- Preview and download routes render the same document content.
- Company profile text, tax labels, payment instructions, and footer text appear where configured.
- Goods receipt/service acceptance PDFs preserve their receiving format.
- DomPDF remains the rendering engine; do not introduce headless browser PDF generation for normal workflow documents.

## Exabytes/Plesk compatibility

Before deployment:

- Run `php artisan quoteflow:single-company-readiness --strict` and resolve every failure/warning or document an accepted limitation.
- Keep `.env` using file cache/session and sync queue unless intentionally changed.
- Confirm storage and bootstrap cache paths are writable.
- Confirm `mysqldump` is available through `MYSQLDUMP_PATH` and `php artisan quoteflow:backup-database --dry-run`.
- Create and verify one real database backup before production use.
- Confirm attachment upload limit is within 50M PHP limits.
- Confirm DomPDF renders a real quotation/invoice.
- Confirm short demo/UAT PDFs do not create blank extra pages.
- Confirm OCR command availability if OCR is enabled:
  - `tesseract --version`
  - `gs --version`
- Confirm `php artisan quoteflow:ocr-smoke-test` passes if OCR is enabled.
- Remove diagnostic routes after testing.
