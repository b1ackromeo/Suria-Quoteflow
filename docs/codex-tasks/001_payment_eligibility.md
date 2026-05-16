# Task 001: Payment Eligibility

## Objective

Prevent payment from being recorded before an invoice is workflow-ready.

## Current problem

The document show page can expose `Record payment` for customer and supplier invoices unless the status is `paid`, `closed`, or `cancelled`. The payment controller currently checks payment role access but does not enforce invoice workflow readiness.

## Business rule

Allow payment only for these invoice states:

```text
customer_invoice: issued, part_paid
supplier_invoice: matched, part_paid
```

Blocked examples:

```text
draft invoice
pending_approval invoice
rejected invoice
approved but unissued customer invoice
approved but unmatched supplier invoice
cancelled invoice
closed invoice
```

## Files to inspect before editing

- `routes/web.php`
- `app/Http/Controllers/PaymentController.php`
- `app/Http/Controllers/DocumentController.php`
- `app/Models/Document.php`
- `resources/views/documents/show.blade.php`
- `resources/views/payments/form.blade.php`
- `tests/Feature/`

## Expected implementation

- Add a small payment eligibility helper/service, preferably `app/Services/Documents/PaymentEligibilityService.php`.
- Enforce eligibility in `PaymentController::create()` and `PaymentController::store()`.
- Hide or disable the `Record payment` button on `documents.show` when payment is not eligible.
- Show a helpful message or status explanation on the document page when payment is not yet available.
- Keep role checks intact: only admin, manager, and accounts can record payments.

## Tests required

Add or update feature tests covering:

- draft customer invoice cannot receive payment
- approved but unissued customer invoice cannot receive payment
- issued customer invoice can receive payment
- part-paid customer invoice can receive additional payment
- approved but unmatched supplier invoice cannot receive payment
- matched supplier invoice can receive payment
- direct POST to payment route is rejected for ineligible invoice
- UI does not show payment action when backend would reject it

## Acceptance criteria

- Backend rejects ineligible payments even if route is posted directly.
- Blade UI matches backend eligibility.
- Existing valid payment behavior still works.
- No shared-hosting constraint is broken.
- Changed files and tests run are summarized.

## Do not do in this task

- Do not implement supplier invoice matching checklist.
- Do not implement manual OCR fallback.
- Do not change document status vocabulary unless absolutely necessary.
- Do not introduce queues, Redis, external APIs, or production Node dependencies.
