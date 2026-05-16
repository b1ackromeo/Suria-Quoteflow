# QuoteFlow Agent Instructions

This repository is QuoteFlow: a Laravel 10 Blade monolith for commercial workflow management. The target deployment is Exabytes/Plesk shared hosting, not a VPS.

## Required workflow before editing

Before making code changes:

1. Read this `AGENTS.md` file.
2. Read the specific task file under `docs/codex-tasks/` assigned for the work.
3. For UI, UX, Blade, CSS, dashboard, form, navigation, copywriting, or accessibility work, read the relevant files under `docs/design/`.
4. For backend, dashboard, reports, exports, document lists, document show pages, PDF, OCR, or UI loading work, read `docs/performance/01_performance_and_loading_rules.md`.
5. Inspect all affected routes, controllers, models, migrations, Blade views, CSS, and tests before editing.
6. Summarize the current behavior and the smallest safe implementation plan.
7. Implement one objective at a time.
8. Add or update tests when behavior changes.
9. Summarize changed files, tests run, performance considerations, and remaining risks.

Do not make broad unrelated refactors while completing a task.

## Architecture to preserve

QuoteFlow must remain a shared-hosting-friendly Laravel monolith:

```text
Browser -> Laravel routes/controllers -> service/business-rule layer -> Eloquent models -> MySQL/MariaDB -> Blade views/downloads/redirects
```

Keep the app server-rendered with Blade and normal form submissions unless a task explicitly says otherwise.

## Shared-hosting constraints

Do not introduce mandatory dependencies on:

- Redis
- Laravel Horizon
- long-running queue workers
- WebSockets
- Docker
- Kubernetes
- Supervisor/systemd
- VPS/root access
- Meilisearch or Elasticsearch
- production Node/Vite build step
- headless Chrome/Puppeteer for normal PDF generation
- mandatory external OCR/API services for core workflow

Allowed patterns:

- Laravel controllers and service classes
- Eloquent models and MySQL/MariaDB-compatible migrations
- Blade views and partials
- DomPDF
- local/private file storage
- authenticated attachment preview/download routes
- file cache/session
- sync queue
- prebuilt CSS at `public/css/app.css`

## Performance and loading rules

Every change must preserve smooth page loading on shared hosting.

Do not introduce:

- obvious N+1 queries
- unbounded `all()` or `get()` on large operational tables
- dashboard queries that load full collections just to count/sum
- file contents loaded into normal page renders
- heavy JavaScript frameworks
- large hidden previews rendered for every list row
- report/export logic that loads all rows into memory

Prefer:

- pagination
- bounded queries
- eager loading only for relationships used by the page
- SQL aggregates for counts/sums
- streamed or chunked exports
- lightweight Blade partials
- progressive disclosure for heavy history/reference sections

Follow `docs/performance/01_performance_and_loading_rules.md` for performance-sensitive work.

## Business-rule placement

Prefer this separation:

- Controllers: request validation, authorization, transactions, redirects/views.
- Services: workflow rules, payment eligibility, supplier invoice verification, matching checks, exception policies.
- Models: relationships, casts, simple display helpers.
- Blade: present actions only when the backend would allow them.

Do not rely only on hiding buttons in Blade. Backend controllers/services must enforce the same rules.

## UI/UX rules

For interface work, preserve QuoteFlow as an action-first operations product:

```text
What is this record or area?
What state is it in?
What needs attention?
What is the next action?
What blocks that action?
Where is the evidence?
What happened before?
```

Follow `docs/design/` for layout, components, status language, UX writing, accessibility, responsive behavior, and visual identity.

Do not copy decorative dashboard trends blindly. Use Figma-style component discipline, Dribbble-level polish, and real workflow clarity.

## Definition of done

A task is complete only when:

- backend rule is enforced server-side where behavior changes
- Blade UI matches backend behavior
- validation messages are clear
- UI copy is concise and business-readable
- important blockers are visible before the user acts
- keyboard/focus accessibility is preserved for UI work
- audit trail is preserved where relevant
- tests are added or updated for changed behavior
- page loading remains smooth and bounded
- no obvious N+1 or unbounded large-table query is introduced
- shared-hosting constraints are preserved
- changed files are summarized
- tests run are summarized
- performance considerations are summarized
- remaining risks are listed

## Reference docs

Use these only as needed for the current task:

- `docs/codex-reference/09_SYSTEM_ARCHITECTURE.md`
- `docs/codex-reference/02_HOSTING_CONSTRAINTS_EXABYTES_PLESK.md`
- `docs/codex-reference/03_WORKFLOW_PAGE_AUDIT_FINDINGS.md`
- `docs/codex-reference/06_ACCEPTANCE_TEST_CHECKLIST.md`
- `docs/codex-reference/07_SUGGESTED_FILE_MAP.md`
- `docs/codex-reference/08_SHARED_HOSTING_DONT_BREAK_RULES.md`
- `docs/design/00_README.md`
- `docs/design/09_ui_ux_audit_findings.md`
- `docs/performance/01_performance_and_loading_rules.md`

For task execution, prefer the focused files under `docs/codex-tasks/` over rereading every long reference file every time.