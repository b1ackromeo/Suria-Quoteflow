# Task 014 - Project / Job and WBS Control Layer

## Why this task exists

QuoteFlow currently behaves primarily as a commercial document workflow system. That is valid for quotation, purchase request, purchase order, goods receipt, invoice, approval, matching, payment, and close tracking.

However, if QuoteFlow is intended to become a stronger global commercial operations system, it must also support project/job-level commercial control. The current document chain alone cannot fully answer:

- Which project or job does this document belong to?
- Which work package, deliverable, WBS item, or cost code does each line belong to?
- Which supplier cost supports which customer deliverable?
- What has been quoted, committed, received, invoiced, paid, and left as margin?
- Will this purchase request or supplier PO exceed the project or WBS budget?
- Is approval being granted based only on document status, or also on commercial impact?

This task captures the recommended next development direction: keep QuoteFlow simple for small jobs, but add a lightweight project/WBS control layer for larger jobs.

## Product principle

Do not force WBS into every quotation.

QuoteFlow should support two operating modes:

```text
Simple document flow
Project-controlled flow
```

### Simple document flow

Use for small jobs, one-off supply, simple services, low-risk procurement, and straightforward invoices.

Example:

```text
Customer quotation -> Customer PO received -> Customer invoice -> Payment received
Purchase request -> Supplier quotation -> Supplier PO -> Goods receipt -> Supplier invoice -> Supplier payment
```

No WBS should be required in this mode.

### Project-controlled flow

Use for larger project-based jobs, margin-sensitive jobs, multi-stage delivery, subcontractor work, multiple suppliers, partial billing, progress tracking, and budget control.

Example:

```text
Project / Job
  -> Work breakdown / WBS / Cost codes
    -> Customer quotation lines
    -> Customer PO lines
    -> Purchase request lines
    -> Supplier PO lines
    -> Goods receipt or service acceptance lines
    -> Supplier invoice lines
    -> Project margin and budget reports
```

The project/WBS layer should sit above the existing document model. It should not replace the central `Document` architecture.

## Recommended scope

Start with three concepts only:

1. Project / Job
2. WBS item / Cost code / Work item
3. Project commercial summary

Avoid starting with full project management, Gantt charts, resource scheduling, earned value management, or complex accounting.

## Data model direction

Preserve the existing shared-hosting-friendly Laravel monolith and central document workflow model.

Add new tables:

```text
projects
wbs_items
```

Extend existing tables:

```text
documents.project_id nullable
document_items.project_id nullable
document_items.wbs_item_id nullable
```

Recommended `projects` fields:

```text
id
project_code
name
customer_id
manager_id
status
start_date
expected_completion_date
contract_value
budget_amount
margin_target_percent
description
timestamps
```

Recommended `wbs_items` fields:

```text
id
project_id
parent_id
code
name
description
cost_type
revenue_budget
cost_budget
sort_order
status
timestamps
```

Recommended indexes:

```text
documents(project_id)
document_items(project_id)
document_items(wbs_item_id)
wbs_items(project_id)
wbs_items(parent_id)
```

Do not rely on JSON-heavy reporting. Keep calculations MySQL/MariaDB-friendly and shared-hosting-safe.

## Important design rule: line-level linking

Do not only add `project_id` to the document header.

Document-level linking is useful for navigation and filtering, but WBS control only becomes meaningful when document lines can be assigned to project work items.

Required relationship direction:

```text
Customer quotation line -> WBS item
Customer PO line -> WBS item
Purchase request line -> WBS item
Supplier PO line -> WBS item
Goods receipt / service acceptance line -> WBS item
Supplier invoice line -> WBS item
```

When converting or creating follow-on documents, preserve project and WBS links from source lines where possible.

Example:

```text
Purchase request line
  -> Supplier PO line
  -> Goods receipt line
  -> Supplier invoice line
```

The copied lines should retain the same `project_id` and `wbs_item_id` unless the user deliberately changes them with permission.

## Approval rule direction

Current approvals are mostly document lifecycle approvals. The next version should add commercial-impact warnings.

### Customer quotation approval

Show:

```text
Linked project/job
Quoted revenue
Estimated cost
Expected gross margin
Margin target
Margin below target warning
Unassigned project/WBS lines warning
```

Possible rule:

```text
If project-controlled mode is enabled and expected margin is below target, require manager approval or override reason.
```

### Purchase request approval

