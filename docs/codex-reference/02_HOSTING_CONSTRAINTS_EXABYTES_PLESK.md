# Hosting Constraints: Exabytes Plesk eBiz 17 Mini

## Target hosting

The intended deployment target is Exabytes Malaysia Business Web Hosting, eBiz 17 Mini, inside Plesk.

This is shared hosting, not VPS. Do not require server-admin access or long-running system services.

## Confirmed PHP environment from uploaded phpinfo

The user provided Exabytes/Plesk PHP info showing:

- PHP 8.3.31
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
- ImageMagick reports PDF/PDFA/PS/EPS support

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

Likely compatible but still needs command-level confirmation:

- OCR using Tesseract
- Ghostscript command availability
- Tesseract command availability

The phpinfo confirms PHP-side support for process execution and Imagick/PDF formats, but phpinfo does not prove that `tesseract` exists on the shell path.

## Required OCR verification commands

Run on Exabytes SSH or through a temporary protected diagnostic route:

```bash
which tesseract
tesseract --version
which gs
gs --version
```

From PHP web runtime, test:

```php
function_exists('proc_open');
extension_loaded('imagick');
Imagick::queryFormats('PDF');
shell_exec('which tesseract 2>&1');
shell_exec('tesseract --version 2>&1');
shell_exec('which gs 2>&1');
shell_exec('gs --version 2>&1');
```

Remove any diagnostic route after testing.

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

Approval should depend on verified invoice data, not strictly successful OCR.
