# Task 010: Document Command Center UX

## Objective

Improve the document show page hierarchy so it clearly presents next action, blockers, primary CTA, key facts, evidence, financials, and history.

## Current problem

The document show page is powerful but dense. Many cards and disclosures appear at similar visual weight, so users may not immediately know what to do next.

## Design rule

Follow:

- `AGENTS.md`
- `docs/design/01_design_principles.md`
- `docs/design/02_layout_system.md`
- `docs/design/03_component_guidelines.md`
- `docs/design/04_status_and_workflow_language.md`
- `docs/design/05_ux_writing_guide.md`
- `docs/design/06_accessibility_checklist.md`

## Target right-panel hierarchy

```text
1. Next action
2. Blocking checklist / readiness
3. Primary action
4. Key facts
5. Related document chain
6. Evidence / attachments
7. Financials
8. History
```

## Files to inspect before editing

- `resources/views/documents/show.blade.php`
- `resources/views/documents/partials/workflow-timeline.blade.php`
- `resources/views/documents/partials/supplier-invoice-file-preview.blade.php`
- `app/Http/Controllers/DocumentController.php`
- `app/Models/Document.php`
- `resources/css/app.css`
- `tests/Feature/`

## Expected implementation

- Keep the left preview pane.
- Reorder or regroup the right panel so the next action and blockers are visible first.
- Add a reusable blocker/readiness panel if needed.
- Keep history/reference sections collapsed or lower priority.
- Do not hide critical blockers inside collapsed details.
- Preserve all existing permissions and backend rules.

## UX writing requirements

Use action-first copy:

```text
Payment locked until this supplier invoice is matched.
Verify invoice details before submitting for approval.
Issue this purchase order after manager approval.
```

## Tests required

Add or update tests where practical:

- show page renders for each major document type
- actions shown match backend eligibility
- blocked states show helpful copy
- no unauthorized action appears for restricted roles

## Acceptance criteria

- the primary next action is obvious
- blockers are visible before user acts
- page remains responsive
- backend behavior is unchanged unless assigned by a workflow task
- no shared-hosting constraint is broken

## Do not do in this task

- Do not implement supplier invoice matching logic.
- Do not change payment eligibility rules unless assigned separately.
- Do not remove existing evidence/history information.
