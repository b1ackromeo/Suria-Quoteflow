# QuoteFlow Visual Identity

## Visual target

QuoteFlow should feel like:

```text
Stripe/Linear-style clarity
+ Figma UI-kit discipline
+ Dribbble-level polish
+ Malaysian SME operations practicality
```

It should be professional and calm, not decorative.

## Primary color

Current primary blue `#0a4f93` is suitable and should remain the brand anchor unless the company brand changes.

Use primary blue for:

- main CTAs
- active navigation
- active workflow step
- links
- document-facing brand accents

Do not overuse primary blue for every positive status.

## Neutral system

Use slate neutrals for:

- body text
- borders
- cards
- muted labels
- backgrounds

Recommended hierarchy:

```text
Page background: very light blue/slate
Card background: white
Muted surface: slate-50 or blue-50 at low opacity
Border: slate-200
Primary text: slate-950
Secondary text: slate-600/500
Metadata: slate-400/500
```

## Semantic colors

Use semantic colors consistently:

```text
Amber: waiting / needs action
Blue: approved / ready
Sky or purple: issued / external movement
Teal: matched / control passed
Cyan: part paid / money in progress
Emerald: paid / complete
Red: rejected / cancelled / error
Slate: draft / closed / neutral
```

## Typography

Current font stack with Inter/system sans is appropriate.

Rules:

- page titles should be visually clear and not tiny
- section titles should be concise
- important instructions should not use micro text
- uppercase tiny labels are allowed only for low-priority metadata/kickers
- avoid excessive bold weight in large paragraphs

Recommended type roles:

```text
Page title: strong, 24-32px depending layout
Section title: 16-20px
Body/help: 14px-15px
Metadata/kicker: 10px-12px uppercase
Table text: 13px-14px
```

## Spacing

Current UI can feel dense because many cards use similar spacing and weight.

Recommended spacing rhythm:

```text
Critical/action cards: more breathing room
Normal information cards: moderate spacing
History/reference sections: compact
Tables: compact but readable
```

Do not make everything equally padded and shadowed.

## Card style

Use shadow sparingly.

Recommended:

- action/blocker cards: subtle emphasis
- normal cards: border + very light shadow or no shadow
- history sections: border/divider, minimal shadow

Avoid heavy shadows and decorative gradients.

## Iconography

Icons should clarify function, not decorate.

Use icons for:

- search
- calendar
- notifications
- document types
- workflow state
- warnings/blockers
- success/checklist

Rules:

- icons must not be the only label for critical actions
- icon-only controls require accessible labels
- keep icon style consistent

## Empty states

Use simple polished empty states.

Structure:

```text
No [records] yet
[Short explanation.]
[Primary action]
```

Optional illustration can be added later, but do not block implementation on custom artwork.

## Dashboard visual direction

Dashboard should lead with a polished action area:

```text
Today’s Work
Pending approvals
Supplier invoices to verify
Invoices overdue
Payments due
```

Metrics should support action, not dominate the page.

## Document preview visual direction

Document preview should feel like a paper/workspace surface:

- slightly cooler background around preview
- clear preview toolbar
- generated vs uploaded labels
- unavailable preview state with next action

## Anti-patterns

Avoid:

- decorative gradients as primary design language
- fake analytics charts
- tiny unreadable labels for important actions
- too many equal-weight cards
- multiple unrelated accent colors on one screen
- hover-only discoverability
- copying Dribbble visuals that do not support the workflow
