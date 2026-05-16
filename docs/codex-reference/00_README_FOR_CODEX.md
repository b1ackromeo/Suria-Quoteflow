# QuoteFlow Codex Reference Pack

This folder is the reference pack for continuing QuoteFlow development with Codex in VS Code.

## How to use this folder

When starting a new Codex session, ask Codex to read this file first, then read the other files in this folder before editing code.

Suggested first prompt:

```text
Read docs/codex-reference/00_README_FOR_CODEX.md and the rest of docs/codex-reference. Then inspect the current code before making changes. Follow the system architecture, hosting constraints, workflow objectives, and acceptance checklist exactly.
```

## Files in this folder

- `01_PROJECT_CONTEXT.md` - what QuoteFlow is and how the current app is structured.
- `02_HOSTING_CONSTRAINTS_EXABYTES_PLESK.md` - hosting facts and rules for Exabytes/Plesk eBiz 17 Mini.
- `03_WORKFLOW_PAGE_AUDIT_FINDINGS.md` - audit findings from workflow pages vs backend logic.
- `04_IMPLEMENTATION_OBJECTIVES.md` - prioritized development objectives.
- `05_CODEX_TASK_PROMPTS.md` - ready-to-use task prompts for Codex.
- `06_ACCEPTANCE_TEST_CHECKLIST.md` - manual and automated checks before accepting changes.
- `07_SUGGESTED_FILE_MAP.md` - likely files to inspect and modify.
- `08_SHARED_HOSTING_DONT_BREAK_RULES.md` - constraints that must not be violated.
- `09_SYSTEM_ARCHITECTURE.md` - architecture reference for the Laravel monolith, document workflow, UI layers, OCR, payments, matching, reports, PDFs, and shared-hosting boundaries.

## Important rule

Do not assume the intended target is a VPS. The app is designed for Exabytes/Plesk shared hosting. Keep changes compatible with synchronous Laravel requests, Blade, local storage, MariaDB/MySQL, DomPDF, and prebuilt CSS.

## Architecture rule

Preserve QuoteFlow as a shared-hosting-friendly Laravel monolith:

```text
Browser -> Laravel routes/controllers -> service/business-rule layer -> Eloquent models -> MySQL/MariaDB -> Blade views/downloads/redirects
```

Do not convert it into an API-first SPA, microservice architecture, Redis/Horizon queue-worker system, or VPS-only deployment.

## Current high-priority themes

1. Keep OCR, but add a manual supplier invoice verification fallback.
2. Prevent payment recording before an invoice is workflow-ready.
3. Make supplier invoice matching a real checklist, not just a status button.
4. Fix pending approval navigation so the badge routes to all pending approvals, not only customer quotations.
5. Tighten direct/exception paths with notes, audit trail, and approval controls.
