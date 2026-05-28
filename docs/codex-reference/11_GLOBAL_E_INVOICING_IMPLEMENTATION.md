# Global E-Invoicing Implementation Reference

This file is the implementation authority for adding e-invoicing to Suria QuoteFlow as a global SaaS product.

Read this file before any e-invoicing code change. Do not implement a country-specific e-invoicing module as the core system. Implement a neutral global compliance engine with country and network adapters.

## Product decision

Suria QuoteFlow must support global e-invoicing through a pluggable compliance architecture.

Correct architecture:

```text
QuoteFlow invoice
-> canonical e-invoice model
-> global compliance engine
-> country/network adapter
-> tax authority, Peppol access point, or manual portal evidence
```

Incorrect architecture:

```text
QuoteFlow invoice
-> Malaysia MyInvois only
```

Malaysia MyInvois may be the first adapter if it is the first customer market, but it must not define the whole product architecture.

## Current system context

QuoteFlow currently stores invoices as typed `documents`:

- `customer_invoice`
- `supplier_invoice`

The existing invoice workflow already has document creation, approval, PDF output, attachment handling, supplier invoice verification, supplier invoice matching, payment recording, audit trails, and reports.

Do not replace this workflow. Add e-invoicing as a compliance layer linked to the existing invoice documents.

## Official reference sources

Always verify current country rules before coding an adapter. E-invoicing rules change frequently.

Start with these official sources:

- OpenPeppol: https://peppol.org/about/
- OpenPeppol documentation: https://peppol.org/documentation/
- Malaysia MyInvois SDK: https://sdk.myinvois.hasil.gov.my/
- Malaysia MyInvois APIs: https://sdk.myinvois.hasil.gov.my/einvoicingapi/
- Saudi Arabia ZATCA e-invoicing: https://zatca.gov.sa/en/E-Invoicing/Pages/default.aspx
- EU e-invoicing directive reference: https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX:32014L0055
- EU VAT in the Digital Age legal reference: https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=CELEX:32025L0516

These sources are not exhaustive. Each adapter must document the official references used at the time of implementation.

## Non-negotiable architecture rules

1. Keep QuoteFlow as a Laravel Blade monolith.
2. Keep normal document workflows working without e-invoicing enabled.
3. Do not introduce Redis, Horizon, long-running queue workers, WebSockets, Docker, Kubernetes, Elasticsearch, or mandatory external services for the core workflow.
4. Do not make any country adapter mandatory for all tenants.
5. Do not hardcode Malaysia, Peppol, ZATCA, India, Italy, France, or any other jurisdiction into the core invoice workflow.
6. Do not store API secrets, certificate passphrases, private keys, or tokens in plain text.
7. Do not treat PDF output as the e-invoice source of truth. Structured payload is the source of truth.
8. Do not block existing invoice creation, approval, payment, reports, or supplier invoice matching unless an enabled e-invoice rule explicitly requires it.
9. Do not hide a backend restriction only in Blade. Backend services/controllers must enforce the same rule.
10. Do not implement a broad rewrite of `DocumentController`. Add small services and focused controllers.

## Terminology

Use these terms in code and user-facing copy:

- `E-Invoice compliance`
- `Tax profile`
- `E-Invoice readiness`
- `Submission status`
- `Validation errors`
- `Validation link`
- `Manual portal submission`
- `Country adapter`
- `Network adapter`

Avoid these user-facing terms unless in technical/admin screens:

- `adapter payload`
- `UBL JSON object`
- `hash digest`
- `queue job`
- `API raw response`
- `workflow transition`
- `model state`

## Global design model

The e-invoicing feature has four layers.

### 1. QuoteFlow document layer

Existing system records:

- `documents`
- `document_items`
- `customers`
- `suppliers`
- `company_profiles`
- `payments`
- `attachments`
- `audit_trails`

This layer remains the operational workflow.

### 2. Canonical compliance layer

This layer converts QuoteFlow data into a neutral canonical invoice model.

It must include:

- seller tax identity
- buyer tax identity
- invoice header
- document type
- currency
- exchange rate when needed
- payment terms
- line items
- tax breakdown
- allowances and charges when implemented
- references to purchase orders, delivery records, previous invoices, credit notes, or debit notes
- delivery/service location when required

The canonical model must be country-neutral.

### 3. Adapter layer

Adapters convert the canonical model into a jurisdiction or network format.

Examples:

- `MalaysiaMyInvoisAdapter`
- `PeppolBisBillingAdapter`
- `SaudiZatcaAdapter`
- `IndiaGstIrpAdapter`
- `ItalySdiAdapter`
- `FrancePdpAdapter`
- `ManualPortalAdapter`

