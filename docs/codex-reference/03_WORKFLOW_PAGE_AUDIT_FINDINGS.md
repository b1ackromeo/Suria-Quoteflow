# Workflow vs Page Audit Findings

This file summarizes the workflow/page audit performed from the current repository code.

The audit looked primarily at:

- `routes/web.php`
- `app/Http/Controllers/DocumentController.php`
- `app/Http/Controllers/PaymentController.php`
- `app/Models/Document.php`
- `resources/views/documents/index.blade.php`
- `resources/views/documents/form.blade.php`
- `resources/views/documents/show.blade.php`
- `resources/views/documents/partials/workflow-timeline.blade.php`
- `resources/views/documents/partials/supplier-invoice-file-preview.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/partials/navigation.blade.php`
- `resources/views/payments/form.blade.php`

## Overall assessment

The workflow and pages are mostly aligned. The app has a strong MVP foundation: module navigation, list workbench, live preview, document studio form, show workspace, attachments, OCR panel, approvals, transitions, payments, PDFs, and reports are all connected.

The main weaknesses are workflow control gaps, not missing pages.

## Finding 1: Supplier invoice approval is too dependent on OCR

Current behavior:

- Supplier invoice submission is blocked unless an invoice copy exists.
- The invoice copy must be extractable.
- OCR must run.
- Extracted fields must be verified.
- If OCR fails, approval is blocked.

Problem:

The UI says OCR prepares a draft and a user verifies it, but the backend effectively requires successful OCR. This is too fragile. A bad scan can block the whole approval process.

Recommended target:

Approval should require verified supplier invoice details, not necessarily OCR success.

Add manual verification fallback:

```text
verification_method = ocr | manual | external
invoice_verified_at
invoice_verified_by
invoice_verification_notes
```

## Finding 2: Payment can be recorded too early

Current behavior:

- The show page shows `Record payment` for customer and supplier invoices unless status is `paid`, `closed`, or `cancelled`.
- The payment controller only checks user role. It does not verify invoice workflow readiness.

Problem:

A user may record payment on a draft, pending approval, rejected, approved-but-unissued, or unmatched invoice.

Recommended target:

Only allow payments for eligible invoice states.

Suggested default:

```text
customer_invoice: issued, part_paid
supplier_invoice: matched, part_paid
```

If the business wants supplier invoice payment before matching, add a deliberate state like `approved_for_payment`.

## Finding 3: Backend supports cancel but page does not expose cancel

Current behavior:

`DocumentController::transition()` supports `cancel`, but the show page does not render a cancel action in the workflow action stack.

Problem:

This is hidden backend behavior. Either it is intended but inaccessible, or it is dead capability.

Recommended target:

Add a guarded Cancel button with confirmation and a required reason, or remove/lock the action.

## Finding 4: Workflow timeline is generic, not record-chain-aware

Current behavior:

`workflow-timeline.blade.php` shows fixed steps for outgoing or incoming process.

Problem:

It does not show whether this specific record is linked to real upstream/downstream documents.

Recommended target:

Show actual linked document chain when available, for example:

```text
PR-2026-00001 -> SQ-2026-00004 -> SPO-2026-00012 -> GR-2026-00008 -> SIN-2026-00009
```

Fallback to generic steps if no chain exists.

## Finding 5: Supplier invoice matching is only a status click

Current behavior:

The UI has `Mark matched`. Backend sets status to `matched` if allowed by type/status.

Problem:

There is no clear matching checklist enforcing supplier, PO, receipt, invoice number, amount, line, or duplicate checks.

Recommended target:

Before `matched`, validate a checklist:

- invoice copy verified
- supplier matches source document
- source PO/GR exists unless direct exception
- invoice number not duplicated for same supplier
- invoice total within tolerance
- line/quantity check completed or manually overridden
- matching notes required for exception

## Finding 6: Direct exception source paths are too easy

Current behavior:

The form supports direct paths like direct customer PO, direct invoice, direct supplier PO, direct receipt, and direct supplier invoice.

Problem:

These source types can bypass the normal chain without a required reason, attachment, or approval policy.

Recommended target:

For direct/exception paths, require:

- `source_note`
- manager/admin approval, or at least audit trail
- optional supporting attachment depending on document type

## Finding 7: Approval is too broad and uniform

Current behavior:

Most documents use the same submit -> approve -> approved flow.

Problem:

Different documents have different business meanings. A goods receipt confirmation is not the same as a purchase request approval or supplier invoice payment approval.

Recommended target:

Introduce document-type-specific policies, while keeping the simple route structure.

Examples:

- customer quotation: approval before issue
- purchase request: approval before sourcing or PO
- supplier PO: approval before issue
- goods receipt: receiving confirmation/evidence before received
- supplier invoice: verification + matching before payment

## Finding 8: Goods receipt source should require issued supplier PO

Current behavior:

Goods receipt requires a related document unless direct receipt is selected, but validation should be stricter about source PO status.

Recommended target:

If `source_type = supplier_po`, related supplier PO should be status `issued`.

If `source_type = direct_receipt`, require exception note.

## Finding 9: Customer direct invoice needs stronger control

Current behavior:

Direct invoice is available as a source path.

Problem:

This bypasses quotation and customer PO.

Recommended target:

Require source note and either attachment or manager approval for direct invoices.

## Finding 10: Pending approval badge routes to only customer quotations

Current behavior:

The header badge counts all pending approvals, but the link goes to customer quotations filtered by pending approval.

Problem:

The count and destination do not match.

Recommended target:

Create a global pending approvals page or filter that includes all pending documents.

Minimum acceptable fix:

- add a route/page for pending approvals
- list all pending approval documents across modules
- update bell link to that route

## Priority summary

### P0

- manual supplier invoice verification fallback
- payment eligibility enforcement
- supplier invoice matching checklist
- pending approval page/bell fix

### P1

- cancel action exposure or removal
- direct exception note requirements
- goods receipt source status enforcement
- document-type-specific approval policies

### P2

- linked-document timeline
- confirmation dialogs for workflow actions
- checklist cards on show page
- more create-next links for outgoing workflow
