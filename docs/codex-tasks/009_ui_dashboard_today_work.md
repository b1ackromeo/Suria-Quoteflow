# Task 009: Dashboard Today’s Work UX

## Objective

Rework the dashboard hierarchy so it starts with actionable work, not only summary metrics.

## Current problem

The dashboard has useful metrics and workflow summaries, but users need a clearer answer to: "What needs my attention today?"

## Design rule

Follow:

- `AGENTS.md`
- `docs/design/01_design_principles.md`
- `docs/design/02_layout_system.md`
- `docs/design/05_ux_writing_guide.md`
- `docs/design/09_ui_ux_audit_findings.md`

## Target dashboard order

```text
1. Today’s Work / Needs Attention
2. Workflow Health
3. Financial Snapshot
4. Recent Activity
5. Secondary summaries
```

## Files to inspect before editing

- `app/Http/Controllers/DashboardController.php`
- `resources/views/dashboard/index.blade.php`
- `resources/css/app.css`
- `resources/views/layouts/app.blade.php`
- `tests/Feature/`

## Expected implementation

- Add a clear `Today’s Work` section near the top.
- Include actionable cards for available data, such as:
  - pending approvals
  - overdue/open receivables
  - supplier invoices requiring verification or matching if such states can be queried safely
  - payments due soon if available
- Each card should have a specific action link.
- Keep existing useful metrics, but move them below action-first work where appropriate.
- Keep queries shared-hosting safe; avoid heavy unbounded queries.

## UX writing requirements

Use short labels:

```text
Pending approvals
Verify supplier invoices
Match supplier invoices
Overdue receivables
```

Avoid long dashboard marketing paragraphs.

## Tests required

Add or update tests where practical:

- dashboard loads
- Today’s Work section renders
- pending approvals link points to a global or correct pending approvals route when available
- role restrictions are not weakened

## Acceptance criteria

- dashboard immediately shows actionable work
- important actions link to correct destinations
- layout remains responsive
- no production Node, SPA, queue, or external service dependency is introduced

## Do not do in this task

- Do not redesign every dashboard section.
- Do not implement global pending approvals unless assigned separately.
- Do not create expensive analytics queries.