Each adapter is responsible for:

- required field rules
- code list mapping
- payload format
- signature rules
- authentication
- submission
- status polling
- cancellation/rejection if supported
- validation link or QR handling if supported
- adapter-specific error parsing

### 4. Evidence and audit layer

This layer stores the result of submission or manual compliance evidence.

It must answer:

- Was e-invoice required?
- Which jurisdiction/network applied?
- Was the invoice submitted?
- Was it accepted or validated?
- What external ID was returned?
- What validation link or QR should appear on the PDF?
- What errors must the user fix?
- Who submitted, synced, cancelled, or recorded manual submission?

## Proposed database design

Use neutral tables. Do not add only `myinvois_*` columns to core tables.

### `tax_profiles`

Company-side tax identity by country or jurisdiction.

Suggested fields:

```text
id
company_profile_id
country_code
jurisdiction_code
tax_scheme
tax_identifier
registration_type
registration_number
vat_number
gst_number
sst_number
msic_code
business_activity_description
address_line_1
address_line_2
address_line_3
postcode
city
state_code
country_code_for_address
contact_number
email
metadata_json
is_default
created_at
updated_at
```

Notes:

- Keep `country_code` as ISO-style country code where possible.
- Use `metadata_json` only for adapter-specific optional fields, not for core required fields.
- Preserve existing `company_profiles` fields for general company display and PDF output.

### `party_tax_profiles`

Customer or supplier tax identity.

Suggested fields:

```text
id
party_type              // customer or supplier
party_id
country_code
jurisdiction_code
tax_identifier
registration_type
registration_number
vat_number
gst_number
sst_number
address_line_1
address_line_2
address_line_3
postcode
city
state_code
country_code_for_address
contact_number
email
validation_status       // not_checked, valid, invalid, not_supported
validated_at
validation_message
metadata_json
created_at
updated_at
```

Notes:

- Do not overload `customers.tax_number` or `suppliers.tax_number` for all countries.
- Keep the old fields for backward compatibility and display.

### `product_tax_profiles`

Product/service classification and tax mapping by jurisdiction.

Suggested fields:

```text
id
product_id
country_code
jurisdiction_code
classification_code
tax_type_code
unit_code
tax_exemption_code
tax_exemption_reason
metadata_json
is_default
created_at
updated_at
```

Notes:

- A single product may need different classifications in different countries.
- Do not hardcode one global classification into `products` unless it is only a fallback.

### `e_invoice_records`

One compliance record per QuoteFlow invoice or adjustment document.

Suggested fields:

```text
id
document_id
jurisdiction_code       // MY, SG, SA, EU, IN, IT, FR, etc.
network_key             // myinvois, peppol, zatca, gst_irp, sdi, pdp, manual
document_kind           // invoice, credit_note, debit_note, refund_note, self_billed_invoice, self_billed_credit_note, self_billed_debit_note, self_billed_refund_note
requirement_status      // not_checked, not_required, required
status                  // draft, ready, submitted, valid, invalid, cancelled, rejected, failed, manual_recorded
adapter_status
external_submission_id
external_document_id
external_uuid
external_long_id
validation_url
qr_payload
payload_format          // canonical_json, ubl_json, ubl_xml, peppol_bis, zatca_xml, etc.
payload_hash
canonical_payload_path
submitted_payload_path
signed_payload_path
response_payload_path
validation_errors_json
submitted_at
validated_at
cancelled_at
rejected_at
last_synced_at
created_by
submitted_by
cancelled_by
rejected_by
created_at
updated_at
```

Notes:

- Never store large payloads directly in normal page query rows if it will slow page loading. Store payloads as private files and keep paths in the table.
- Store enough status fields for lists and dashboards without loading payload files.

### `e_invoice_submissions`

Tracks API/manual submission batches.

Suggested fields:

```text
id
jurisdiction_code
network_key
environment             // sandbox, production, manual
submission_reference
status
submitted_document_count
accepted_document_count
rejected_document_count
request_payload_path
response_payload_path
submitted_by
submitted_at
last_synced_at
created_at
updated_at
```

### `e_invoice_events`

Audit-friendly event history for compliance lifecycle.

Suggested fields:

```text
id
e_invoice_record_id
document_id
event_type              // readiness_checked, payload_prepared, submitted, sync_completed, valid, invalid, cancelled, rejected, manual_recorded
status_before
status_after
message
metadata_json
created_by
created_at
```

This does not replace `audit_trails`. Use both when appropriate: `e_invoice_events` for compliance timeline and `audit_trails` for system audit.

