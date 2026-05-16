# Task 007: Cancel Workflow

## Objective

Resolve the hidden cancel workflow action so cancellation is either exposed safely or intentionally restricted.

## Current problem

The backend transition logic supports `cancel`, but the document show page does not clearly expose a cancel action.

## Business rule

Cancellation should be deliberate, audited, and blocked for completed financial states.

Suggested allowed statuses:

```text
draft
rejected
approved
issued
```

Suggested blocked statuses:

```text
pending_approval
fulfilled
received
matched
part_paid
paid
closed
cancelled
```

If business wants cancellation during pending approval, require admin/manager and a reason.

## Files to inspect before editing

- `routes/web.php`
- `app/Http/Controllers/DocumentController.php`
- `app/Models/Document.php`
- `resources/views/documents/show.blade.php`
- `database/migrations/`
- `tests/Feature/`

## Expected implementation

- Decide and document whether cancel is exposed or restricted.
- If exposed, add a Cancel action on `documents.show` when allowed.
- Require cancellation reason.
- Store the reason in a clear place, preferably audit payload or dedicated nullable field if needed.
- Audit cancellation with reason and user.
- Ensure cancelled documents cannot be edited or transitioned further unless explicitly allowed.
- Backend must reject direct POST attempts for disallowed cancellation.

## Tests required

Add or update feature tests covering:

- allowed status can be cancelled with reason
- cancellation without reason fails
- disallowed status cannot be cancelled
- cancelled document cannot be edited
- cancelled document cannot transition further
- audit trail includes cancellation reason

## Acceptance criteria

- No hidden cancel behavior remains unclear.
- Cancellation is visible or intentionally restricted.
- Cancellation is audited.
- UI and backend rules match.
- No shared-hosting constraint is broken.

## Do not do in this task

- Do not redesign the full state machine.
- Do not add delete/destroy behavior.
- Do not allow cancellation to erase financial history.
