@extends('layouts.app', ['title' => 'Company profile'])

@section('header_actions')
<a class="btn btn-primary" href="{{ route('company-profiles.edit', $company) }}">Edit company profile</a>
@endsection

@section('content')
<div class="grid gap-5 xl:grid-cols-[1fr_22rem]">
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-bold uppercase tracking-wide text-[#0a4f93]">Document issuer profile</p>
        <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-950">Company profile</h1>
        <p class="mt-2 max-w-3xl text-sm font-semibold leading-6 text-slate-500">
            This company profile is used by Suria QuoteFlow for the app context, document previews, quotations, purchase orders, invoices, and generated PDF headers.
        </p>
    </section>

    <section class="rounded-xl border border-blue-100 bg-blue-50 p-5">
        <p class="text-xs font-bold uppercase tracking-wide text-blue-800">Current document issuer</p>
        <div class="mt-3 flex items-center gap-3">
            @include('company_profiles.partials.logo-mark', [
                'company' => $company,
                'imageClass' => 'h-12 w-12 rounded-xl object-cover shadow-sm',
                'placeholderClass' => 'company-logo-placeholder h-12 w-12 rounded-xl text-sm',
            ])
            <div class="min-w-0">
                <p class="truncate text-sm font-bold text-slate-950">{{ $company->displayName() }}</p>
                <p class="truncate text-xs font-semibold text-slate-500">{{ $company->email ?: 'No email set' }}</p>
            </div>
        </div>
    </section>
</div>

<section class="panel mt-5">
    <div class="grid gap-5 lg:grid-cols-[18rem_1fr]">
        <aside class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            @include('company_profiles.partials.logo-mark', [
                'company' => $company,
                'imageClass' => 'aspect-square w-28 rounded-2xl object-cover shadow-sm',
                'placeholderClass' => 'company-logo-placeholder aspect-square w-28 rounded-2xl text-2xl',
            ])
            <div class="mt-4 flex gap-2">
                <span class="h-8 w-8 rounded-lg border border-slate-200" style="background: {{ $company->primary_color }}"></span>
                <span class="h-8 w-8 rounded-lg border border-slate-200" style="background: {{ $company->accent_color }}"></span>
            </div>
            <p class="mt-3 text-xs font-semibold leading-5 text-slate-500">Document colors are used on previews and generated PDFs.</p>
        </aside>

        <div class="min-w-0">
            <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-lg font-bold tracking-tight text-slate-950">{{ $company->displayName() }}</h2>
                    <p class="mt-1 text-sm font-semibold text-slate-500">{{ $company->displayTagline() }}</p>
                </div>
                <a class="btn btn-primary" href="{{ route('company-profiles.edit', $company) }}">Edit details</a>
            </div>

            <dl class="mt-4 grid gap-4 text-sm md:grid-cols-2">
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Registration number</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ $company->registration_number ?: '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Email</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ $company->email ?: '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Phone</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ $company->phone ?: '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Document tagline</dt>
                    <dd class="mt-1 font-semibold text-slate-900">{{ $company->tagline ?: '-' }}</dd>
                </div>
                <div class="md:col-span-2">
                    <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Address</dt>
                    <dd class="mt-1 whitespace-pre-line font-semibold text-slate-900">{{ $company->address ?: '-' }}</dd>
                </div>
            </dl>
        </div>
    </div>
</section>

<section class="panel mt-5">
    <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-[#0a4f93]">Global document settings</p>
            <h2 class="text-lg font-bold tracking-tight text-slate-950">Country, currency, tax, and formats</h2>
            <p class="mt-1 text-sm font-semibold text-slate-500">These settings become the default document context for global-ready QuoteFlow installations.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('company-profiles.edit', $company) }}">Edit settings</a>
    </div>

    <dl class="mt-4 grid gap-4 text-sm md:grid-cols-3">
        <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Country</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ $company->displayCountry() }}</dd>
        </div>
        <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Timezone</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ $company->displayTimezone() }}</dd>
        </div>
        <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Base currency</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ $company->baseCurrency() }}</dd>
        </div>
        <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Date format</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ now()->format($company->dateFormat()) }}</dd>
        </div>
        <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Number format</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ $numberFormats[$company->numberFormat()] ?? $company->numberFormat() }}</dd>
        </div>
        <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Default tax</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ $company->taxLabel() }} · {{ number_format($company->defaultTaxRate(), 2) }}%</dd>
        </div>
        <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Tax registration label</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ $company->taxRegistrationLabel() }}</dd>
        </div>
        <div class="md:col-span-2">
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Tax registration number</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ $company->tax_registration_number ?: '-' }}</dd>
        </div>
    </dl>
</section>

<section class="panel mt-5">
    <div class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-[#0a4f93]">Project control settings</p>
            <h2 class="text-lg font-bold tracking-tight text-slate-950">Delivery billing alert levels</h2>
            <p class="mt-1 text-sm font-semibold text-slate-500">These company alert levels are used unless a project has its own alert levels.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('company-profiles.edit', $company) }}">Edit settings</a>
    </div>

    <dl class="mt-4 grid gap-4 text-sm md:grid-cols-2">
        <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Customer unbilled / not yet received</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ $company->formatPercent($company->deliveryGapAlertPercent()) }}</dd>
        </div>
        <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Received not invoiced</dt>
            <dd class="mt-1 font-semibold text-slate-900">{{ $company->formatPercent($company->receivedNotInvoicedAlertPercent()) }}</dd>
        </div>
    </dl>
</section>

<section class="panel mt-5">
    <div>
        <p class="text-xs font-bold uppercase tracking-wide text-[#0a4f93]">PDF document text</p>
        <h2 class="text-lg font-bold tracking-tight text-slate-950">Payment instructions and footer</h2>
    </div>

    <dl class="mt-4 grid gap-4 text-sm md:grid-cols-2">
        <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">Payment instructions</dt>
            <dd class="mt-1 whitespace-pre-line font-semibold text-slate-900">{{ $company->payment_instructions ?: '-' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-bold uppercase tracking-wide text-slate-400">PDF footer</dt>
            <dd class="mt-1 whitespace-pre-line font-semibold text-slate-900">{{ $company->pdf_footer ?: '-' }}</dd>
        </div>
    </dl>
</section>
@endsection
