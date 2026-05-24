# Hosting Constraints: Exabytes Plesk eBiz 17 Mini

## Target hosting

The intended deployment target is Exabytes Malaysia Business Web Hosting, eBiz 17 Mini, inside Plesk.

This is shared hosting, not VPS. Do not require server-admin access or long-running system services.

## Confirmed PHP environment from uploaded phpinfo

The user provided Exabytes/Plesk PHP info showing:

- PHP 8.3.31
- Linux `x86_64` Plesk build on LiteSpeed
- LiteSpeed server API
- Plesk PHP configuration path
- `memory_limit = 512M`
- `max_execution_time = 300`
- `max_input_time = 600`
- `upload_max_filesize = 50M`
- `post_max_size = 50M`
- `open_basedir = /home/rctech.my/:/tmp/`
- `disable_functions = opcache_get_status`
- `proc_open`, `exec`, `shell_exec`, and `system` were not listed as disabled
- `curl` enabled
- `dom` enabled
- `fileinfo` enabled
- `gd` enabled
- `intl` enabled
- `mbstring` enabled
- `mysqli` / `pdo_mysql` enabled
- `openssl` enabled
- `zip` enabled
- `exif` enabled
- `imagick` enabled
- ImageMagick 6.9.13 with PDF/PDFA/PS/EPS formats reported
- OPcache enabled
- Xdebug loaded
- Redis PHP extension loaded, but Redis remains disallowed as a required QuoteFlow dependency

## Compatibility conclusion

The Laravel core is compatible with this environment.

Compatible:

- Laravel 10
- Blade views
- MySQL/MariaDB via PDO
- local storage
- file cache/session
- sync queue
- DomPDF for normal quotations, POs, invoices, and receiving PDFs
- authenticated attachment preview/download routes
- prebuilt CSS uploaded to `public/css/app.css`

Likely compatible but still needs command-level confirmation from the same web/runtime user:

- Database backups using `mysqldump`
- OCR using Tesseract
- Ghostscript command availability
- Tesseract command availability
- Poppler `pdftotext` command availability

Possible only after explicit hosting proof:

- PaddleOCR 3.5.0 through a project-owned Python environment

The phpinfo confirms PHP-side support for Imagick/PDF formats and does not list common process functions as disabled, but phpinfo does not prove that `tesseract`, `pdftotext`, `gs`, `python3`, or PaddleOCR exist on the shell path. Do not make any of those binaries a mandatory production dependency until they are tested on Exabytes under the same account and `open_basedir` rules.

## Production implications from phpinfo

- Keep uploads, generated previews, temp OCR images, extraction cache, and any model/cache files inside `/home/rctech.my/` or `/tmp/`.
- Do not hardcode Laragon, Windows, or developer-machine paths into production configuration.
- Keep OCR extraction user-triggered and bounded; do not run it during normal page rendering.
- The 50M upload/post limits are enough for ordinary business PDFs/images, but the app should still validate file type and size before extraction.
- The 512M memory limit and 300 second execution time allow explicit extraction attempts, not unbounded batch processing.
- Xdebug is loaded in the provided phpinfo. If performance is poor in production, disable Xdebug from the Plesk PHP extension set before adding infrastructure.
- The Redis extension being loaded does not change the architecture rule: QuoteFlow must keep file cache/session and sync-safe workflows as the guaranteed deployment path.

## Required OCR verification commands

Run on Exabytes SSH or through a temporary protected diagnostic route:

```bash
pwd
whoami
which tesseract
tesseract --version
which pdftotext
pdftotext -v
which mysqldump
mysqldump --version
which gs
gs --version
which python3
python3 --version
python3 -m pip show paddleocr
```

From PHP web runtime, test:

```php
function_exists('proc_open');
function_exists('shell_exec');
extension_loaded('imagick');
Imagick::queryFormats('PDF');
shell_exec('which tesseract 2>&1');
shell_exec('tesseract --version 2>&1');
shell_exec('which pdftotext 2>&1');
shell_exec('pdftotext -v 2>&1');
shell_exec('which mysqldump 2>&1');
shell_exec('mysqldump --version 2>&1');
shell_exec('which gs 2>&1');
shell_exec('gs --version 2>&1');
shell_exec('which python3 2>&1');
shell_exec('python3 --version 2>&1');
shell_exec('python3 -m pip show paddleocr 2>&1');
```

Remove any diagnostic route after testing.

## PaddleOCR boundary

The current latest PaddleOCR version selected for QuoteFlow advanced document capture is 3.5.0.

PaddleOCR can be supported only as an embedded advanced analyzer behind QuoteFlow's own document capture service. It must not become the business workflow boundary and must not replace manual verification.

Production requirements before enabling PaddleOCR:

- `PADDLEOCR_ENABLED=false` remains the default for Exabytes/Plesk deployments.
- Exabytes must provide a usable Python 3 runtime compatible with PaddleOCR and PaddlePaddle.
- Any virtual environment, package cache, model cache, and generated files must live under `/home/rctech.my/` or `/tmp/`.
- Extraction must have page, file-size, timeout, and memory limits.
- Failure must return the user to manual verification instead of blocking approval or matching work.
- No external OCR API may be required for the core workflow.

If PaddleOCR cannot run on Exabytes within these limits, QuoteFlow must still work through PDF text extraction, local Tesseract where available, and first-class manual verification.

## Do not break these constraints

Do not introduce hard requirements for:

- Redis
- Laravel Horizon
- resident queue workers
- WebSockets
- Docker
- Meilisearch / Elasticsearch
- headless Chrome PDF generation
- Node/Vite build on production server
- external services as mandatory core dependencies
- root package installation
- systemd / Supervisor

## Safe architectural pattern

Use normal Laravel service classes inside the request lifecycle:

```text
Controller -> Service class -> Eloquent models -> Audit record -> Blade redirect/view
```

Safe examples:

- `SupplierInvoiceVerificationService`
- `SupplierInvoiceMatchingService`
- `PaymentEligibilityService`
- `DocumentWorkflowService`
- `DocumentNumberingService`

Unsafe for this target if mandatory:

- queue-only OCR
- daemon workers
- microservice-only matching
- VPS-only binary installation

## OCR product rule

Keep OCR as a useful feature, but do not make OCR success the only possible path to approval. OCR can fail due to poor scans even when hosting supports it.

Approval should depend on verified business data, not strictly successful OCR. Supplier invoices require verified invoice data. Purchase requests that need supplier pricing require verified supplier quote evidence, or an explicit quote exception reason approved with an approver comment.

The product guarantee is not "OCR reads every layout perfectly." The guarantee is that uploaded supplier/customer documents remain reviewable, QuoteFlow attempts bounded assisted extraction when local capabilities exist, and the user can verify or correct the structured draft before downstream workflow trusts it.
