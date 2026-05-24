# Implementation Objectives

Implement these objectives in priority order. Each objective should preserve Exabytes/Plesk shared-hosting compatibility.

## Current implementation status

As of 2026-05-23, the objectives below have been implemented in the Laravel Blade monolith and covered by feature tests:

- manual supplier invoice verification fallback using `AttachmentExtraction`
- server-side payment eligibility for customer and supplier invoices
- supplier invoice matching checklist with tolerance checks, duplicate checks, source/supplier validation, override reason, and audit trail
- global pending approvals page
- visible cancel workflow with cancellation reason and audit trail
- direct exception source note controls
- goods receipt source validation against issued purchase orders
- linked document progress chain with bounded traversal
- next-document draft creation from eligible command-center records
- purchase request quote-first capture with supplier quotation verification or quote exception
- action-first dashboard, command center, form studio, semantic statuses, and mobile task mode
- document index workbench with separate Preview/Open actions and full-height selected preview
- compact generated PDF output without blank trailing pages
- neutral first-run company profile defaults with no default logo

Keep this file as the business-rule roadmap. New work should extend the current implementation instead of reintroducing the old placeholder behavior.

## Objective 1: Add manual supplier invoice verification fallback

Goal: supplier invoice approval should require verified invoice details, but not strictly successful OCR.

Status: implemented. Supplier invoice files can be verified from OCR output or manually when OCR fails/unavailable. Submission and approval are blocked until verification is complete.

Desired behavior:

- If OCR works, keep the existing OCR-assisted flow.
- If OCR fails or is unavailable, allow authorized users to manually enter and verify key invoice fields.
- Approval/submission should pass when invoice verification is complete by OCR or manual method.
- Audit the verification method and user.

Suggested fields or data structure:

```text
verification_method = ocr | manual | external
verified_fields
verified_by
verified_at
verification_notes
```

If adding columns is too large for the current task, reuse `attachment_extractions` carefully by allowing a `manual` engine/status path. Prefer an explicit schema if possible.

## Objective 2: Enforce payment eligibility

Goal: prevent payment recording before an invoice is workflow-ready.

Status: implemented through `PaymentEligibilityService`, controller enforcement, and document show messaging.

Suggested rules:

```text
customer_invoice: allow payment only in issued, part_paid
supplier_invoice: allow payment only in matched, part_paid
```

Do this in both:

- `resources/views/documents/show.blade.php`
- `App\Http\Controllers\PaymentController`

Add a helper/service so rules are not duplicated.

## Objective 3: Make supplier invoice matching meaningful

Goal: `Mark matched` should mean a real matching check happened.

Status: implemented through `SupplierInvoiceMatchingService`. Matching requires verified details, valid source path or direct exception, supplier/source consistency, duplicate invoice number check, amount tolerance, and audited override when allowed.

Suggested matching checks:

- invoice has verified invoice fields
- supplier invoice has source document unless direct supplier invoice exception
- supplier matches related PO/GR when applicable
- supplier invoice number is not duplicated for the supplier
- invoice total is within tolerance against related source or manual override is recorded
- matching notes are required for exception/direct/override cases

Implementation may start simple with a service and visible checklist.

## Objective 4: Fix global pending approval navigation

Goal: notification bell count and destination should match.

Status: implemented as a global pending approvals page at `approvals.pending`.

Current problem:

- badge counts all pending approvals
- link goes only to pending customer quotations

Target:

- create a global pending approvals page or dashboard section
- list all documents with pending approvals
- link badge to this page

## Objective 5: Expose or remove cancel workflow action

Goal: no hidden backend-only cancellation unless intentional.

Status: implemented for allowed statuses with admin/manager authorization, cancellation reason, audit payload, and no further transition/editing from cancelled status.

Target:

- add cancel button on show page when allowed
- require confirmation and cancellation reason
- audit cancellation reason

Alternative: restrict cancel to admin/manager only or remove transition if business does not want it.

## Objective 6: Tighten direct exception paths

Goal: direct paths should not silently bypass normal workflow controls.

Status: implemented. Direct exception source types require a source note and are recorded in audit context.

When source type is one of these:

- `direct_customer_po`
- `direct_invoice`
- `direct_supplier_po`
- `direct_receipt`
- `direct_supplier_invoice`

Require at minimum:

- `source_note`
- audit trail

For higher control, require manager/admin approval before issue/payment.

## Objective 7: Improve goods receipt source validation

Goal: receiving against a PO should require an issued supplier PO.

Status: implemented. Normal goods receipt path requires an issued supplier PO for the same supplier; direct receipt requires a source note.

Rules:

- if goods receipt `source_type = supplier_po`, related document must be supplier PO and status `issued`
- if goods receipt `source_type = direct_receipt`, require source note

## Objective 8: Improve timeline from decorative to operational

Goal: timeline should show actual linked records when available.

Status: implemented through `DocumentChainService` and the `workflow-timeline` partial. Chain traversal is bounded and falls back to generic steps when no linked records exist.

Target:

- show current record and related source record
- optionally discover upstream/downstream documents via `related_document_id`
- fallback to generic workflow steps if no chain found

## General implementation rules

- Keep controllers thin where possible.
- Prefer small Laravel service classes for business rules.
- Do not add infrastructure dependencies.
- Keep Blade forms and normal redirects.
- Add tests for business rules before or with implementation.
- Make page actions match backend rules.
