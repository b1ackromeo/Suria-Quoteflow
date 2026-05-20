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
                @if($company->exists || $company->logo_path)
                    <img class="mt-3 aspect-square w-28 rounded-2xl object-cover shadow-sm" src="{{ $company->logoUrl() }}" alt="{{ $company->displayName() }}">
                @else
                    <div class="mt-3 grid aspect-square w-28 place-items-center rounded-2xl border border-dashed border-slate-300 bg-white text-center text-xs font-bold uppercase tracking-wide text-slate-400 shadow-sm">
                        Logo preview
                    </div>
                @endif
                <label class="form-label mt-4">Upload logo
                    <input class="form-input" type="file" name="logo" accept="image/*">
                </label>
                <p class="mt-3 text-xs font-semibold leading-5 text-slate-500">Use a square PNG/JPG logo. Existing documents will immediately use this company profile.</p>
            </aside>
        </div>
    </section>

    <section class="panel space-y-5">
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-[#0a4f93]">Global document settings</p>
            <h2 class="panel-title">Country, currency, tax, and formats</h2>
            <p class="panel-subtitle">These defaults help QuoteFlow support different countries and document standards without changing code.</p>
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

        if (!countrySelect) {
            return;
        }

        countrySelect.addEventListener('change', () => {
            const defaults = countryDefaults[countrySelect.value] || countryDefaults.Other;

            Object.entries(defaults).forEach(([field, value]) => {
                const input = document.querySelector(`[data-country-default-field="${field}"]`);

                if (input && value !== undefined && value !== null) {
                    input.value = value;
                }
            });
        });
    });
</script>
@endsection