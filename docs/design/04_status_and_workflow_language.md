# QuoteFlow Status and Workflow Language

## Purpose

Statuses must communicate business meaning clearly. Users should know what a document can do next without learning internal implementation terms.

## Status groups

Use these semantic groups for UI design and copy.

| Group | Status examples | Meaning | Visual direction |
|---|---|---|---|
| Draft | `draft` | Work not submitted | Slate / neutral |
| Waiting | `pending_approval` | Someone must act | Amber |
| Ready | `approved` | Approved for next step | Blue |
| External movement | `issued`, `received`, `fulfilled` | Sent, received, or operationally completed | Sky / purple |
| Control passed | `matched` | Checked and finance-safe | Teal |
| Money in progress | `part_paid` | Payment started but incomplete | Cyan |
| Money complete | `paid` | Fully paid | Emerald |
| Stopped | `rejected`, `cancelled` | Blocked or intentionally stopped | Red |
| Final | `closed` | Workflow finished | Dark slate or emerald outline |

## Do not use one color for all positive states

Avoid treating these as the same state:

```text
approved
issued
received
matched
```

They mean different things:

- approved = permission granted
- issued = sent or accepted externally
- received = goods/service evidence captured
- matched = finance/control check passed

## User-facing status labels

Preferred labels:

```text
draft -> Draft
pending_approval -> Pending approval
approved -> Approved
rejected -> Rejected
issued -> Issued
fulfilled -> Fulfilled
received -> Received
matched -> Matched
part_paid -> Part paid
paid -> Paid
closed -> Closed
cancelled -> Cancelled
```

Context-specific labels may be used where clearer:

```text
customer_po + issued -> PO accepted
supplier_po + issued -> PO issued
goods_receipt + received -> Receipt confirmed
supplier_invoice + matched -> Invoice matched
```

## Workflow language

Use user-goal verbs:

- Submit for approval
- Approve
- Reject
- Issue quotation
- Accept PO received
- Issue purchase order
- Mark received
- Verify invoice details
- Match invoice
- Record payment
- Close document
- Cancel document

Avoid system verbs:

- Transition
- Execute action
- Update extraction
- Process entity
- Change status

## Blocking language

When an action is unavailable, explain why.

Examples:

```text
Payment locked until this supplier invoice is matched.
Approval locked until invoice details are verified.
Matching locked until invoice copy is uploaded.
Issue locked until manager approval is complete.
```

Do not only hide unavailable actions.

## Checklist language

Checklist items should be short and specific.

Good:

```text
Invoice copy uploaded
Invoice number verified
Supplier matches PO
Total within tolerance
Payment not yet recorded
```

Bad:

```text
Document conditions are valid
Data completed
Workflow status acceptable
```

## Direct exception language

Exception paths must be explicit.

Preferred copy:

```text
Direct supplier invoice
Use only when no PO or receipt is required. Add a reason.
```

```text
Direct receipt exception
Use only when goods or services must be recorded before the PO is available. Add a reason.
```

## Approval language

Approval copy should identify who acts and what is being approved.

Examples:

```text
Manager approval is required before this quotation can be issued.
Manager approval is required before this purchase order can be sent to the supplier.
Invoice details must be verified before approval.
```

## Payment language

Payment copy must be finance-safe.

Examples:

```text
Payment is available because this customer invoice has been issued.
Supplier payment is locked until invoice matching is complete.
This invoice is fully paid. Close it after payment is confirmed.
```

## Empty and success messages

Success messages should confirm the business outcome:

```text
Invoice details verified.
Supplier invoice matched.
Payment recorded.
Quotation issued.
```

Avoid vague success messages:

```text
Updated successfully.
Action complete.
```
