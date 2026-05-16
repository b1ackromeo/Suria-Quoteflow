# Implementation Objectives

Implement these objectives in priority order. Each objective should preserve Exabytes/Plesk shared-hosting compatibility.

## Objective 1: Add manual supplier invoice verification fallback

Goal: supplier invoice approval should require verified invoice details, but not strictly successful OCR.

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

Current problem:

- badge counts all pending approvals
- link goes only to pending customer quotations

Target:

- create a global pending approvals page or dashboard section
- list all documents with pending approvals
- link badge to this page

## Objective 5: Expose or remove cancel workflow action

Goal: no hidden backend-only cancellation unless intentional.

Target:

- add cancel button on show page when allowed
- require confirmation and cancellation reason
- audit cancellation reason

Alternative: restrict cancel to admin/manager only or remove transition if business does not want it.

## Objective 6: Tighten direct exception paths

Goal: direct paths should not silently bypass normal workflow controls.

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

Rules:

- if goods receipt `source_type = supplier_po`, related document must be supplier PO and status `issued`
- if goods receipt `source_type = direct_receipt`, require source note

## Objective 8: Improve timeline from decorative to operational

Goal: timeline should show actual linked records when available.

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
