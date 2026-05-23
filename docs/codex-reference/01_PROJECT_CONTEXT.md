# QuoteFlow Project Context

## Product summary

QuoteFlow is a Laravel 10, Blade-based business workflow app for managing quotation, order, purchasing, receiving, invoicing, payment, attachment, OCR, reporting, and audit workflows.

The app is intentionally shared-hosting friendly. It should remain deployable on Exabytes/Plesk shared hosting without Redis, Horizon, WebSockets, resident queue workers, Docker, or a production Node build.

## Core architecture

The central model is `App\Models\Document`.

QuoteFlow stores several workflow modules as typed documents:

- Customer Quotations: `customer_quotation`
- PO Received: `customer_po`
- Customer Invoices: `customer_invoice`
- Purchase Requests: `purchase_request`
- Supplier Quotations: `supplier_quotation`
- Purchase Orders: `supplier_po`
- Receiving Records: `goods_receipt`
- Supplier Invoices: `supplier_invoice`

The main statuses are:

- `draft`
- `pending_approval`
- `approved`
- `rejected`
- `issued`
- `fulfilled`
- `received`
- `matched`
- `part_paid`
- `paid`
- `closed`
- `cancelled`

## Main routes

The main workflow routes are in `routes/web.php` and map to `App\Http\Controllers\DocumentController`.

Important actions:

- document index/create/store/show/edit/update
- submit for approval
- approve/reject
- transition actions: `issue`, `fulfill`, `receive`, `match`, `close`, `cancel`
- purchase request supplier quotation evidence verification or quote exception
- supplier invoice verification and matching checks
- attachment upload/preview/download
- OCR extraction and extraction verification
- PDF preview/download
- CSV export

Payment routes are handled by `App\Http\Controllers\PaymentController`.
PDF preview/download routes are handled by `App\Http\Controllers\DocumentPdfController`.

## Important UI files

Primary document pages:

- `resources/views/documents/index.blade.php`
- `resources/views/documents/form.blade.php`
- `resources/views/documents/show.blade.php`

Important document partials:

- `resources/views/documents/partials/workflow-timeline.blade.php`
- `resources/views/documents/partials/supplier-invoice-file-preview.blade.php`
- `resources/views/documents/partials/generated-pdf-output-preview.blade.php`
- `resources/views/documents/partials/external-document-preview.blade.php`
- `resources/views/documents/partials/external-document-capture-lines.blade.php`
- `resources/views/documents/partials/receipt-live-preview.blade.php`
- `resources/views/documents/partials/quotation-live-preview.blade.php`
- `resources/views/documents/partials/business-document-header.blade.php`
- `resources/views/documents/partials/cancel-document-form.blade.php`
- `resources/views/company_profiles/partials/logo-mark.blade.php`

Layout/navigation:

- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/partials/navigation.blade.php`

Payment pages:

- `resources/views/payments/index.blade.php`
- `resources/views/payments/form.blade.php`

## Intended high-level flows

### Customer / outgoing flow

```text
Customer inquiry -> customer quotation -> approval -> issue quotation -> PO received -> accept PO -> delivery/service completion -> customer invoice -> issue invoice -> payment -> close
```

### Supplier / incoming flow

```text
Purchase request with supplier quotation evidence or quote exception -> approval -> purchase order -> issue PO -> goods/service receipt -> supplier invoice verification -> matching -> payment -> close
```

## Development guidance

Before changing workflow behavior, inspect the controller rule and the page rule together. Do not update only the Blade page or only the controller. The page should not show actions that the backend rejects, and the backend should not support important actions that are invisible in the UI unless intentionally admin-only.
