# QuoteFlow Layout System

## Layout philosophy

QuoteFlow should use layout to guide work, not just display information.

Each layout must make clear:

1. where the user is
2. what needs attention
3. what action is next
4. what evidence supports the action
5. where history/details live

## Global shell

Keep the current shell pattern:

```text
Fixed desktop sidebar
Sticky top header
Main content canvas
Role-aware navigation
Global search
Company identity
Notification entry point
User chip
```

The existing shell is suitable for QuoteFlow. Do not replace it with a top-nav-only layout.

## Dashboard layout

The dashboard should be action-first, not metric-first.

Recommended order:

```text
1. Today's Work / Needs Attention
2. Workflow Health
3. Financial Snapshot
4. Recent Activity
5. Secondary summaries
```

Target structure:

```text
Dashboard
  Today’s Work
    Pending approvals
    Supplier invoices to verify
    Supplier invoices to match
    Customer invoices overdue
    Payments due soon

  Workflow Health
    Outgoing: quotation -> PO -> invoice -> payment
    Incoming: PR -> PO -> receipt -> supplier invoice -> payment

  Financial Snapshot
    Open receivables
    Open payables
    Aging
    Cash movement

  Recent Activity
    Latest documents
    Recent approvals/payments
```

Avoid making the dashboard a collection of unrelated KPI cards.

## Document index workbench

Preserve the two-pane workbench:

```text
Left: document list and filters
Right: selected document preview
```

Purpose:

- scan many records quickly
- preview before opening
- avoid unnecessary navigation
- keep document operations fast

Rules:

- list rows must support both preview and open actions clearly
- selected row must be visually obvious
- filters must remain compact
- preview panel should show useful empty/loading/unavailable states
- mobile should not force the full two-pane layout

## Document show command center

The show page should be the operational command center.

Recommended right-panel hierarchy:

```text
1. Next action
2. Blocking checklist / readiness
3. Primary action
4. Key facts
5. Related document chain
6. Evidence / attachments
7. Financials
8. History
```

Avoid equal-weight cards for everything.

Primary action should be obvious and unique whenever possible.

Examples:

```text
[Submit for approval]
[Verify invoice details]
[Mark matched]
[Record payment]
[Close document]
```

## Supplier invoice layout

Supplier invoice pages need explicit control states:

```text
Invoice file uploaded?
Invoice details verified?
Approval submitted?
Approved?
Matched?
Payment allowed?
```

Recommended right-panel order for supplier invoices:

```text
1. Next action
2. Verification panel
3. Matching checklist
4. Primary action
5. Payment lock/status
6. Key facts
7. Attachments
8. History
```

## Form studio layout

Keep the one-page studio layout, but make it feel guided.

Recommended sections:

```text
1. Source
2. Party and dates
3. Money and terms
4. Items
5. Notes and evidence
```

Add a progress/readiness summary where practical:

```text
Source: selected
Party: missing
Items: 3 lines
Terms: standard
Ready to save: no
```

## Mobile layout

Mobile should use task cards, not desktop workbench.

Recommended mobile priorities:

```text
My Work
Search
Approve/reject
Verify invoice
Upload evidence
Record payment
View summary
```

Desktop workbench features can become tabs, drawers, or separate screens on mobile.

## Layout anti-patterns

Avoid:

- every section as equal white cards
- long forms without progress indication
- multiple primary buttons in one card
- action buttons below long history sections
- dashboard metrics without action links
- hiding blockers until after form submit