### `e_invoice_adapter_settings`

Tenant/company settings per adapter.

Suggested fields:

```text
id
company_profile_id
jurisdiction_code
network_key
environment             // sandbox, production
credentials_encrypted
certificate_path
certificate_passphrase_encrypted
settings_json
is_active
created_at
updated_at
```

Notes:

- Encrypt credentials and passphrases.
- Prefer `.env` for deployment-level secrets when possible.
- Do not show secret values after save.

## Proposed services

Create a neutral service namespace:

```text
app/Services/EInvoicing/Core/
```

Suggested classes:

```text
EInvoiceRequirementService.php
EInvoiceReadinessService.php
CanonicalInvoiceBuilder.php
EInvoiceRecordService.php
EInvoicePayloadStorageService.php
EInvoiceStatusService.php
EInvoiceEventService.php
```

Create adapter namespace:

```text
app/Services/EInvoicing/Adapters/
```

Suggested interface:

```php
interface EInvoiceAdapter
{
    public function key(): string;

    public function label(): string;

    public function supports(string $jurisdictionCode, string $documentKind): bool;

    public function readinessRules(): array;

    public function buildPayload(CanonicalInvoiceData $invoice): EInvoicePayload;

    public function submit(EInvoiceRecord $record): EInvoiceSubmissionResult;

    public function sync(EInvoiceRecord $record): EInvoiceStatusResult;

    public function cancel(EInvoiceRecord $record, string $reason): EInvoiceStatusResult;
}
```

If PHP DTO classes are too much for the current codebase style, use well-documented arrays initially, but keep method boundaries strict.

## Suggested config

Create:

```text
config/einvoicing.php
```

Example shape:

```php
return [
    'enabled' => env('EINVOICING_ENABLED', false),

    'default_environment' => env('EINVOICING_ENVIRONMENT', 'sandbox'),

    'jurisdictions' => [
        'MY' => [
            'label' => 'Malaysia MyInvois',
            'adapter' => App\Services\EInvoicing\Adapters\MalaysiaMyInvois\MalaysiaMyInvoisAdapter::class,
            'network_key' => 'myinvois',
            'supports_api_submission' => true,
            'supports_cancel' => true,
            'supports_reject' => true,
            'supports_validation_url' => true,
        ],
        'PEPPOL' => [
            'label' => 'Peppol network',
            'adapter' => App\Services\EInvoicing\Adapters\Peppol\PeppolBisBillingAdapter::class,
            'network_key' => 'peppol',
            'requires_service_provider' => true,
            'supports_api_submission' => false,
        ],
        'SA' => [
            'label' => 'Saudi ZATCA',
            'adapter' => App\Services\EInvoicing\Adapters\SaudiZatca\SaudiZatcaAdapter::class,
            'network_key' => 'zatca',
            'supports_clearance' => true,
            'supports_qr' => true,
        ],
        'MANUAL' => [
            'label' => 'Manual portal submission',
            'adapter' => App\Services\EInvoicing\Adapters\ManualPortal\ManualPortalAdapter::class,
            'network_key' => 'manual',
        ],
    ],
];
```

## Global readiness rules

`EInvoiceReadinessService` must check neutral requirements first:

- company tax profile exists for selected jurisdiction
- customer or buyer tax profile exists when required
- invoice has issue date
- invoice has document number
- invoice has currency
- invoice totals are internally consistent
- invoice has at least one line item
- every line has description, quantity, unit price, line total, tax rate/tax amount
- every line can be mapped to an adapter classification/code when required
- document is not cancelled
- duplicate e-invoice submission is prevented unless adapter allows resubmission/correction

Then call adapter-specific readiness rules.

Readiness result shape:

```php
[
    'ready' => false,
    'requirement_status' => 'required',
    'jurisdiction_code' => 'MY',
    'network_key' => 'myinvois',
    'blocking_issues' => [
        ['key' => 'buyer_tin', 'message' => 'Add the buyer tax identifier before submitting this e-Invoice.'],
    ],
    'warnings' => [],
]
```

## Workflow rules

### Customer invoices

Default rule:

```text
Customer invoice can be created and approved normally.
```

When e-invoicing is enabled and required for the company/customer/jurisdiction:

```text
Customer invoice cannot be issued as final customer document until e-invoice compliance is valid, not required, or manual portal submission has been recorded by an authorized user.
```

Implementation point:

- Add a small service call inside `DocumentController::transition()` before `customer_invoice` `issue` transition.
- Do not hardcode country checks in the controller.
- The controller should call a neutral service such as `EInvoiceRequirementService` or `EInvoiceStatusService`.

