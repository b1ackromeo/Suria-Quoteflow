# Task 011: Form Studio UX and Copy Refinement

## Objective

Reduce form cognitive load while preserving the one-page document studio pattern.

## Current problem

The form page is smart and context-aware, but it contains long explanatory copy and many sections. Expert users may be slowed down by repeated guidance.

## Design rule

Follow:

- `AGENTS.md`
- `docs/design/01_design_principles.md`
- `docs/design/02_layout_system.md`
- `docs/design/03_component_guidelines.md`
- `docs/design/05_ux_writing_guide.md`
- `docs/design/07_responsive_behavior.md`

## Target form pattern

Keep one page, but make it feel guided:

```text
1. Source
2. Party and dates
3. Money and terms
4. Items
5. Notes and evidence
```

Add a compact readiness/progress summary where practical.

## Files to inspect before editing

- `resources/views/documents/form.blade.php`
- `resources/css/app.css`
- `app/Http/Controllers/DocumentController.php`
- `app/Models/Document.php`
- `tests/Feature/`

## Expected implementation

- Reduce long explanatory copy by about 30-40 percent where it does not reduce clarity.
- Keep source choice cards, but make labels shorter.
- Add or prepare a compact progress/readiness summary.
- Make exception source paths clearly say a reason is required when that backend rule exists.
- Do not remove important guidance for risky workflows such as supplier invoice, goods receipt, and direct exceptions.

## UX writing examples

Use:

```text
How should this invoice be matched?
Use only when no PO or receipt is required. Add a reason.
Upload evidence after saving this receipt.
```

Avoid:

```text
Choose whether the supplier invoice is matched against a receiving record, a purchase order, or an approved direct supplier invoice exception.
```

## Tests required

Add or update tests where practical:

- forms render for major document types
- validation errors remain visible
- required fields are not removed
- source path values remain unchanged

## Acceptance criteria

- form remains functionally equivalent
- copy is shorter and clearer
- risky paths remain explicit
- responsive layout is not worsened
- no backend rule is weakened

## Do not do in this task

- Do not redesign the entire form into a multi-page wizard.
- Do not change database schema.
- Do not change workflow validation unless assigned separately.
