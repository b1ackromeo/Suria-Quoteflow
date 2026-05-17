# QuoteFlow Responsive Behavior

## Device strategy

QuoteFlow is primarily a desktop/laptop operations product. Desktop should be powerful. Mobile should be task-focused.

Do not force the full desktop document workbench onto mobile.

## Desktop

Target use:

- office laptop
- finance/procurement desktop
- manager review
- document preview and comparison
- long forms

Desktop patterns:

```text
sidebar + sticky header + main workspace
document list + preview pane
document preview + command side panel
form studio + live preview
```

## Tablet

Tablet should use stacked or tabbed layouts.

Recommended patterns:

```text
list -> preview as tab/drawer
show page -> preview first, actions second
form -> sections stacked, preview collapsed
```

Tablet should preserve:

- approval/rejection
- upload evidence
- invoice verification
- search
- document summary

## Mobile

Mobile should focus on task cards and summary views.

Recommended mobile home:

```text
My Work
- Pending approvals
- Supplier invoices to verify
- Supplier invoices to match
- Goods receipts needing evidence
- Overdue invoices
```

Recommended mobile actions:

- review document summary
- approve/reject
- upload evidence/photo
- verify invoice fields
- record simple payment
- search document

Avoid on mobile:

- full two-pane workbench
- large PDF comparison layout
- dense line-item editing
- complex milestone billing setup
- wide tables as the only UI

## Breakpoint behavior

### Desktop large

Use full layout:

```text
sidebar fixed
header sticky
list + preview
preview + command panel
```

### Desktop medium / laptop

Keep sidebar and preview, but reduce density:

- shorter descriptions
- compact filters
- collapsible secondary sections

### Tablet

Stack major panels:

```text
header
summary/action card
preview tab
facts tab
history tab
```

### Mobile

Use task mode:

```text
header/search
my work cards
document summary
actions
evidence/history collapsed
```

## Responsive forms

Desktop form:

```text
left: form sections
right: live preview/readiness
```

Tablet form:

```text
form sections first
preview collapsed below or tabbed
```

Mobile form:

```text
one section at a time
large inputs
no dense grids
save draft clearly available
```

## Responsive tables

For mobile and tablet, avoid wide tables where possible.

Use card rows for key operational lists:

```text
Document number
Party
Status
Amount
Next action
[Open]
```

Keep full tables for desktop.

## PDF/file previews

Desktop:

- inline preview pane
- generated PDF iframe acceptable
- uploaded file preview acceptable

Tablet:

- preview tab/drawer

Mobile:

- show document summary first
- preview opens in separate route/new tab where needed

## Header behavior

The header should remain useful but not consume too much vertical space on mobile.

Mobile header priority:

1. app/brand
2. search
3. menu
4. user/actions

Non-critical pills can hide on mobile.

## Acceptance checks

For every UI change, check:

- desktop 1440px
- laptop 1366px
- tablet around 768px
- mobile around 390px
- browser zoom 125% and 150%
- long party names
- long document numbers
- zero records
- many records
- validation errors

Hard stop rule:

- A UI change is not complete until the affected page has been rendered in browser at the relevant viewport sizes above.
- Dashboard and full-screen workspace changes must pass at `1366x768` before they can be called fixed.
- Passing a larger desktop monitor does not prove the laptop layout works.
- If any primary panel, action, chart, form control, or navigation item clips, overlaps, becomes unreachable, or creates unintended page-level scrolling, fix the layout and re-test before summarizing completion.