### Supplier invoices

Supplier invoices in QuoteFlow are usually received documents.

Default rule:

```text
Supplier invoice verification and matching remains a procurement/accounts control.
```

Do not convert existing supplier invoice matching into e-invoicing.

For supplier invoices, e-invoicing should initially support storing received e-invoice evidence:

- supplier e-invoice UUID/reference
- validation link
- supplier e-invoice file or payload attachment
- received network/source
- validation status if the adapter supports lookup

Self-billed supplier invoices are a separate future document kind and must not be mixed with normal received supplier invoices.

### Credit notes, debit notes, refund notes

Do not overload normal invoices without a clear document kind.

Recommended approach:

- Keep `documents.type = customer_invoice` for now if minimal schema change is required.
- Add `e_invoice_records.document_kind` for compliance document kind.
- If full accounting support is later required, add explicit document types and UI pages for credit/debit/refund notes.

## UI requirements

### Navigation

Add a compliance area only when e-invoicing is enabled or user has admin/manager/accounts role:

```text
Compliance
- E-Invoice settings
- Tax profiles
- E-Invoice documents
- Submission log
```

Do not place country-specific menu items in the main navigation.

Wrong:

```text
Malaysia MyInvois
```

Correct:

```text
E-Invoice settings
```

### Invoice show page

Add a partial:

```text
resources/views/documents/partials/e-invoice-compliance-panel.blade.php
```

Show it on customer invoice pages and, later, supplier invoice pages when received e-invoice evidence is supported.

Panel states:

```text
Not checked
Not required
Missing details
Ready to submit
Submitted
Validated
Invalid
Cancelled
Rejected
Manual submission recorded
```

Primary actions:

```text
Check compliance
Prepare e-Invoice
Submit e-Invoice
Check status
View errors
Download payload
Record manual portal submission
Cancel e-Invoice
```

User-facing copy must be business-readable. Avoid API jargon except in admin diagnostics.

### PDF output

Update `resources/views/documents/pdf.blade.php` only after a valid compliance record exists.

Show:

- e-invoice status
- external UUID/reference
- validation link
- QR code if the adapter supports it

Do not show fake UUIDs or placeholder QR codes.

## API and integration rules

Adapters that call external APIs must:

- have sandbox and production environments
- cache access tokens where applicable
- redact secrets from logs and UI
- store request/response payloads in private storage, not public web paths
- handle API failure without corrupting the invoice document
- use explicit retry rules
- avoid duplicate submission
- store raw validation errors for support but show user-friendly errors to end users
- audit submit, sync, cancel, reject, manual record, and payload generation events

Since QuoteFlow targets shared hosting, all API calls must work through normal request/response actions first.

Optional later enhancement:

```text
php artisan quoteflow:sync-e-invoices
```

This command may be used with hosting cron, but e-invoicing must not require a resident queue worker.

## Manual portal fallback

Every country adapter should decide whether manual portal submission is allowed.

Manual submission record should capture:

- external reference/UUID if available
- submission date/time
- user who recorded it
- uploaded evidence file if available
- notes
- jurisdiction/network

Manual fallback status:

```text
manual_recorded
```

Manual submission must not silently bypass compliance. It must be visible and audited.

## Suggested implementation phases

### Phase 1: Neutral foundation

Files likely affected:

```text
config/einvoicing.php
database/migrations/*_create_tax_profiles_table.php
database/migrations/*_create_party_tax_profiles_table.php
database/migrations/*_create_product_tax_profiles_table.php
database/migrations/*_create_e_invoice_records_table.php
database/migrations/*_create_e_invoice_submissions_table.php
database/migrations/*_create_e_invoice_events_table.php
app/Models/TaxProfile.php
app/Models/PartyTaxProfile.php
app/Models/ProductTaxProfile.php
app/Models/EInvoiceRecord.php
app/Models/EInvoiceSubmission.php
app/Models/EInvoiceEvent.php
app/Services/EInvoicing/Core/*
resources/views/documents/partials/e-invoice-compliance-panel.blade.php
```

Acceptance criteria:

- Existing invoice workflow still passes tests.
- E-invoicing disabled by default.
- Readiness panel can show `Not checked`, `Not required`, or `Missing details`.
- No external API calls yet.

### Phase 2: Canonical invoice model

Files likely affected:

```text
app/Services/EInvoicing/Core/CanonicalInvoiceBuilder.php
app/Services/EInvoicing/Core/EInvoiceReadinessService.php
tests/Unit/EInvoicing/*
```

