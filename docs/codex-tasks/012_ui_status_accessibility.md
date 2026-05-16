# Task 012: Status Semantics and Accessibility Hardening

## Objective

Improve status semantics and accessibility without changing backend workflow behavior.

## Current problem

The current status chips are visually consistent but several different business states share similar visual treatment. Some interactions, especially document row preview/open behavior, need stronger accessibility semantics.

## Design rule

Follow:

- `AGENTS.md`
- `docs/design/03_component_guidelines.md`
- `docs/design/04_status_and_workflow_language.md`
- `docs/design/06_accessibility_checklist.md`
- `docs/design/08_visual_identity.md`

## Files to inspect before editing

- `resources/css/app.css`
- `resources/views/documents/index.blade.php`
- `resources/views/documents/show.blade.php`
- `resources/views/dashboard/index.blade.php`
- `resources/views/layouts/app.blade.php`
- `app/Models/Document.php`
- `tests/Feature/`

## Expected implementation

- Refine status chip styling to better represent semantic groups.
- Keep existing status class names unless there is a strong reason to change them.
- Do not rely on color alone for important status or blockers.
- Improve document row interaction so preview and open actions are clearer for keyboard/screen-reader users.
- Ensure icon-only controls have accessible names.
- Preserve existing routes and backend behavior.

## Status semantic groups

Use the guide in `docs/design/04_status_and_workflow_language.md`:

```text
Draft: slate
Waiting: amber
Ready: blue
External movement: sky/purple
Control passed: teal
Money in progress: cyan
Money complete: emerald
Stopped: red
Final: dark slate or emerald outline
```

## Tests required

Add or update tests where practical:

- document index renders rows and open actions
- document show renders status chip and workflow actions
- notification/bell has accessible label
- no unauthorized actions become visible

## Manual acceptance checks

- keyboard can select preview and open document intentionally
- focus ring is visible
- status meaning is readable without relying only on color
- important buttons keep accessible names
- layout remains readable at 125% and 150% zoom

## Do not do in this task

- Do not change document statuses in the database.
- Do not alter workflow transitions.
- Do not redesign every screen at once.
