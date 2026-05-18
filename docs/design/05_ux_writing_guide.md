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
- internal direction terms such as "Outgoing workflow", "Incoming workflow", "Workflow lanes", "Outgoing revenue", or "Incoming procurement"
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

### Document basis questions

Before:

```text
Choose whether the supplier invoice is matched against a receiving record, a purchase order, or an approved direct supplier invoice exception.
```

After:

```text
Select matching basis
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
Operations today
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
Reason / note
Explain why this direct exception is allowed.
```

Bad:

```text
Internal note explaining why this source path is allowed or how the matching source was selected
```

## Form Section Language

Form sections must use the language of the document being prepared. Do not use internal build labels such as `Source path`, `Party and dates`, or `Money and terms`. Avoid generic visible `source` wording when a user-facing phrase such as `Quote basis`, `Billing basis`, `Order basis`, or `Matching basis` is clearer.

Use document-specific labels:

- Customer quotation: Customer request, Customer and quote details, Pricing and terms, Quoted items, Scope and terms
- Supplier quotation: Quote basis, Supplier and quote details, Pricing and terms, Quoted items, Supplier notes and terms
- Purchase request: Supplier quote, Supplier and request details, Budget and terms, Requested items, Justification and notes
- Purchase order: Order basis, Supplier and PO details, Order value and terms, Order items, Delivery terms
- Supplier invoice: Matching basis, Supplier and invoice details, Payment terms, Invoice lines, Invoice notes

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

## OCR Page Copy Standard

OCR scanning pages should use active-task language, not internal extraction language.

Use:

```text
Verify supplier quote evidence
Include in PR
Check extracted invoice details
Review customer PO details
Confirm payment proof
Re-run OCR
```

Avoid:

```text
OCR verified
Extraction complete
Source path
Imported metadata
Processed file payload
```

When OCR is unverified, the page should tell the user exactly what to do next:

```text
OCR extracted a draft from the supplier quotation. Check the fields and line items against the file, then verify the evidence.
```

Do not repeat captured values in multiple places. If the quote terms are editable in the OCR form, do not also show a separate terms summary card with the same text. If the selected attachment and preview already identify the source file, do not add another visible "previewing" header.

When a quotation has more lines than the user plans to buy, use selection language:

```text
Select
3 selected
Only selected quote lines become PR items.
```

Do not repeat a visible word beside every checkbox in a line table when the column header already explains the control.

If OCR confidence is weak, say what needs checking:

```text
Check the supplier name and total before verifying this quote.
```

Keep manual fallback copy positive and operational:

```text
OCR could not read enough detail. Enter the supplier quote details from the file, then verify the evidence.
```