Acceptance criteria:

- A customer invoice can be converted into a stable canonical structure.
- Missing seller, buyer, line, and tax data are reported as blocking issues.
- Canonical output is tested with fixtures.

### Phase 3: Manual portal adapter

Build this before a country API adapter.

Files likely affected:

```text
app/Services/EInvoicing/Adapters/ManualPortal/*
app/Http/Controllers/EInvoiceController.php
resources/views/documents/partials/e-invoice-compliance-panel.blade.php
```

Acceptance criteria:

- Authorized user can record manual e-invoice submission evidence.
- Evidence is stored privately.
- Invoice issue blocking respects manual compliance only when allowed by config/rules.
- Audit trail records the action.

### Phase 4: First country adapter

Build the first market adapter inside the adapter architecture.

If Malaysia is first:

```text
app/Services/EInvoicing/Adapters/MalaysiaMyInvois/*
```

If Peppol is first:

```text
app/Services/EInvoicing/Adapters/Peppol/*
```

Acceptance criteria:

- Adapter-specific settings page exists.
- Readiness rules are adapter-specific but called through the neutral service.
- Payload generation does not pollute the core document model.
- Submission/status/cancel logic is isolated to adapter classes.

### Phase 5: PDF and reporting integration

Files likely affected:

```text
resources/views/documents/pdf.blade.php
resources/views/reports/index.blade.php
app/Http/Controllers/ReportController.php
```

Acceptance criteria:

- Valid e-invoice reference appears on PDF only after validation/manual evidence.
- Reports can filter e-invoice status without loading payload files.
- Existing finance reports still work.

## Testing requirements

Add tests before marking any implementation complete.

Minimum tests:

```text
E-invoicing disabled does not block customer invoice workflow.
Enabled jurisdiction with missing company tax profile shows blocking issue.
Missing buyer tax profile shows blocking issue when buyer details are required.
Missing product classification shows blocking issue when adapter requires classification.
Valid invoice creates e_invoice_record.
Manual portal submission requires authorized role.
Manual portal submission stores evidence and audit event.
Customer invoice issue is blocked when required e-invoice is not valid/manual-recorded.
Customer invoice issue is allowed when e-invoice is not required.
Customer invoice issue is allowed when e-invoice is valid.
Supplier invoice matching still works independently of e-invoicing.
Payload files are not loaded on normal document index pages.
Secrets are not shown after settings save.
```

Suggested test locations:

```text
tests/Feature/EInvoicingWorkflowTest.php
tests/Feature/EInvoicingSettingsTest.php
tests/Unit/EInvoicing/CanonicalInvoiceBuilderTest.php
tests/Unit/EInvoicing/EInvoiceReadinessServiceTest.php
```

## Performance requirements

Follow the existing performance rules.

Do not:

- load full payload JSON/XML on list pages
- run external API calls in index/dashboard renders
- make dashboard counts load full e-invoice records into memory
- render huge validation responses inline by default

Do:

- paginate e-invoice document lists
- store large payloads in private storage
- use SQL aggregates for dashboard/report counts
- show validation errors in expandable panels
- use manual `Check status` action before adding optional cron sync

## Security requirements

- Encrypt adapter credentials.
- Protect certificate files and private keys in non-public storage.
- Restrict settings to admin users unless a later role model says otherwise.
- Restrict submit/cancel/reject/manual-record actions to admin, manager, or accounts roles as appropriate.
- Redact secrets in logs, audit payloads, validation messages, and UI.
- Never expose private stored payload paths directly.

## Backward compatibility checklist

Before merging e-invoicing changes, verify:

- customer quotations still create and approve
- customer PO flow still works
- customer invoice PDF still works without e-invoicing
- supplier PO/goods receipt/supplier invoice flow still works
- supplier invoice verification and matching still work
- payment recording still works
- reports still work
- CSV export still works
- company profile still supports existing display/PDF settings
- shared-hosting deployment still works without Node/Vite production build or queue worker

## Codex implementation discipline

When implementing this feature, Codex must:

1. Read `AGENTS.md`.
2. Read this file.
3. Inspect current routes, controllers, models, migrations, Blade views, services, and tests affected by the specific phase.
4. Implement one phase at a time.
5. Keep e-invoicing disabled by default until settings are configured.
6. Add tests for every behavior change.
7. Summarize changed files, tests run, performance impact, security impact, and remaining risks.

Do not mark the module complete after only adding fields or a UI panel. E-invoicing is complete only when readiness, payload, evidence/status, workflow enforcement, audit, tests, and adapter behavior are all correct for the selected phase.
