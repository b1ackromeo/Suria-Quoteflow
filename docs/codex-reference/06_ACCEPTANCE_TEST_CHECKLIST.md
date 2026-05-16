# Acceptance Test Checklist

Use this checklist before accepting Codex-generated changes.

## General checks

- App boots without errors.
- `composer install --no-dev --optimize-autoloader` still works.
- `php artisan route:list` works.
- `php artisan config:cache` works.
- `php artisan route:cache` works, unless closures were intentionally added and documented.
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

## Exabytes/Plesk compatibility

Before deployment:

- Keep `.env` using file cache/session and sync queue unless intentionally changed.
- Confirm storage and bootstrap cache paths are writable.
- Confirm attachment upload limit is within 50M PHP limits.
- Confirm DomPDF renders a real quotation/invoice.
- Confirm OCR command availability if OCR is enabled:
  - `tesseract --version`
  - `gs --version`
- Remove diagnostic routes after testing.