Show:

```text
Supplier quote evidence
Project/job
WBS item / cost code
WBS cost budget
Already committed cost
Remaining budget before request
Remaining budget after request
Budget overrun warning
```

Possible rule:

```text
If the request exceeds WBS remaining budget, require manager/admin approval and a reason.
```

### Supplier PO approval

Show:

```text
Source purchase request
Supplier quotation evidence
Project/job
WBS budget impact
Committed cost after issue
Expected margin impact
```

Possible rule:

```text
Do not issue supplier PO if it breaks project/WBS budget without an approved override.
```

### Supplier invoice verification and matching

Show:

```text
Matched supplier PO
Matched goods receipt / service acceptance
Invoice variance
WBS actual cost impact
```

Possible rule:

```text
If supplier invoice exceeds PO/receipt tolerance, require matching override reason.
```

Backend business rules must enforce these constraints. Do not only hide or show Blade buttons.

## User flow direction

### Creating a customer quotation

Recommended flow:

```text
New customer quotation
-> choose customer
-> optional: link to project/job
-> optional: assign lines to work items / cost codes
-> show project impact and margin preview
-> submit for approval
-> issue
```

Do not block simple quotations that have no project.

### Creating a purchase request

Recommended flow:

```text
Purchase request
-> choose project/job when relevant
-> choose WBS item / cost code for each relevant line
-> attach or verify supplier quotation
-> compare requested cost against WBS budget
-> submit for approval
```

### Goods receipt / service acceptance

Goods receipt and service acceptance should inherit project and WBS links from supplier PO lines. Service jobs should not be forced into goods-only language.

### Supplier invoice

Supplier invoice lines should inherit project and WBS from matched PO/receipt lines so actual cost can be reported by project and WBS item.

## Reporting direction

Start with practical commercial reports, not complex scheduling reports.

### Project commercial summary

Show:

```text
Quoted revenue
Customer PO value
Customer invoiced
Customer paid
Estimated cost
Committed supplier PO cost
Received / accepted cost
Supplier invoiced actual cost
Supplier paid
Expected margin
Actual margin
Unbilled revenue
Unpaid supplier cost
Budget remaining
```

### WBS budget report

Show:

```text
WBS item
Revenue budget
Cost budget
Committed PO cost
Received / accepted cost
Supplier invoice actual cost
Remaining budget
Variance
```

### Exception report

Show:

```text
Unassigned project-controlled document lines
POs exceeding WBS budget
Supplier invoices exceeding tolerance
Customer invoices not linked to a project when expected
Direct invoices without sufficient evidence
Projects below margin target
```

## UI and wording guidance

Add only one major navigation area at first:

```text
Projects
```

Inside a project, use tabs or sections such as:

```text
Overview
Work breakdown
Documents
Budget and margin
Activity
Attachments
```

Avoid exposing the term `WBS` too aggressively to normal users. Use business-friendly labels:

```text
Work breakdown
Work item
Cost code
Project section
Budget item
```

For technical documentation, `WBS` is acceptable. For UI, prefer user-readable wording unless the page is clearly intended for project-control users.

## Phased implementation plan

### Phase 1 - Project container

Goal: group documents by project/job.

Build:

```text
Project CRUD
Project list and detail page
documents.project_id
Project selector on relevant document forms
Project document list
Basic project totals
```

This answers:

```text
Which documents belong to this job?
```

### Phase 2 - WBS / cost code layer

Goal: assign document lines to work items.

Implementation status: foundation completed in the Laravel Blade app.

Build:

```text
WBS item CRUD inside project
document_items.project_id
document_items.wbs_item_id
Line-level work item selector
Copy project/WBS links during document conversion
Basic WBS budget summary
```

Implemented behavior:

```text
Project pages include Work breakdown management for work items and cost codes.
Document line forms can assign optional work items after a project is selected.
Document validation prevents assigning a work item from a different project.
New and converted document lines preserve project and work item links.
Project Work breakdown summarizes line-level budget, revenue, committed cost, actual cost, and unassigned project lines.
Search, readiness checks, and database backup verification include the WBS/work-item table.
```

This answers:

```text
Which part of the project created this revenue or cost?
```

### Phase 3 - Budget and margin controls

Goal: make approvals commercially intelligent.

Implementation status: approval controls completed in the Laravel Blade app.

Build:

