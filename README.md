# QuoteFlow

QuoteFlow is a Laravel 10 MVP for quotations, purchase orders, invoices, receipts, payments, approvals, PDF output, attachments, audit trail, dashboard, reports, and CSV export.

The build is intentionally server-rendered and shared-hosting friendly:

- Laravel Blade pages, Eloquent ORM, MariaDB-compatible migrations.
- Tailwind CSS compiled to `public/css/app.css`; no Vite or Node runtime is needed in production.
- DomPDF for document PDFs.
- Local/private file storage for uploads, downloaded through Laravel routes.
- File cache, file sessions, sync queue driver; no Redis, queues, WebSockets, or background workers.

## Local Laragon Setup

This project was built against Laragon PHP:

```powershell
$env:Path='D:\laragon\bin\php\php-8.1.10-Win32-vs16-x64;D:\laragon\bin\composer;' + $env:Path
composer install
npm install
npm run build
php artisan migrate:fresh
php artisan serve --host=127.0.0.1 --port=8000
```

Open `http://127.0.0.1:8000/setup` and create the first administrator. After that, use `/login`.

## Exabytes / Plesk Deployment

Recommended deployment shape:

1. Upload the Laravel app outside the public web root where Plesk allows it.
2. Set the domain document root to the Laravel `public` directory.
3. Create one MariaDB database and update `.env`.
4. Run:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Use these production `.env` defaults as a baseline:

```env
APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Kuala_Lumpur
LOG_CHANNEL=single
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
```

Do not run `npm install` or `npm run build` on Exabytes. Build CSS locally, then upload the generated `public/css/app.css`.

Writable directories:

- `storage`
- `bootstrap/cache`

Uploads are stored under Laravel's local storage disk and served through authenticated download routes, so a public `storage:link` symlink is not required for the MVP.

## Roles

- `admin`: full setup, users, approvals, all documents.
- `manager`: approvals and operational oversight.
- `sales`: outgoing customer quotations, customer POs, customer invoices, customer master data.
- `procurement`: incoming purchase requests, supplier quotations, supplier POs, receipts, supplier master data.
- `accounts`: invoice payment recording and finance workflows.
- `viewer`: read access to dashboard, documents, and reports.

## MVP Workflow Coverage

Outgoing:

`Customer inquiry -> quotation -> approval -> customer PO -> delivery/service completion -> invoice -> payment -> close`

Incoming:

`Purchase request -> supplier quotation -> approval -> supplier PO -> goods/service receipt -> supplier invoice -> invoice matching -> payment -> close`

The MVP stores these as typed `documents` with related `document_items`, `approvals`, `payments`, `attachments`, and `audit_trails`.

## Local Verification Notes

Validated locally with:

- Laravel route loading: `php artisan route:list --except-vendor`
- PHP syntax check across `app`, `database`, and `routes`
- Blade compilation: `php artisan view:cache`
- Laragon MySQL migration execution: `php artisan migrate:fresh`
- Tailwind CSS build: `npm run build`
- Playwright smoke test through Chrome: setup admin, create customer, create product/service, create customer quotation, submit for approval, desktop/mobile screenshots.
- Permanent workflow tests: `php artisan test` covers the outgoing customer workflow, incoming supplier workflow, PDF, attachment upload, document CSV, and receivables report CSV.

## End-User Workflow Testing

The end-user UAT checklist is maintained in `docs/END_USER_WORKFLOW_TESTING.md`.

Seed local demo/UAT data with:

```powershell
php artisan db:seed --class=DemoOperationsSeeder
```

Default UAT password:

```text
Password123!
```
