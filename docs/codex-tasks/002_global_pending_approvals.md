# Task 002: Global Pending Approvals

## Objective

Fix pending approval navigation so the notification badge count and destination match.

## Current problem

The header notification badge counts all pending approvals, but the bell link routes to pending customer quotations only.

## Business rule

Users should be able to open one global page that lists pending approvals across all document modules.

Admin and manager users can approve/reject from each document page. Other roles must not gain approval permission through this page.

## Files to inspect before editing

- `routes/web.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/partials/navigation.blade.php`
- `app/Http/Controllers/DocumentController.php`
- `app/Models/Approval.php`
- `app/Models/Document.php`
- `tests/Feature/`

## Expected implementation

- Add a route and controller action for a global pending approvals page.
- The page should list pending approval documents across all modules.
- Include document number, type, party, status, amount, requester, requested date, and Open action.
- Update the bell link to route to this page.
- Keep approval authority limited to admin/manager.
- Use pagination; do not load all records unbounded.

Suggested route name:

```text
approvals.pending
```

Suggested controller options:

```text
ApprovalController@indexPending
or a new method on DashboardController if kept small
```

Prefer a dedicated controller if logic grows.

## Tests required

Add or update feature tests covering:

- pending approvals page loads for authorized users
- page lists multiple document types
- bell link points to global pending approvals page
- non-approver cannot approve through direct POST
- pagination or query does not load unbounded data

## Acceptance criteria

- Header badge destination matches the count concept.
- Pending approvals across modules are visible in one place.
- Existing document-level approval/rejection still works.
- No new infrastructure dependency is introduced.

## Do not do in this task

- Do not redesign the whole dashboard.
- Do not change approval status vocabulary.
- Do not add real-time notifications or WebSockets.