```text
Project margin calculation service
WBS budget calculation service
Approval warnings
Budget overrun reason
Margin below target warning
Manager/admin override controls
Audit trail entries for overrides
```

Implemented behavior:

```text
Project-linked customer quotations show quoted revenue, estimated cost, expected gross margin, expected margin percentage, and margin target during approval.
Project-linked purchase requests, purchase orders, and supplier invoices show WBS budget impact for assigned work items.
Approval screens show budget and margin warnings before the approval action when project data creates a commercial exception.
Approving a document with a budget overrun or margin-below-target exception requires an approval reason.
Commercial approval overrides are recorded in the audit trail with warning and metric context.
Simple document flow without a project remains available and does not require a commercial approval reason.
```

This answers:

```text
Should this document be approved based on budget and margin impact?
```

### Phase 4 - Project commercial dashboard

Goal: give management visibility.

Implementation status: project detail visibility completed in the Laravel Blade app.

Build:

```text
Project margin report
WBS budget report
Committed vs actual cost report
Unassigned lines report
Project exceptions report
```

Implemented behavior:

```text
Project pages show budget and margin reporting from persisted documents, line items, work items, and payments.
Project pages show expected margin percentage, actual margin percentage, unbilled revenue, unpaid supplier cost, and budget remaining.
Work breakdown reporting includes received / accepted cost, supplier actual cost, remaining budget, and actual variance by work item.
Project exception reporting highlights margin below target, unassigned project lines, work-item budget overruns, actual cost over budget, and supplier actuals above committed purchase order cost.
Project commercial calculations now live in a dedicated report service instead of controller-only calculations.
The Projects list includes portfolio-level customer confirmed value, supplier committed cost, expected margin, unassigned line count, and projects needing review.
Each listed project shows customer confirmed value, supplier committed cost, expected margin, budget remaining, and visible review reasons before opening the full project report.
The project commercial review can be exported as a filtered CSV using streamed, chunked rows for shared-hosting-safe reporting.
The Projects list and CSV export can be filtered by commercial review status, including work-item budget overruns, actual cost overruns, unassigned project lines, margin exceptions, and supplier actuals above purchase order commitments.
Project portfolio totals on the Projects list follow the active search, status, and commercial review filters so the visible cards, header counts, and side totals remain aligned.
Each project detail page can export its commercial summary, exception list, and work breakdown budget report as a CSV for management review.
Project detail pages now include review evidence with document-line drill-down for unassigned project lines, work-item budget overruns, actual cost overruns, and supplier invoice lines behind supplier-actual-above-committed exceptions.
```

This answers:

```text
Which projects are profitable, over budget, blocked, or commercially risky?
```

### Phase 5 - Advanced project billing later only

Do not start here.

Future possible additions:

```text
Milestone billing
Progress claims
Retention
Variation orders
Partial delivery billing
```

These should come only after project, WBS, budget, margin, and reporting foundations are stable.

## Explicit non-goals for this task

Do not implement these in the first version:

```text
Full earned value management
Gantt chart
Resource scheduling
Timesheets
Complex project accounting
Multi-company consolidation
Inventory reservation
Manufacturing BOM
Heavy workflow engine
Microservices
API-first SPA rewrite
Queue-heavy automation
```

QuoteFlow must remain a shared-hosting-friendly Laravel Blade monolith.

## Acceptance checklist

Before accepting any implementation of this task, verify:

- Existing simple document flow still works without requiring a project.
- Project-controlled flow can link document headers to projects.
- Project-controlled flow can link document lines to WBS/work items.
- Follow-on documents preserve project/WBS links where appropriate.
- Approval screens show budget and margin impact when project-controlled data exists.
- Backend services enforce budget/margin override requirements, not only Blade visibility.
- Reports calculate from persisted relational data, not fragile UI-only totals.
- Wording is business-readable and aligned with `docs/codex-reference/10_BUSINESS_WORDING_STANDARD.md`.
- Implementation respects Exabytes/Plesk shared-hosting constraints.
- Tests cover simple flow and project-controlled flow separately.

## Key product question this task must answer

For any project/job, QuoteFlow should be able to answer:

```text
How much did we quote?
How much did the customer confirm?
How much did we commit to suppliers?
How much did we receive or accept?
How much did suppliers invoice us?
How much did we invoice the customer?
How much has been paid on both sides?
What budget and margin remain?
```

If the implementation cannot answer that, the project/WBS layer is not yet useful.
