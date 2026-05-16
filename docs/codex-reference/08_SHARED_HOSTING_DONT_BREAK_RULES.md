# Shared Hosting Do Not Break Rules

QuoteFlow must remain compatible with Exabytes/Plesk shared hosting.

## Do not add mandatory infrastructure

Do not make the application require:

- Redis
- Laravel Horizon
- Supervisor
- systemd
- long-running queue workers
- WebSockets
- Laravel Echo server
- Docker
- Kubernetes
- VPS/root access
- Meilisearch
- Elasticsearch
- external OCR API as mandatory core dependency
- headless Chrome or Puppeteer for normal PDF generation
- Node/Vite build step on production hosting

## Allowed patterns

These are safe:

- Laravel controllers
- Laravel service classes
- Eloquent models
- Blade views
- DomPDF
- local/private uploads
- authenticated preview/download routes
- file cache
- file sessions
- sync queue
- normal Plesk cron if optional and tested
- prebuilt CSS uploaded to `public/css/app.css`

## Request lifecycle rule

Business operations should complete inside normal HTTP requests unless explicitly optional.

Safe:

```text
POST form -> Controller -> Service -> Eloquent transaction -> Audit -> Redirect
```

Avoid mandatory background processing for:

- approval
- payment recording
- matching
- document status changes
- PDF generation
- core reporting

## OCR rule

OCR can remain synchronous and local if Tesseract is available. However, OCR must not be the only path to supplier invoice approval.

Reason:

Even if server OCR works, OCR can fail due to scan quality, language, orientation, image noise, or supplier layout.

Correct product rule:

```text
Supplier invoice approval requires verified invoice details.
Verification may be OCR-assisted, manual, or external.
```

Incorrect product rule:

```text
Supplier invoice approval requires successful OCR.
```

## PDF rule

Keep DomPDF templates simple:

- avoid huge images
- avoid remote assets
- avoid complex JavaScript-dependent rendering
- avoid browser-only CSS features
- keep generated documents A4 and business-document sized

Do not replace DomPDF with mandatory Chrome/Puppeteer unless the deployment target changes.

## Storage rule

Use local storage with authenticated routes for attachments.

Do not require S3 or public symlinks unless implemented as optional.

## Deployment rule

Production server should not need:

```bash
npm install
npm run build
```

Build CSS locally and commit/upload `public/css/app.css` as the project already expects.

## Database rule

Use MySQL/MariaDB-compatible migrations.

Avoid database features that may not exist on shared hosting, such as:

- PostgreSQL-only features
- triggers requiring elevated privileges
- stored procedures requiring special permissions
- fulltext/search engines that need server-level setup

## Performance rule

The hosting has reasonable PHP limits from phpinfo, but it is still shared hosting.

Avoid:

- N+1 heavy pages
- huge unpaginated lists
- scanning all attachments synchronously
- large recursive graph traversal
- loading all documents into memory for reports

Use pagination and chunking where appropriate.

## Security rule

Any temporary diagnostic route for PHP/OCR checks must be:

- admin-only
- removed after use, or
- protected by environment flag

Never expose phpinfo publicly in production.
