# QuoteFlow Agent Instructions

This repository is QuoteFlow: a Laravel 10 Blade monolith for commercial workflow management. The target deployment is Exabytes/Plesk shared hosting, not a VPS.

## Required workflow before editing

Before making code changes:

1. Read this `AGENTS.md` file.
2. Read the specific task file under `docs/codex-tasks/` assigned for the work.
3. Inspect all affected routes, controllers, models, migrations, Blade views, and tests before editing.
4. Summarize the current behavior and the smallest safe implementation plan.
5. Implement one objective at a time.
6. Add or update tests when behavior changes.
7. Summarize changed files, tests run, and remaining risks.

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

## Business-rule placement

Prefer this separation:

- Controllers: request validation, authorization, transactions, redirects/views.
- Services: workflow rules, payment eligibility, supplier invoice verification, matching checks, exception policies.
- Models: relationships, casts, simple display helpers.
- Blade: present actions only when the backend would allow them.

Do not rely only on hiding buttons in Blade. Backend controllers/services must enforce the same rules.

## Definition of done

A task is complete only when:

- backend rule is enforced server-side
- Blade UI matches backend behavior
- validation messages are clear
- audit trail is preserved where relevant
- tests are added or updated for changed behavior
- shared-hosting constraints are preserved
- changed files are summarized
- remaining risks are listed

## Reference docs

Use these only as needed for the current task:

- `docs/codex-reference/09_SYSTEM_ARCHITECTURE.md`
- `docs/codex-reference/02_HOSTING_CONSTRAINTS_EXABYTES_PLESK.md`
- `docs/codex-reference/03_WORKFLOW_PAGE_AUDIT_FINDINGS.md`
- `docs/codex-reference/06_ACCEPTANCE_TEST_CHECKLIST.md`
- `docs/codex-reference/07_SUGGESTED_FILE_MAP.md`
- `docs/codex-reference/08_SHARED_HOSTING_DONT_BREAK_RULES.md`

For task execution, prefer the focused files under `docs/codex-tasks/` over rereading every long reference file every time.
