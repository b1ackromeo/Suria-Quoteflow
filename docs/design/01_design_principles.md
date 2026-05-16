# QuoteFlow Design Principles

## Product personality

QuoteFlow should feel:

- professional
- calm
- fast
- trustworthy
- document-first
- action-driven
- finance-safe
- suitable for Malaysian SME operations

It should not feel:

- flashy
- gradient-heavy
- decorative
- chart-heavy without action
- over-animated
- like a generic SaaS template

## Principle 1: Action first, data second

Each page should prioritize the action the user needs to take next.

Good:

```text
Supplier invoice needs verification.
Verify invoice number and total before approval.
[Verify invoice details]
```

Bad:

```text
Supplier Invoice
Status: Draft
Total: RM 8,900
Attachments: 1
Approvals: 0
Payments: 0
```

## Principle 2: Show blockers before buttons

A user should know why an action is unavailable before they try it.

Examples:

```text
Payment locked until supplier invoice is matched.
Approval locked until invoice copy is verified.
Matching locked until supplier and total are confirmed.
```

## Principle 3: Keep workbench patterns

QuoteFlow is a document operations app. Preserve the strong patterns already present:

- document list + preview workbench
- document show command center
- document studio form
- PDF/file preview
- workflow timeline/checklist

Do not redesign it into a simple CRUD table app.

## Principle 4: Separate action, evidence, and history

Do not mix primary action, supporting evidence, and historical logs at the same visual weight.

Recommended order:

1. next action
2. blockers/readiness checklist
3. primary CTA
4. key facts
5. evidence/attachments
6. financials
7. history/audit

## Principle 5: Expert users need speed

Most users will repeat the same workflows daily.

Keep:

- predictable button locations
- short labels
- strong keyboard/focus behavior
- minimal repeated explanation
- progressive disclosure for long help text

## Principle 6: Exceptions must be explicit

Direct invoice, direct receipt, direct supplier invoice, and other exception paths should be visible and auditable.

UI copy should say:

```text
Use only when normal PO/receipt matching is not required. Add a reason.
```

## Principle 7: Use business language, not system language

Prefer:

```text
Verify invoice details
Record supplier payment
Match to receipt
Needs approval
```

Avoid:

```text
Update extraction
Transition status
Submit entity
Execute workflow action
```

## Principle 8: Do not rely on color alone

Status and workflow states need text and, where useful, icons or checklist labels.

Do not make green/red/blue the only indicator of meaning.

## Principle 9: Desktop is powerful; mobile is task-focused

Desktop can show list + preview + command panel.

Mobile should focus on:

- approve/reject
- upload evidence
- verify invoice
- record payment
- search and view summary

Do not force the full desktop workbench onto mobile.

## Principle 10: Keep implementation Blade/Tailwind friendly

Do not require:

- SPA framework
- front-end build on production
- heavy JavaScript runtime
- design libraries that break shared-hosting deployment

Use Blade partials, Tailwind component classes, and small progressive enhancement JavaScript where needed.
