# Task 013: Mobile Task Mode

## Objective

Define and implement a task-focused mobile experience without forcing the full desktop workbench onto small screens.

## Current problem

QuoteFlow uses powerful desktop patterns such as list + preview and form + live preview. These are suitable for laptop/desktop but too heavy for mobile.

## Design rule

Follow:

- `AGENTS.md`
- `docs/design/01_design_principles.md`
- `docs/design/02_layout_system.md`
- `docs/design/06_accessibility_checklist.md`
- `docs/design/07_responsive_behavior.md`

## Target mobile priority

Mobile should focus on:

```text
My Work
Search
Approve/reject
Verify invoice
Upload evidence
Record payment
View summary
```

## Files to inspect before editing

- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/partials/navigation.blade.php`
- `resources/views/dashboard/index.blade.php`
- `resources/views/documents/index.blade.php`
- `resources/views/documents/show.blade.php`
- `resources/css/app.css`
- `tests/Feature/`

## Expected implementation

- Improve mobile navigation and task visibility.
- Ensure important actions are reachable without horizontal scrolling.
- Stack workbench panels clearly on mobile.
- Keep preview available but not dominant on small screens.
- Use card rows for mobile where tables become too wide.
- Do not break desktop layouts.

## Manual acceptance checks

Check at around 390px width:

- dashboard is readable
- navigation opens and closes clearly
- document list can be searched and opened
- document show page exposes next action before long history
- primary actions are large enough to tap
- no critical table requires impossible horizontal scrolling

## Do not do in this task

- Do not build a separate mobile app.
- Do not introduce a SPA framework.
- Do not remove desktop workbench behavior.
