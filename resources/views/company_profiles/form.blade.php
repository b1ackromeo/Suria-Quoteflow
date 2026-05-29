@extends('layouts.app', ['title' => 'Edit company profile'])

@section('content')
<form method="post" action="{{ $company->exists ? route('company-profiles.update', $company) : route('company-profiles.store') }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @if($company->exists)
        @method('put')
    @endif

    <section class="panel space-y-5">
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-[#0a4f93]">Company profile setup</p>
            <h1 class="mt-2 text-xl font-bold tracking-tight text-slate-950">Edit company profile</h1>
            <p class="panel-subtitle">This company name, logo, contact details, currency, tax, and document settings appear in the app context, document previews, and generated PDF documents.</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1fr_18rem]">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="form-label md:col-span-2">Company name
                    <input class="form-input" name="name" value="{{ old('name', $company->name) }}" required placeholder="Enter company or subsidiary name">
                </label>
                <label class="form-label">Registration number
                    <input class="form-input" name="registration_number" value="{{ old('registration_number', $company->registration_number) }}" placeholder="Company registration number">
                </label>
                <label class="form-label">Email
                    <input class="form-input" type="email" name="email" value="{{ old('email', $company->email) }}" placeholder="company@example.com">
                </label>
                <label class="form-label">Phone
                    <input class="form-input" name="phone" value="{{ old('phone', $company->phone) }}" placeholder="+60 ...">
                </label>
                <label class="form-label">Tagline
                    <input class="form-input" name="tagline" value="{{ old('tagline', $company->tagline) }}" placeholder="Company tagline shown on documents">
                </label>
                <label class="form-label md:col-span-2">Address
                    <textarea class="form-input min-h-24" name="address" placeholder="Registered or business address">{{ old('address', $company->address) }}</textarea>
                </label>
                <label class="form-label">Primary document color
                    <input class="form-input h-12" type="color" name="primary_color" value="{{ old('primary_color', $company->primary_color ?? '#0a345f') }}">
                </label>
                <label class="form-label">Action color
                    <input class="form-input h-12" type="color" name="accent_color" value="{{ old('accent_color', $company->accent_color ?? '#0a4f93') }}">
                </label>
            </div>

            <aside class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Logo</p>
                <div class="mt-3" data-company-logo-preview aria-live="polite">
                    @include('company_profiles.partials.logo-mark', [
                        'company' => $company,
                        'imageClass' => 'aspect-square w-28 rounded-2xl bg-white object-contain shadow-sm ring-1 ring-blue-100',
                        'placeholderClass' => 'company-logo-placeholder aspect-square w-28 rounded-2xl text-2xl',
                    ])
                </div>
                <label class="form-label mt-4">Upload logo
                    <input class="form-input" type="file" name="logo" accept="image/*" data-company-logo-input>
                </label>
                <p class="sr-only" data-company-logo-preview-status>Current company logo shown.</p>
                <p class="mt-3 text-xs font-semibold leading-5 text-slate-500">Use a square PNG/JPG logo. Existing documents will immediately use this company profile.</p>
            </aside>
        </div>
    </section>

    <section class="panel space-y-5">
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-[#0a4f93]">Global document settings</p>
            <h2 class="panel-title">Country, currency, tax, and formats</h2>
            <p class="panel-subtitle">These defaults help QuoteFlow support different countries and document standards without changing code. Malaysia-facing documents normally use RM while exports can still keep ISO codes where needed.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <label class="form-label">Country
                <select class="form-input" name="country" required data-country-select>
                    @foreach($countries as $country)
                        <option value="{{ $country }}" @selected(old('country', $company->displayCountry()) === $country)>{{ $country }}</option>
                    @endforeach
                </select>
                <span class="mt-1 text-xs font-semibold text-slate-400">Changing country updates the default settings below.</span>
            </label>
            <label class="form-label">Timezone
                <select class="form-input" name="timezone" required data-country-default-field="timezone">
                    @foreach($timezones as $timezone)
                        <option value="{{ $timezone }}" @selected(old('timezone', $company->displayTimezone()) === $timezone)>{{ $timezone }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-label">Base currency
                <select class="form-input" name="base_currency" required data-country-default-field="base_currency">
                    @foreach($currencies as $code => $label)
                        <option value="{{ $code }}" @selected(old('base_currency', $company->baseCurrency()) === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-label">Currency display
                <select class="form-input" name="currency_display" data-country-default-field="currency_display">
                    @foreach($currencyDisplays as $display => $label)
                        <option value="{{ $display }}" @selected(old('currency_display', $company->currencyDisplay()) === $display)>{{ $label }}</option>
                    @endforeach
                </select>
                <span class="mt-1 text-xs font-semibold text-slate-400">Controls money shown in pages and PDFs, for example RM 1,234.56.</span>
            </label>
            <label class="form-label">Currency symbol override
                <input class="form-input" name="currency_symbol_override" value="{{ old('currency_symbol_override', $company->currency_symbol_override) }}" placeholder="RM" data-country-default-field="currency_symbol_override">
                <span class="mt-1 text-xs font-semibold text-slate-400">Optional. Applies to the base currency only.</span>
            </label>
            <label class="form-label">Date format
                <select class="form-input" name="date_format" required data-country-default-field="date_format">
                    @foreach($dateFormats as $format => $label)
                        <option value="{{ $format }}" @selected(old('date_format', $company->dateFormat()) === $format)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-label">Number format
                <select class="form-input" name="number_format" required data-country-default-field="number_format">
                    @foreach($numberFormats as $format => $label)
                        <option value="{{ $format }}" @selected(old('number_format', $company->numberFormat()) === $format)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-label">Default tax rate (%)
                <input class="form-input" type="number" step="0.01" min="0" max="100" name="default_tax_rate" value="{{ old('default_tax_rate', $company->defaultTaxRate()) }}" required placeholder="0" data-country-default-field="default_tax_rate">
            </label>
            <label class="form-label">Tax label
                <input class="form-input" name="tax_label" value="{{ old('tax_label', $company->taxLabel()) }}" required placeholder="Tax, SST, GST, VAT" data-country-default-field="tax_label">
            </label>
            <label class="form-label">Tax registration label
                <input class="form-input" name="tax_registration_label" value="{{ old('tax_registration_label', $company->taxRegistrationLabel()) }}" required placeholder="Tax Registration No." data-country-default-field="tax_registration_label">
                <span class="mt-1 text-xs font-semibold text-slate-400">Shown before the tax registration number on PDFs.</span>
            </label>
            <label class="form-label">Tax registration number
                <input class="form-input" name="tax_registration_number" value="{{ old('tax_registration_number', $company->tax_registration_number) }}" placeholder="SST / GST / VAT registration number">
            </label>
        </div>
    </section>

    <section class="panel space-y-5">
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-[#0a4f93]">Project control settings</p>
            <h2 class="panel-title">Delivery billing alert levels</h2>
            <p class="panel-subtitle">These company alert levels are used by project delivery billing reports unless a project has its own alert levels.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <label class="form-label">Customer unbilled / not yet received alert %
                <input class="form-input" type="number" step="0.01" min="0" max="100" name="delivery_gap_alert_percent" value="{{ old('delivery_gap_alert_percent', $company->deliveryGapAlertPercent()) }}" required placeholder="25">
            </label>
            <label class="form-label">Received not invoiced alert %
                <input class="form-input" type="number" step="0.01" min="0" max="100" name="received_not_invoiced_alert_percent" value="{{ old('received_not_invoiced_alert_percent', $company->receivedNotInvoicedAlertPercent()) }}" required placeholder="10">
            </label>
            <label class="form-label">Customer unbilled / not yet received amount alert
                <input class="form-input" type="number" step="0.01" min="0" name="delivery_gap_alert_amount" value="{{ old('delivery_gap_alert_amount', $company->deliveryGapAlertAmount()) }}" required placeholder="0">
                <span class="mt-1 text-xs font-semibold text-slate-400">Use 0 when percentage-only alerts are enough.</span>
            </label>
            <label class="form-label">Received not invoiced amount alert
                <input class="form-input" type="number" step="0.01" min="0" name="received_not_invoiced_alert_amount" value="{{ old('received_not_invoiced_alert_amount', $company->receivedNotInvoicedAlertAmount()) }}" required placeholder="0">
                <span class="mt-1 text-xs font-semibold text-slate-400">Use 0 when percentage-only alerts are enough.</span>
            </label>
        </div>
    </section>

    <section class="panel space-y-5">
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-[#0a4f93]">PDF document text</p>
            <h2 class="panel-title">Payment instructions and footer</h2>
            <p class="panel-subtitle">Use these fields for bank transfer instructions, remittance notes, legal footer text, or country-specific document notes.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <label class="form-label">Payment instructions
                <textarea class="form-input min-h-32" name="payment_instructions" placeholder="Bank name, account number, payment reference, remittance email">{{ old('payment_instructions', $company->payment_instructions) }}</textarea>
            </label>
            <label class="form-label">PDF footer
                <textarea class="form-input min-h-32" name="pdf_footer" placeholder="Company legal footer, tax note, or document disclaimer">{{ old('pdf_footer', $company->pdf_footer) }}</textarea>
            </label>
        </div>
    </section>

    <div class="flex flex-wrap gap-3">
        <button class="btn btn-primary" type="submit">Save company profile</button>
        <a class="btn btn-secondary" href="{{ route('company-profiles.index') }}">Cancel</a>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const countryDefaults = @json($countryDefaults);
        const countrySelect = document.querySelector('[data-country-select]');

        if (countrySelect) {
            countrySelect.addEventListener('change', () => {
                const defaults = countryDefaults[countrySelect.value] || countryDefaults.Other;

                Object.entries(defaults).forEach(([field, value]) => {
                    const input = document.querySelector('[data-country-default-field="' + field + '"]');

                    if (input && value !== undefined && value !== null) {
                        input.value = value;
                    }
                });
            });
        }

        const logoInput = document.querySelector('[data-company-logo-input]');
        const logoPreview = document.querySelector('[data-company-logo-preview]');
        const logoPreviewStatus = document.querySelector('[data-company-logo-preview-status]');
        const logoPreviewClass = 'aspect-square w-28 rounded-2xl bg-white object-contain shadow-sm ring-1 ring-blue-100';

        if (!logoInput || !logoPreview) {
            return;
        }

        const originalLogoPreview = logoPreview.innerHTML;
        let logoPreviewUrl = null;

        const clearLogoPreviewUrl = () => {
            if (logoPreviewUrl) {
                URL.revokeObjectURL(logoPreviewUrl);
                logoPreviewUrl = null;
            }
        };

        logoInput.addEventListener('change', () => {
            const file = logoInput.files && logoInput.files[0];

            clearLogoPreviewUrl();

            if (!file) {
                logoPreview.innerHTML = originalLogoPreview;

                if (logoPreviewStatus) {
                    logoPreviewStatus.textContent = 'Current company logo shown.';
                }

                return;
            }

            if (!file.type.startsWith('image/')) {
                logoPreview.innerHTML = originalLogoPreview;

                if (logoPreviewStatus) {
                    logoPreviewStatus.textContent = 'Choose a PNG or JPG logo image.';
                }

                return;
            }

            logoPreviewUrl = URL.createObjectURL(file);

            const image = new Image();
            image.className = logoPreviewClass;
            image.src = logoPreviewUrl;
            image.alt = 'Selected company logo preview';

            logoPreview.replaceChildren(image);

            if (logoPreviewStatus) {
                logoPreviewStatus.textContent = 'Selected company logo preview is shown.';
            }
        });

        window.addEventListener('beforeunload', clearLogoPreviewUrl);
    });
</script>
@endsection
