# QuoteFlow Codex Reference Pack

This folder contains supporting reference material for Codex. The root-level `AGENTS.md` is the primary instruction file.

## Recommended Codex workflow

For each development task, Codex should:

1. Read `AGENTS.md`.
2. Read exactly one focused task file from `docs/codex-tasks/`.
3. Read only the reference files in this folder that are relevant to that task.
4. For any UI, page wording, navigation, dashboard, status badge, PDF label, report label, form label, validation message, empty state, or copywriting work, read `docs/codex-reference/10_BUSINESS_WORDING_STANDARD.md`.
5. Inspect affected routes, controllers, models, migrations, Blade views, and tests.
6. Summarize current behavior and the smallest safe implementation plan.
7. Implement one objective at a time.
8. Add or update tests.
9. Summarize changed files, tests run, and remaining risks.

Do not ask Codex to read every long reference file for every small task unless the task truly needs it.

## Suggested first prompt

```text
Read AGENTS.md first. Then read the assigned task file under docs/codex-tasks/. Use docs/codex-reference only as supporting context when relevant. For any user-facing wording, read docs/codex-reference/10_BUSINESS_WORDING_STANDARD.md before editing. Inspect the current code before editing. Preserve the QuoteFlow architecture and Exabytes/Plesk shared-hosting constraints.
```

## Focused task files

The current task specs live in:

```text
docs/codex-tasks/
```

Recommended order:

1. `001_payment_eligibility.md`
2. `002_global_pending_approvals.md`
3. `003_direct_exception_controls.md`
4. `004_goods_receipt_source_validation.md`
5. `005_supplier_invoice_verification.md`
6. `006_supplier_invoice_matching.md`
7. `007_cancel_workflow.md`
8. `008_linked_workflow_timeline.md`

## Reference files in this folder

- `01_PROJECT_CONTEXT.md` - what QuoteFlow is and how the current app is structured.
- `02_HOSTING_CONSTRAINTS_EXABYTES_PLESK.md` - hosting facts and rules for Exabytes/Plesk eBiz 17 Mini.
- `03_WORKFLOW_PAGE_AUDIT_FINDINGS.md` - audit findings from workflow pages vs backend logic.
- `04_IMPLEMENTATION_OBJECTIVES.md` - prioritized development objectives.
- `05_CODEX_TASK_PROMPTS.md` - older task prompt list; prefer the focused files in `docs/codex-tasks/` for execution.
- `06_ACCEPTANCE_TEST_CHECKLIST.md` - manual and automated checks before accepting changes.
- `07_SUGGESTED_FILE_MAP.md` - likely files to inspect and modify.
- `08_SHARED_HOSTING_DONT_BREAK_RULES.md` - constraints that must not be violated.
- `09_SYSTEM_ARCHITECTURE.md` - architecture reference for the Laravel monolith, document workflow, UI layers, OCR, payments, matching, reports, PDFs, and shared-hosting boundaries.
- `10_BUSINESS_WORDING_STANDARD.md` - user-facing business wording benchmark, banned internal terms, recommended replacements, and the copy challenge checklist.

## Important architecture rule

Preserve QuoteFlow as a shared-hosting-friendly Laravel monolith:

```text
Browser -> Laravel routes/controllers -> service/business-rule layer -> Eloquent models -> MySQL/MariaDB -> Blade views/downloads/redirects
```

Do not convert it into an API-first SPA, microservice architecture, Redis/Horizon queue-worker system, or VPS-only deployment.

## Current high-priority themes

1. Prevent payment recording before an invoice is workflow-ready.
2. Fix pending approval navigation so the badge routes to all pending approvals.
3. Tighten direct/exception paths with notes, audit trail, and approval controls.
4. Require issued supplier PO for normal goods receipt.
5. Keep OCR, but add a manual supplier invoice verification fallback.
6. Make supplier invoice matching a real checklist, not just a status button.
7. Keep user-facing wording aligned with global business document systems, not route/model/workflow-engine language.
