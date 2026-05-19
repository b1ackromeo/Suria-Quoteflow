# QuoteFlow Business Wording Standard

This file is the wording authority for user-facing QuoteFlow development.

Codex must read this file before changing any page title, navigation label, dashboard card, document show page, form label, empty state, status badge, validation message, PDF label, report label, test assertion, or copywriting-related UI.

QuoteFlow must sound like a practical business document system used by sales, finance, procurement, and admin users. It must not sound like a workflow engine, database admin panel, or Codex-generated route map.

## Principle

Use the wording pattern used by top global business document systems:

```text
business document noun + business action/status
```

Examples:

- Customer quotation
- Customer PO received
- Customer invoice
- Customer payment received
- Purchase request
- Supplier quotation
- Purchase order
- Goods receipt
- Service acceptance
- Supplier invoice
- Supplier payment made
- Document progress
- Available actions
- Required before continuing

Do not derive end-user wording from:

- route names
- controller names
- model names
- enum values
- database columns
- workflow engine transitions
- UI component names
- OCR implementation details
- PDF generation internals

## Global system wording benchmark

Use this benchmark when deciding labels. QuoteFlow does not need to copy any one system exactly, but it should follow the same business language pattern.

| Business concept | Common global ERP/accounting wording | QuoteFlow wording to use |
| --- | --- | --- |
| Customer quote | Quotation, sales quotation, estimate, quote | Customer quotation |
| Customer order proof | Sales order, customer purchase order, accepted estimate | Customer PO received |
| Invoice sent to customer | Customer invoice, sales invoice, A/R invoice, invoice | Customer invoice |
| Money received from customer | Receive payment, payment received, customer payment, incoming payment | Customer payment received / Record customer payment |
| Internal purchasing request | Purchase request, purchase requisition, requisition | Purchase request |
| Supplier quote | Supplier quotation, vendor quotation, RFQ response, supplier quote | Supplier quotation |
| Order sent to supplier | Purchase order, PO | Purchase order / Supplier purchase order |
| Goods received | Goods receipt, receipt, receive items, GRPO | Goods receipt |
| Services accepted | Service acceptance, service receipt | Service acceptance |
| Invoice received from supplier | Supplier invoice, vendor bill, A/P invoice, bill | Supplier invoice |
| Money paid to supplier | Supplier payment, pay bill, bill payment, outgoing payment | Supplier payment made / Record supplier payment |
| Money owed by customers | Accounts receivable, receivables, open invoices | Receivables |
| Money owed to suppliers | Accounts payable, payables, bills to pay | Payables |
| Ageing reports | Receivables aging, payables aging | Receivables aging / Payables aging |
| Approval state | Pending approval, waiting for approval, approved, rejected | Pending approval / Waiting for approval |
| Document timeline | Status, progress, history, activity | Document progress / Approval history |

## Recommended QuoteFlow page names

| Area | Use |
| --- | --- |
| Dashboard | Operations today |
| Customer quote list | Customer quotations |
| Customer PO list | Customer POs received |
| Customer invoice list | Customer invoices |
| Customer payments | Customer payments received |
| Purchase request list | Purchase requests |
| Supplier quotation list | Supplier quotations |
| Supplier PO list | Purchase orders or Supplier purchase orders |
| Receiving list | Goods receipts |
| Service receiving list | Service acceptances |
| Supplier invoice list | Supplier invoices |
| Supplier payments | Supplier payments made |
| Products/items | Products and services |
| Supplier master data | Suppliers / Supplier directory |
| User admin | Users and permissions |
| Approval workbench | Pending approvals |
| Finance reporting | Finance reports |

## Preferred replacements

Use this table when existing copy is encountered.

| Avoid / replace | Use instead |
| --- | --- |
| Supplier billing | Supplier invoices |
| Incoming paid | Customer payments received |
| Outgoing paid | Supplier payments made |
| Request to supplier payment | Purchase request to supplier payment |
| Customer POs | Customer POs received |
| Primary actions | Available actions |
| Workflow | Document progress |
| Before next action | Required before continuing |
| Payment locked | Payment not available yet |
| Supplier payment is locked until this invoice is matched. | Match this supplier invoice before recording payment. |
| Purchase Order Output | Purchase order PDF |
| Customer-facing output | Customer document / Issued customer PDF |
| Supplier-facing output | Supplier document / Issued supplier PDF |
| Internal record output | Internal record PDF |
| Supplier Quote File Preview | Supplier quotation file |
| Customer PO File Preview | Customer PO file / Customer PO received |
| Supplier Invoice Preview | Supplier invoice file |
| No previewable source file uploaded yet | No source file uploaded yet |
| OCR draft ready | Details ready for review |
| Verify and update invoice record | Verify invoice details |
| Verify OCR draft details | Review extracted details |
| Reusable products and services | Products and services |
| Default price used on documents | Default document price / Default selling price |
| User access list | Users and permissions |
| Supplier record preview | Supplier preview |
| Default invoice term | Default payment term |
| Source type | How this document was created |
| Entity | Document / record, depending on context |
| Transition status | Change status / Move to next step, depending on context |
| Execute workflow action | Complete action |
| Submit entity | Submit document |

