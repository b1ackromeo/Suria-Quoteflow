# QuoteFlow Performance and Loading Rules

QuoteFlow must feel smooth on Exabytes/Plesk shared hosting. Performance is part of the definition of done for every backend, Blade, CSS, dashboard, report, document, and UI task.

## Target environment

QuoteFlow targets shared hosting, not a VPS. The confirmed PHP environment is capable, but still shared hosting:

- PHP 8.3
- LiteSpeed
- MySQL/MariaDB
- local filesystem storage
- file cache/session
- sync queue
- no mandatory Redis/Horizon/workers
- prebuilt CSS

Do not solve performance by adding infrastructure that shared hosting cannot guarantee.

## Performance principles

1. Query only what the page needs.
2. Paginate operational lists.
3. Avoid N+1 queries.
4. Avoid loading entire document history when a summary is enough.
5. Cache safe reference data where practical.
6. Keep Blade rendering simple.
7. Keep CSS/JS lightweight.
8. Keep PDF and OCR workflows bounded.
9. Make slow actions visibly progress or return clear feedback.
10. Preserve correctness before micro-optimization.

## Backend query rules

Use eager loading when a page displays related models.

Examples:

```php
Document::with(['customer', 'supplier', 'creator', 'approvals', 'payments'])
```

But do not eager load large relations unless needed.

Prefer counts/sums where possible:

```php
withCount('attachments')
withSum('payments', 'amount')
```

Avoid:

```php
Document::all()
```

for dashboard, index, reports, or exports.

Use pagination:

```php
paginate(25)
simplePaginate(25)
```

Use chunking for large exports:

```php
chunkById(500, ...)
```

## Dashboard performance

Dashboard must be action-first but query-safe.

Rules:

- limit recent records
- avoid unbounded all-time calculations unless indexed and cheap
- use aggregate queries for counts/sums
- use fixed reporting windows for trend charts, such as the last 6 or 12 months
- avoid loading full document collections to calculate totals in PHP
- avoid nested loops over documents and relations

## Document index performance

Document index/workbench must remain smooth.

Rules:

- paginate results
- filter in SQL, not in PHP collections
- eager load only the party/source fields shown in the list
- preview panel should not require loading huge attachment content
- avoid rendering every document preview at once

## Document show performance

Document show can load more details, but must still be bounded.

Rules:

- eager load needed relationships intentionally
- avoid unbounded audit/history lists; paginate or limit if they grow
- load attachment metadata, not file contents
- generate preview URLs; do not inline large files

## Reports and exports

Reports and CSV exports must be memory-safe.

Rules:

- use SQL aggregation where possible
- use streamed responses for large CSV exports
- chunk large datasets
- do not load all rows into memory
- avoid generating massive PDF reports on shared hosting

## Blade and CSS performance

Rules:

- avoid deeply nested loops over large collections
- avoid repeated expensive helpers inside loops
- compute repeated values in controller/service/view model before rendering
- reuse Blade partials carefully; avoid passing large unused objects
- keep CSS component classes consistent
- avoid heavy client-side frameworks
- avoid large inline scripts

## JavaScript performance

Small progressive enhancement JavaScript is allowed.

Rules:

- no SPA framework for core workflow
- no heavy frontend runtime
- no unnecessary animation libraries
- debounce search/filter inputs if they trigger requests
- keep keyboard behavior simple and reliable

## Asset performance

Production must not require npm build on server.

Rules:

- keep using prebuilt CSS at `public/css/app.css`
- avoid large unoptimized images in the app shell
- use local assets where possible
- avoid remote font dependencies if they slow initial render

## PDF performance

DomPDF should remain suitable for normal business documents.

Rules:

- keep templates simple
- avoid huge images
- avoid remote assets
- avoid JavaScript-rendered PDF dependencies
- avoid generating large reports as single PDFs

## OCR performance

OCR is allowed as a bounded local process when available.

Rules:

- keep OCR synchronous only for reasonably sized files
- enforce file size/type limits
- use temp files safely
- fail gracefully with manual verification fallback
- do not block the entire business workflow on OCR success

OCR scanning page loading rules:

- never load uploaded file contents directly into Blade
- use authenticated preview/download URLs for PDF/image evidence
- render only the active source preview, not hidden previews for every attachment
- keep unverified OCR pages focused on the capture task instead of rendering large unrelated history sections
- re-run OCR only from an explicit user action, not during normal page render
- preserve extracted business terms as structured fields or review notes without duplicating the same text in multiple cards

## Loading smoothness rules

Every page should provide immediate visual structure.

Use:

- clear page title
- primary action visible above the fold
- empty states instead of blank panels
- blocked-state messages before failed actions
- pagination for long lists
- progressive disclosure for history/reference data

Avoid:

- pages that render many hidden previews
- large tables without pagination
- slow dashboard queries
- loading full file contents into Blade
- expensive calculations inside Blade loops

## Performance acceptance checklist

Before accepting any Codex change, check:

- no obvious N+1 query introduced
- no unbounded `all()` / `get()` on large operational records unless justified
- list pages remain paginated
- dashboard queries are bounded and aggregate-based
- Blade loops do not perform repeated database calls
- heavy relations are eager-loaded only when needed
- file contents are not loaded into normal page render
- CSV/report exports are memory-safe
- UI remains responsive on desktop and mobile layouts
- no shared-hosting-incompatible dependency introduced

## Definition of done addition

A task is not done if it makes pages noticeably slower, introduces unbounded queries, or requires infrastructure not available on Exabytes/Plesk shared hosting.
