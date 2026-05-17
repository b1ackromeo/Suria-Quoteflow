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
Primary system logo
Subordinate workspace identity
Contextual notification entry point
User/logout panel
```

The existing shell is suitable for QuoteFlow. Do not replace it with a top-nav-only layout.

Shell identity rules:

- the Suria QuoteFlow system logo sits at the top of the sidebar
- the active company/workspace badge sits directly below it, smaller and subordinate
- user identity and logout live at the bottom of the sidebar
- dashboard pages should not show duplicate header date ranges or notification shortcuts when the same work is already surfaced in Needs Attention

## Dashboard layout

The dashboard should be action-first, not metric-first.

Recommended order:

```text
1. Needs Attention
2. Quick Create
3. Financial Exposure
4. Sales and Purchasing
```

Target structure:

```text
Dashboard full-screen workspace
  Needs Attention
    pending approvals
    supplier invoices to verify
    supplier invoices to match
    overdue receivables
    supplier payments due
    compact clickable cards with visible workload bars

  Quick Create
    one home for create actions

  Financial Exposure
    open receivables
    open payables
    due aging
    lightweight chart bars for exposure and invoice aging

  Sales and Purchasing
    Customer sales: quotation -> customer PO -> customer invoice -> payment
    Supplier purchasing: purchase request -> purchase order -> receipt -> supplier invoice -> payment
    compact stage bars so bottlenecks are visible
    fixed 6-month movement chart for invoices issued and payments recorded
```

Avoid making the dashboard a collection of unrelated KPI cards.

Dashboard copy must use customer, supplier, sales, purchasing, invoice, and payment language. Do not expose internal direction labels such as "outgoing workflow", "incoming workflow", or "workflow lanes" to end users.

Dashboard anti-redundancy rules:

- do not duplicate create actions in both the header/hero and Quick Create
- do not duplicate pending approvals as header bell, queue card, overview card, and Needs Attention at the same time
- do not duplicate supplier payments due in both Needs Attention and Financial Exposure
- do not show a system-generated date range as if it is a user-selected dashboard filter
- do not use Recent Records as dashboard filler; document lists handle browsing, and audit/activity pages handle history
- desktop dashboard should fit the app viewport with internal panels instead of forcing page scroll for core dashboard content
- trend charts must use fixed reporting windows and aggregate queries; do not render open-ended historical charts on the dashboard

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
