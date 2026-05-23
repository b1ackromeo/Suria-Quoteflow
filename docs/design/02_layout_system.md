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
- dashboard/full-screen workspace fixes must be rendered and verified at `1366x768`; if they only fit a wider desktop monitor, they are not fixed
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
- on desktop, the preview panel should fill the available workbench height instead of leaving a large blank area below the PDF or file preview
- generated PDF iframes should fill the preview surface and clear loading text after the iframe loads or after a safe fallback timeout
- mobile should not force the full two-pane layout

Document index anti-redundancy rules:

- the selected list row is the source of record context: document number, party, status, date/reference, amount, Preview, and Open
- the preview pane should show the selected document output, file, or useful unavailable state, not repeat the same record metadata already visible in the selected row
- do not add visible preview headers such as "Previewing...", "Uploaded file preview", or repeated document titles when the selected row and page title already provide that context
- keep detailed preview headers, summaries, verification panels, and matching information on the document show command center, where the preview is no longer beside an already-selected list row
- when fixing document index layout, verify the actual rendered page for generated PDFs, uploaded files, and unavailable-preview states before calling the anti-redundancy issue resolved
- compact generated business PDFs should not create blank trailing pages in preview or download

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

Current implemented command-center rules:

- show blockers before actions for submit, approve, match, pay, close, and cancel
- keep cancellation visible only when backend rules allow it, and require a cancellation reason
- show linked document progress using actual related records when available
- keep OCR/manual verification panels focused on the current document task

## OCR-assisted capture pages

Use this standard for every page where QuoteFlow scans an uploaded business document, including supplier quotation evidence, customer PO files, goods receipt evidence, supplier invoices, and payment proof.

When OCR has produced a draft that is not verified yet, the page's active job is verification. The user should not have to hunt through the normal document command center to find the OCR task.

Required layout:

```text
1. Keep the normal sidebar and top search shell.
2. Show the document header and status.
3. Put the OCR assisted capture task in the main workspace immediately.
4. Show extracted fields and extracted line items once, as editable review fields.
5. Let the user include or exclude extracted line items before they become document lines.
6. Show the source PDF/image in a readable preview panel.
7. Keep Verify evidence and Re-run OCR visible near the capture task.
8. After verification, return the record to the normal command-center hierarchy.
```

Business-relevant text from the uploaded document must stay visible for review. This includes payment terms, validity, delivery, warranty, exclusions, supplier remarks, commercial notes, and terms and conditions. If QuoteFlow can structure the value, show it as an editable captured field. If structure is uncertain, preserve it as extracted notes or low-confidence evidence.

Anti-redundancy rules:

- do not repeat the same OCR field in both a summary card and an editable field
- do not show a selected attachment summary beside the same source preview unless it adds a decision or blocker
- do not show empty Payments, Approval history, Commercial notes, or generic Workflow cards while the unverified OCR task is active
- do not squeeze the PDF/image preview beside a cramped OCR form inside the same narrow panel
- do not bury Verify evidence below history, attachments, or unrelated details
- do not treat OCR line items as final document line items until the user verifies the evidence
- do not force every extracted quotation line into purchasing; only included/selected lines should be synced into the document
- do not expose database precision in purchasing fields; show `3` instead of `3.000`, while preserving meaningful fractional quantities such as `1.5` or `0.125`

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
1. Document basis, named for the work
   Examples: Customer request, Supplier quote, Purchase order, Matching basis
2. Customer / supplier / document details
   Examples: Customer and quote details, Supplier and invoice details
3. Pricing / budget / payment terms
   Examples: Pricing and terms, Budget and terms, Payment terms
4. Document lines
   Examples: Quoted items, Requested items, Order items, Invoice lines
5. Scope, justification, delivery, or commercial notes
```

Add a progress/readiness summary where practical:

```text
Supplier quote: uploaded
Customer details: missing
Quoted items: 3 lines
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

Implemented mobile behavior should preserve the task strip, compact actions, readable status text, and collapsed document previews. Do not regress mobile into a squeezed desktop two-column layout.

## Layout anti-patterns

Avoid:

- every section as equal white cards
- long forms without progress indication
- multiple primary buttons in one card
- action buttons below long history sections
- dashboard metrics without action links
- hiding blockers until after form submit
