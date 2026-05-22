# PR #32 Codex handoff: neutral company profile defaults

Date: 2026-05-22
Repo: `b1ackromeo/Suria-Quoteflow`
PR: `#32` — `Use neutral company profile defaults`
Branch: `feature/company-profile-neutral-defaults`
Latest known head before this handoff file: `46d527291d7200c6589ff4276ba823e732999532`

## Required reading before editing

1. Read `AGENTS.md`.
2. Read this task file.
3. For any user-facing wording, also read `docs/codex-reference/10_BUSINESS_WORDING_STANDARD.md`.
4. For any report, PDF, document list, dashboard, export, or loading-sensitive change, also read `docs/performance/01_performance_and_loading_rules.md`.

## Objective

Make QuoteFlow suitable for global first-run installs by removing RC Technology / Malaysia-specific identity from default company profile data.

The desired first-run default posture is:

- Company name: `Your Company Name`
- Country: `Other`
- Timezone: `UTC`
- Base currency: `USD`
- Currency display: neutral code-based display by default
- No default logo
- No Malaysia-specific tax/registration assumptions unless the user explicitly selects a Malaysia preset or configures them

Malaysia/RM behavior must remain available and covered when explicitly selected/configured. Do not remove Malaysia support; only remove Malaysia as the implicit global default.

## Current PR status

PR #32 is open and mergeable, but GitHub Actions is currently red.

Known status before this handoff commit:

- PR head: `46d527291d7200c6589ff4276ba823e732999532`
- Workflow run id: `26271873982`
- Failing job id: `77327013944`
- Failure area: Laravel test job
- The PR had 4 changed files before this handoff file was added

Use GitHub Actions logs or local tests to confirm the exact current failure before patching. Do not guess from this handoff alone.

## What already happened

The PR changed company profile defaults toward a neutral/global install posture and added focused test coverage for neutral defaults and Malaysia RM behavior.

A later compatibility patch changed `app/Models/CompanyProfile.php` so `CompanyProfile::defaults()['tagline']` returns:

```php
'Reliable Infrastructure. Connected Future.'
```

That patch was made to satisfy an assumed workflow preview assertion, but it may conflict with the objective because it preserves an old branded default. Codex must inspect the actual failing assertion and decide the correct fix. Prefer neutral first-run defaults unless the branded tagline is intentionally part of a seeded/demo company record rather than the global fallback.

A report currency mismatch was considered but should not be assumed to be the failing issue. Report rows may correctly format by each document's own currency, so `MYR 880.00` can be correct for MYR documents even when the base profile currency is USD.

## Files to inspect

Inspect at least these before editing:

- `AGENTS.md`
- `docs/codex-tasks/pr-32-neutral-company-profile-defaults-handoff.md`
- `app/Models/CompanyProfile.php`
- `tests/Feature/CompanyProfileSettingsTest.php`
- `tests/Feature/IncomeWorkflowTest.php`
- `resources/views/reports/index.blade.php` if the failure mentions reports, receivables, payables, currency, or ledger output
- Any seeder/factory/migration that supplies default `CompanyProfile` data

## Recommended next steps for Codex

1. Check out `feature/company-profile-neutral-defaults`.
2. Read `AGENTS.md` and this task file first.
3. Pull the current CI failure, for example:

```bash
gh run view 26271873982 --job 77327013944 --log
```

4. Reproduce locally with the smallest useful test first, then the full suite:

```bash
php artisan test --filter=IncomeWorkflowTest
php artisan test --filter=CompanyProfileSettingsTest
php artisan test
```

5. Patch the smallest correct source/test alignment:

- If tests still expect RC Technology/Malaysia branding for first-run defaults, update the tests and/or seeded scenario to neutral global defaults.
- If a workflow preview intentionally uses a demo or configured company profile, keep that expectation scoped to that explicit record, not the global fallback.
- Do not globally force document amounts to USD; preserve per-document currency formatting where the document currency is intentional.
- Avoid broad rewrites of large workflow tests. Patch precise assertions or setup data using local diff tools.

6. Run all CI-equivalent checks used by the repo. At minimum:

```bash
php artisan test
npm run build
```

7. Push the fix to PR #32 and merge only when CI is green.

## Known mistakes to avoid

- Do not keep guessing the failing assertion. Inspect the current log first.
- Do not replace large test files wholesale from generated text.
- Do not erase Malaysia/RM support; only remove Malaysia as the implicit first-run default.
- Do not regress shared-hosting constraints in `AGENTS.md`.
- Do not introduce non-neutral identity into `CompanyProfile::defaults()` unless the product owner explicitly decides that is acceptable.

## Acceptance criteria

- First-run/default company profile values are globally neutral.
- Malaysia preset/configured behavior still formats RM/MYR correctly.
- Existing workflow previews, reports, and PDFs use the configured company profile and document currency correctly.
- Laravel tests pass.
- Asset build passes if required by CI.
- PR #32 is merged only after green CI.
