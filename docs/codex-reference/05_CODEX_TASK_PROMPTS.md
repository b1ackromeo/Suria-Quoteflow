# Codex Task Prompts

Use these prompts one at a time in VS Code. Ask Codex to inspect the current files before editing.

## Task 1: Manual supplier invoice verification fallback

```text
Read docs/codex-reference first. Implement manual supplier invoice verification fallback.

Current problem: supplier invoice submission depends on OCR success and verified OCR extraction. Keep OCR, but allow authorized users to manually verify supplier invoice fields when OCR fails or is unavailable.

Requirements:
- Preserve current OCR-assisted flow.
- Add a manual verification path for supplier invoices.
- Approval/submission should pass when supplier invoice details are verified by OCR or manual method.
- Track verification method, verified fields, verifier, timestamp, and notes.
- Update Blade UI so users understand OCR is optional assistance, not the only path.
- Add or update tests for OCR success, OCR failure + manual verification, and unverified invoice blocking.
- Do not add Redis, queues, external services, or VPS-only dependencies.
```

## Task 2: Payment eligibility enforcement

```text
Read docs/codex-reference first. Implement payment eligibility rules.

Current problem: payment can be recorded on invoices that are not workflow-ready.

Requirements:
- Customer invoice payments allowed only when status is issued or part_paid.
- Supplier invoice payments allowed only when status is matched or part_paid.
- Enforce in PaymentController, not only in Blade.
- Hide or disable Record payment button on documents.show when not eligible.
- Show a helpful message explaining why payment is not available.
- Add tests for allowed and blocked payment attempts.
```

## Task 3: Supplier invoice matching checklist

```text
Read docs/codex-reference first. Implement a supplier invoice matching checklist before Mark matched.

Requirements:
- Add a service or helper to evaluate supplier invoice matching readiness.
- Checklist should consider invoice verification, source document, supplier consistency, duplicate supplier invoice number, total/tolerance, and direct exception notes.
- Show checklist on supplier invoice show page.
- Only show or allow Mark matched when checklist passes, unless an authorized override is implemented.
- If override is implemented, require a reason and audit it.
- Add feature tests for matching pass/fail cases.
```

## Task 4: Global pending approvals page

```text
Read docs/codex-reference first. Fix pending approval navigation.

Current problem: header badge counts all pending approvals but links only to pending customer quotations.

Requirements:
- Add a global pending approvals route/page or dashboard panel.
- List all pending approvals across document modules.
- Include document number, type, party, amount, requester, date, and Open action.
- Update the header notification bell link to this route.
- Preserve role permissions: only admin/manager should be able to approve, but permitted users may see relevant pending items according to existing access rules.
- Add tests for route access and data listing.
```

## Task 5: Cancel workflow action

```text
Read docs/codex-reference first. Resolve the hidden cancel workflow action.

Current problem: backend supports cancel but UI does not expose it.

Requirements:
- Decide whether cancel should be exposed or restricted.
- If exposed, add a Cancel action on documents.show when backend allows it.
- Require confirmation and cancellation reason.
- Store/audit the reason.
- Ensure cancelled documents cannot be edited or transitioned further unless explicitly allowed.
- Add tests for allowed and blocked cancellation.
```

## Task 6: Direct exception source controls

```text
Read docs/codex-reference first. Tighten direct exception paths.

Requirements:
- For direct_customer_po, direct_invoice, direct_supplier_po, direct_receipt, and direct_supplier_invoice, require source_note.
- Show UI copy explaining why the note is required.
- Validate in DocumentController or a dedicated service.
- Audit direct exception creation/update.
- Add tests for missing source_note and valid direct exception paths.
```

## Task 7: Goods receipt source validation

```text
Read docs/codex-reference first. Tighten goods receipt validation.

Requirements:
- If goods receipt is against supplier_po, related document must be a supplier_po with status issued.
- If direct_receipt is selected, source_note is required.
- Update form copy and validation messages accordingly.
- Add feature tests.
```

## Task 8: Workflow timeline linked-chain improvement

```text
Read docs/codex-reference first. Improve the workflow timeline so it shows actual linked documents when available.

Requirements:
- Keep the current generic timeline as fallback.
- For documents with related_document_id, show upstream chain links.
- Show document numbers, types, statuses, and links to open records.
- Avoid expensive recursive queries; keep it shared-hosting safe.
- Add tests or at least Blade coverage where practical.
```

## General prompt add-on

Append this to any Codex task:

```text
Before editing, inspect the current route, controller, model, migration, Blade view, and tests related to the task. Make the smallest safe change. Keep Exabytes/Plesk shared-hosting compatibility. Do not introduce new infrastructure dependencies. Update tests and documentation when needed.
```
