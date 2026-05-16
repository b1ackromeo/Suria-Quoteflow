# QuoteFlow Accessibility Checklist

## Accessibility baseline

QuoteFlow is a business operations system. It must remain usable for keyboard users, laptop users, older users, and users reviewing dense financial documents.

Use WCAG-aware practices even when not formally certifying the product.

## Keyboard access

Required:

- Every interactive control must be reachable by keyboard.
- Focus state must be visible.
- Focus must not be hidden behind sticky headers or panels.
- Buttons and links must use correct elements.
- Do not use clickable `div` or `article` unless there is no better semantic option.

## Document row interaction

Current document rows combine preview selection and open links. Future UI should separate these actions clearly.

Preferred pattern:

```text
[Preview] [Open]
```

or:

```text
Row button selects preview
Separate Open link opens document
```

Rules:

- screen reader users must understand what each action does
- keyboard users must not accidentally open a document when intending to preview
- selected row must have a non-color-only indicator

## Icon-only controls

Icon-only controls must have an accessible name.

Examples:

```html
<button aria-label="Open pending approvals">...</button>
<a aria-label="Search documents">...</a>
```

If a badge count is shown, the target page must match the badge meaning.

## Color and status

Do not rely on color alone.

Status should include readable text:

```text
Pending approval
Matched
Part paid
Cancelled
```

Checklist states should use text plus visual sign:

```text
Passed: Invoice copy uploaded
Blocked: Invoice number not verified
Locked: Payment requires matched invoice
```

## Text size and density

Avoid using tiny text for important instructions.

Guidance:

- main body/help copy: normal small text, not microcopy
- metadata/kickers may use smaller uppercase text
- critical blockers should be easy to read
- dense tables should preserve row spacing and contrast

## Forms

Required:

- visible labels for all fields
- error messages near fields where practical
- business-readable error summaries
- required/optional clarity where ambiguity exists
- no placeholder-only labels

## Focus and sticky UI

Sticky headers and split-pane layouts must be tested with keyboard navigation.

Check:

- tabbing into side panel
- tabbing into preview pane controls
- tabbing through long form sections
- focus ring visibility inside scroll containers
- browser zoom at 125%, 150%, and 200%

## Target size

Important tap/click targets should be comfortable.

Rules:

- primary actions should be at least normal button height
- small action chips should not be the only path to critical actions
- mobile task cards need large touch targets

## Tables

Tables should have:

- meaningful column headers
- clear action links
- right-aligned money values
- no hover-only critical actions
- status text visible without color reliance

## Disclosures and accordions

When using `<details>`:

- summary text must be descriptive
- critical blockers should not be hidden in collapsed sections
- history/reference sections may be collapsed
- next actions should stay visible

## Motion

Avoid animation that affects task completion.

If transitions are added:

- keep them short
- do not require animation to understand state
- respect reduced-motion preferences where practical

## Accessibility acceptance checklist

Before accepting UI work:

- keyboard can reach all actions
- focus state is visible
- screen-reader names exist for icon-only controls
- color is not the only status indicator
- form errors tell the user how to fix the issue
- mobile touch targets are usable
- sticky UI does not hide focused controls
- critical blockers are visible without opening history sections