## Forbidden end-user labels unless explicitly technical/admin

Do not use these as visible labels on normal business pages:

- workflow
- workflow lane
- incoming workflow
- outgoing workflow
- primary actions
- source type
- source file as a page title
- previewable
- output
- final output
- entity
- transition
- execute
- locked
- OCR draft
- supplier billing
- record preview
- workspace, when a real business area name exists

Allowed exception: these words may appear in developer documentation, admin diagnostics, tests about internals, or architecture comments where the user is not the audience.

## Sales side wording

Use customer-side words for documents and money coming from customers.

Preferred flow:

```text
Customer quotation -> Customer PO received -> Customer invoice -> Customer payment received
```

Preferred actions:

- Create customer quotation
- Mark customer PO received
- Issue customer invoice
- Record customer payment
- Download customer invoice PDF
- View customer PO file

Avoid:

- outgoing workflow
- outgoing revenue
- incoming paid
- customer-facing output
- customer source file preview

## Purchasing side wording

Use supplier/procurement words for documents and money owed to suppliers.

Preferred flow:

```text
Purchase request -> Supplier quotation -> Purchase order -> Goods receipt / Service acceptance -> Supplier invoice -> Supplier payment made
```

Preferred actions:

- Create purchase request
- Add supplier quotation
- Create purchase order
- Record goods receipt
- Record service acceptance
- Verify supplier invoice
- Match supplier invoice
- Record supplier payment
- Download purchase order PDF
- View supplier invoice file

Avoid:

- incoming workflow
- incoming procurement
- outgoing paid
- supplier billing
- supplier-facing output
- internal record output

## Document show page wording

The document show page must answer these questions in business language:

```text
What document is this?
What state is it in?
What needs attention?
What is the next action?
What blocks that action?
Where is the evidence?
What happened before?
```

Use these section labels:

- Next action
- Required before continuing
- Available actions
- Document details
- Document progress
- Evidence and attachments
- Approval history

Avoid:

- Before next action
- Primary actions
- Workflow
- Payment locked
- Transition status

## OCR and extraction wording

Users do not need to see OCR as the main concept. OCR is implementation detail.

Use:

- Details ready for review
- Review extracted details
- Verify invoice details
- Verify supplier quotation details
- Check the uploaded file against the extracted details

Avoid:

- OCR draft ready
- Verify OCR draft details
- OCR payload
- Extraction result
- Parsed source file

Exception: OCR may appear in admin/debug settings, technical logs, or developer documentation.

## PDF and file wording

Use the document name, not the generation pipeline.

Use:

- Customer quotation PDF
- Customer invoice PDF
- Purchase order PDF
- Goods receipt PDF
- Service acceptance PDF
- Supplier quotation file
- Supplier invoice file
- Uploaded file
- Source document, only when contrasting uploaded source versus generated PDF

Avoid:

- Output
- Final output
- Previewable source file
- Customer-facing output
- Supplier-facing output
- Internal record output

## Reports wording

Use finance/accounting labels that business users recognize.

Use:

- Finance reports
- Receivables
- Payables
- Receivables aging
- Payables aging
- Overdue receivables
- Supplier payments due soon
- Cash movement or Cash flow
- 12-month invoices and payments

Avoid:

- Money position, unless intentionally using a plain-language executive summary card
- Incoming paid
- Outgoing paid
- Revenue workflow
- Procurement workflow

## Challenge rule for Codex

Before adding or keeping any user-facing copy, Codex must challenge it with these questions:

1. Would a finance, sales, procurement, or admin user say this at work?
2. Does the wording name the business document or business action?
3. Is this label copied from a route, model, enum, database column, or workflow transition?
4. Does it expose implementation details such as OCR, output generation, source type, entity, or transition?
5. Would SAP, Odoo, NetSuite, QuickBooks, Xero, Zoho Books, Microsoft Dynamics, or another serious business document system use a similar phrase?
6. Can the label be shorter while staying clear?
7. Does the label say customer or supplier instead of incoming or outgoing?
8. Does the empty state explain what the user should do next?

If a label fails this challenge, rewrite it before implementation.

## Definition of done for wording changes

A wording-related task is not complete until:

- New copy follows this standard.
- Reused component labels do not break another module's meaning.
- Tests are updated to assert business-readable wording, not implementation wording.
- Empty states, button labels, headings, status badges, PDFs, and validation messages are reviewed together.
- The task summary lists any intentionally retained technical terms and why.

## Canonical examples

Good:

```text
Supplier invoice
Match this supplier invoice before recording payment.
Required before continuing
Available actions
Document progress
Details ready for review
No source file uploaded yet
Customer payments received
Supplier payments made
```

Bad:

```text
Supplier billing
Supplier payment is locked until this invoice is matched.
Before next action
Primary actions
Workflow
OCR draft ready
No previewable source file uploaded yet
Incoming paid
Outgoing paid
```
