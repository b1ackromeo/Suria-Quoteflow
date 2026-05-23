# QuoteFlow UI/UX Audit Findings

This audit is based on the current Laravel Blade and CSS implementation. It is not a pixel-perfect browser screenshot review.

## Current strengths

QuoteFlow already has stronger UX foundations than a normal CRUD system:

- fixed sidebar and sticky header shell
- global search
- role-aware navigation
- dashboard with metrics and workflow summaries
- document list + preview workbench
- document show command center
- document studio form
- status chips
- workflow timeline
- PDF/file preview pattern
- context-aware source path cards
- domain-specific copy for quotations, POs, receipts, invoices, and supplier invoices

The correct direction is to harden and polish the existing architecture, not throw it away.

## Implementation status - 2026-05-23

The main audit priorities have now been implemented in the Blade monolith:

- global pending approvals route and sidebar/header entry behavior
- payment eligibility messages and server-side payment blocking
- supplier invoice verification and matching checklist behavior
- visible blockers before submit, approve, match, pay, close, and cancel
- separate Preview and Open actions on document list rows
- action-first dashboard with fewer duplicate controls
- document show command center hierarchy
- form studio wording, readiness, and source-path guidance
- semantic status styling and text-first state meaning
- linked document progress chain
- task-focused mobile layout behavior
- neutral company profile defaults, initials fallback, and active-company money/date formatting
- full-height document index preview pane with generated PDF loading fallback
- compact generated PDF output without blank trailing pages

Keep the historical findings below as design guardrails. New work should preserve these completed improvements instead of recreating the older CRUD-heavy patterns.

## Current weaknesses

### 1. Cognitive overload

Many screens show too much at the same visual weight.

The document show page currently has many cards and disclosures: next action, workflow actions, details, workflow progress, billing stages, line items, attachments, notes, payments, and approval history.

Target improvement:

```text
Next action -> blockers -> primary action -> key facts -> evidence -> financials -> history
```

### 2. Dashboard is redundant and summary-first

The dashboard has useful metrics, but it can drift into duplicate action surfaces: create buttons in more than one place, approval queue plus approval overview plus header notification, and recent records that belong in document lists.

Target improvement:

```text
Needs Attention
- pending approvals
- supplier invoices to verify
- supplier invoices to match
- overdue receivables
- payments due soon

Quick Create
- the single home for create actions

Financial Exposure
- open receivables
- open payables
- due aging

Sales and Purchasing
- compact customer sales and supplier purchasing continuation links
```

The dashboard should be a full-screen operations workspace on desktop. It is not a recent-record feed and should not require scrolling just to understand the primary work state.

Dashboard labels should be written for the end user. Avoid internal direction terms such as "outgoing workflow", "incoming workflow", or "workflow lanes" in the visible UI.

### 3. Status chips are visually consistent but not semantically rich enough

Approved, issued, received, fulfilled, and matched should not all feel like the same state.

Target improvement:

Use semantic groups:

```text
Draft, Waiting, Ready, External movement, Control passed, Money in progress, Money complete, Stopped, Final
```

### 4. UX writing is business-aware but too wordy

The copy is often accurate but reads like internal documentation.

Target improvement:

Use short labels, one-line helpers, and detailed guidance only when needed.

Example:

```text
Before: Choose whether the supplier invoice is matched against a receiving record, a purchase order, or an approved direct supplier invoice exception.
After: Select matching basis
```

### 5. Accessibility needs tightening

Known risk areas:

- clickable document rows using `role="button"` while containing links
- dense microcopy
- sticky headers and scroll containers hiding focus
- color-heavy status meaning
- icon-only controls

Target improvement:

Separate preview/open actions, make blockers text-first, test keyboard/focus behavior, and avoid color-only meaning.

### 6. Mobile should be task-focused

The full desktop workbench is too heavy for mobile.

Target improvement:

Mobile should focus on task cards:

```text
approve/reject
verify invoice
upload evidence
record payment
search and view summary
```

## Best final design direction

QuoteFlow should not copy Dribbble dashboards blindly and should not import a generic Figma kit.

Final direction:

```text
Action-first dashboard
Document command center
Checklist-driven workflow panels
Design-token-based components
Semantic status system
Task-focused mobile mode
Reduced copy density
Accessibility-aware interactions
```

## P0 UI/UX priorities

Status: implemented as of 2026-05-23. Preserve these behaviors during future changes.

1. Fix pending approval destination and make it a real global action surface.
2. Add payment eligibility messaging and blocked-state UI.
3. Add supplier invoice verification/matching checklist panels.
4. Add visible blockers before submit/approve/match/pay actions.
5. Improve document row accessibility by separating preview/open behavior.
6. Add confirmation UX for approve, reject, issue, match, close, cancel, and payment actions.

## P1 UI/UX priorities

Status: implemented as of 2026-05-23, with remaining work limited to polish and future workflow expansion.

1. Redesign document show side panel hierarchy.
2. Add role-aware My Work shortcuts.
3. Reduce form copy by 30-40 percent.
4. Add form progress/readiness summary.
5. Redesign status semantics.
6. Add linked-document workflow chain.

## P2 UI/UX priorities

Status: mostly implemented as of 2026-05-23. Treat future work as consistency polish.

1. Tighten spacing scale.
2. Reduce overuse of equal-weight cards and shadows.
3. Standardize empty states.
4. Add consistent workflow icons.
5. Improve table density controls.
6. Create mobile task mode.

## Design acceptance rule

A UI change is acceptable only when:

- next action is obvious
- blockers are visible before the user acts
- backend and UI rules match
- copy is concise and business-readable
- keyboard/focus behavior remains usable
- shared-hosting constraints are preserved

Additional dashboard rule:

- one action should have one obvious home; do not create duplicate dashboard controls for the same user job
