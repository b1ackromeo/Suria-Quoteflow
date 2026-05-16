# QuoteFlow UX Writing Guide

## Voice

QuoteFlow should sound like an experienced operations coordinator.

Voice qualities:

- direct
- calm
- specific
- helpful
- business-aware
- concise

Avoid:

- generic system messages
- long policy paragraphs
- developer/internal terms
- vague success copy
- decorative marketing language

## Copy hierarchy

Use three layers of copy.

### Layer 1: label

Short and scannable.

```text
From receipt
Record payment
Invoice details
```

### Layer 2: short helper

One sentence only.

```text
Use when the supplier invoice follows accepted goods or services.
```

### Layer 3: detailed guidance

Show only when needed through help text, disclosure, or documentation.

Do not put long instructions in the default path unless the action is risky.

## Rewrite patterns

### Source path questions

Before:

```text
Choose whether the supplier invoice is matched against a receiving record, a purchase order, or an approved direct supplier invoice exception.
```

After:

```text
How should this invoice be matched?
```

### Exception path copy

Before:

```text
Use this for bills that are approved without PO or receipt matching.
```

After:

```text
Use only when no PO or receipt is required. Add a reason.
```

### Approval blocker

Before:

```text
The document cannot be submitted due to missing extraction verification.
```

After:

```text
Verify invoice details before submitting for approval.
```

### Payment blocker

Before:

```text
Payment cannot be recorded for this status.
```

After:

```text
Payment is locked until this supplier invoice is matched.
```

### Success message

Before:

```text
Updated successfully.
```

After:

```text
Supplier invoice details verified.
```

## Button labels

Buttons should be verbs.

Use:

- Create quotation
- Record PO received
- Save invoice
- Submit for approval
- Verify invoice details
- Match invoice
- Record payment
- Close document

Avoid:

- Submit
- Process
- Update
- Execute
- Continue
- Action

## Page titles

Page titles should describe the workspace.

Good:

```text
Today’s Work
Customer Invoices
Supplier Invoice SIN-2026-00008
New Purchase Order
```

Avoid:

```text
Index
Show Document
Create Record
```

## Empty states

Use this structure:

```text
No [records] yet
[Why this area matters and what happens next.]
[Primary action]
```

Example:

```text
No supplier invoices yet
Record the supplier invoice, upload the invoice file, then verify and match it before payment.
[Record supplier invoice]
```

## Error messages

Error messages should tell the user how to fix the issue.

Good:

```text
Add a reason for this direct supplier invoice.
```

Bad:

```text
The source note field is required.
```

Laravel validation messages can stay technical internally, but UI-facing summaries should be business-readable.

## Confirmation messages

Use confirmations for irreversible or important workflow actions:

- approve
- reject
- issue
- match
- close
- cancel
- record payment

Confirmation copy should include the business impact.

Example:

```text
Issue this purchase order?
The supplier-facing PDF will be treated as issued. You can still attach supporting documents after issue.
```

## Field labels

Field labels should be short.

Use helper text for explanation.

Good:

```text
Source note
Explain why this direct exception is allowed.
```

Bad:

```text
Internal note explaining why this source path is allowed or how the matching source was selected
```

## Tone for finance controls

Use precise, non-alarming language.

Good:

```text
Payment locked until matching is complete.
```

Avoid:

```text
Payment forbidden.
```

## Tone for OCR

OCR should be described as assistance.

Use:

```text
OCR extracted a draft. Verify the fields before approval.
```

Avoid:

```text
OCR verified the invoice.
```

The user verifies. OCR only assists.
