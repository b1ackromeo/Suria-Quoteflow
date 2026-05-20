@extends('layouts.app', ['title' => 'Search'])

@php
    $companyProfile = \App\Models\CompanyProfile::active();
    $totalResults = $documents->count() + $customers->count() + $suppliers->count() + $products->count();
    $canManageCustomers = auth()->user()->hasRole('admin', 'manager', 'sales');
    $canManageSuppliers = auth()->user()->hasRole('admin', 'manager', 'procurement');
    $canManageProducts = auth()->user()->hasRole('admin', 'manager', 'sales', 'procurement');
@endphp

@section('content')
<section class="panel">
    <div class="panel-header">
        <div>
            <p class="document-pane-kicker">Global search</p>
            <h1 class="text-2xl font-bold tracking-tight text-slate-950">Search Suria QuoteFlow</h1>
            <p class="mt-1 text-sm font-semibold text-slate-500">Find documents, customers, suppliers, products, services, payment stages, dates, and amounts.</p>
        </div>
        @if($q !== '')
            <span class="status-chip status-approved">{{ $totalResults }} results</span>
        @endif
    </div>

    <form class="grid gap-3 md:grid-cols-[1fr_auto]" method="get" action="{{ route('search.index') }}">
        <input class="form-input mt-0" type="search" name="q" value="{{ $q }}" placeholder="Search document no., reference, customer, supplier, item, status, or amount..." autofocus>
        <button class="btn btn-primary" type="submit">Search</button>
    </form>
</section>

@if($q === '')
    <section class="panel mt-5">
        <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm font-semibold text-slate-500">
            Enter a document number, reference, customer, supplier, contact, product, service, status, payment stage, date, or amount to search.
        </div>
    </section>
@else
    <section class="mt-5 grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
        <div class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Documents</h2>
                    <p class="panel-subtitle">Matches document details, customer or supplier, line items, payment stages, dates, amounts, attachments, products, and services.</p>
                </div>
                <span class="status-chip status-draft">{{ $documents->count() }}</span>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($documents as $document)
                    @php
                        $slug = \App\Models\Document::slugForType($document->type);
                        $meta = \App\Models\Document::metaForSlug($slug);
                        $stageSummary = $document->billing_stage_name
                            ?: $document->billingStages->pluck('stage_name')->filter()->take(3)->implode(', ');
                        $itemSummary = $document->items->pluck('description')->filter()->take(2)->implode(', ');
                        $attachmentSummary = $document->attachments->pluck('original_name')->filter()->take(2)->implode(', ');
                    @endphp
                    <article class="py-4 first:pt-0 last:pb-0">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $meta['singular'] }}</p>
                                <a class="mt-1 block text-base font-bold text-[#0a4f93]" href="{{ route('documents.show', $document) }}">{{ $document->document_number }}</a>
                                <p class="mt-1 truncate text-sm font-bold text-slate-900">{{ $document->partyName() }}</p>
                            </div>
                            <span class="status-chip status-{{ $document->status }}">{{ $document->statusDisplay() }}</span>
                        </div>
                        <dl class="mt-3 grid gap-3 text-sm md:grid-cols-3">
                            <div>
                                <dt class="document-row-meta">Reference</dt>
                                <dd class="mt-1 font-semibold text-slate-700">{{ $document->external_reference ?: '-' }}</dd>
                            </div>
                            <div>
                                <dt class="document-row-meta">Issue date</dt>
                                <dd class="mt-1 font-semibold text-slate-700">{{ $document->issue_date ? $companyProfile->formatDate($document->issue_date) : '-' }}</dd>
                            </div>
                            <div>
                                <dt class="document-row-meta">Total</dt>
                                <dd class="mt-1 font-bold text-slate-950">{{ $companyProfile->formatMoney($document->total, $document->currency) }}</dd>
                            </div>
                            @if($document->project_name)
                                <div>
                                    <dt class="document-row-meta">Project / site</dt>
                                    <dd class="mt-1 font-semibold text-slate-700">{{ $document->project_name }}</dd>
                                </div>
                            @endif
                            @if($document->delivery_to)
                                <div>
                                    <dt class="document-row-meta">Delivery / service location</dt>
                                    <dd class="mt-1 font-semibold text-slate-700">{{ \Illuminate\Support\Str::limit($document->delivery_to, 80) }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt class="document-row-meta">Payment terms</dt>
                                <dd class="mt-1 font-semibold text-slate-700">{{ $document->paymentTermsDisplay() }}</dd>
                            </div>
                            @if($stageSummary)
                                <div>
                                    <dt class="document-row-meta">Billing stage</dt>
                                    <dd class="mt-1 font-semibold text-slate-700">{{ $stageSummary }}</dd>
                                </div>
                            @endif
                            @if($itemSummary)
                                <div class="md:col-span-2">
                                    <dt class="document-row-meta">Item / service</dt>
                                    <dd class="mt-1 font-semibold text-slate-700">{{ $itemSummary }}</dd>
                                </div>
                            @endif
                            @if($document->relatedDocument)
                                <div>
                                    <dt class="document-row-meta">Related document</dt>
                                    <dd class="mt-1 font-semibold text-slate-700">{{ $document->relatedDocument->document_number }}</dd>
                                </div>
                            @endif
                            @if($attachmentSummary)
                                <div class="md:col-span-2">
                                    <dt class="document-row-meta">Attachment</dt>
                                    <dd class="mt-1 font-semibold text-slate-700">{{ $attachmentSummary }}</dd>
                                </div>
                            @endif
                        </dl>
                    </article>
                @empty
                    <div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm font-semibold text-slate-500">
                        No matching documents.
                    </div>
                @endforelse
            </div>
        </div>

        <div class="space-y-5">
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Customers</h2>
                        <p class="panel-subtitle">Name, code, contact, email, phone, address, or tax number.</p>
                    </div>
                    <span class="status-chip status-draft">{{ $customers->count() }}</span>
                </div>
                <div class="space-y-3">
                    @forelse($customers as $customer)
                        <div class="rounded-lg border border-slate-200 bg-white p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-slate-950">{{ $customer->name }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $customer->code ?: 'No code' }} · {{ $customer->email ?: 'No email' }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">Default term: {{ $customer->payment_terms_days }} days</p>
                                </div>
                                @if($canManageCustomers)
                                    <a class="btn btn-secondary min-h-9 px-3 py-1.5" href="{{ route('customers.edit', $customer) }}">Open</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm font-semibold text-slate-500">No matching customers.</p>
                    @endforelse
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Suppliers</h2>
                        <p class="panel-subtitle">Name, code, contact, email, phone, address, or tax number.</p>
                    </div>
                    <span class="status-chip status-draft">{{ $suppliers->count() }}</span>
                </div>
                <div class="space-y-3">
                    @forelse($suppliers as $supplier)
                        <div class="rounded-lg border border-slate-200 bg-white p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-slate-950">{{ $supplier->name }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $supplier->code ?: 'No code' }} · {{ $supplier->category ?: 'Uncategorised' }} · {{ $supplier->email ?: 'No email' }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">Default term: {{ $supplier->payment_terms_days }} days</p>
                                </div>
                                @if($canManageSuppliers)
                                    <a class="btn btn-secondary min-h-9 px-3 py-1.5" href="{{ route('suppliers.edit', $supplier) }}">Open</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm font-semibold text-slate-500">No matching suppliers.</p>
                    @endforelse
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Products and services</h2>
                        <p class="panel-subtitle">Item name, SKU, type, description, or unit.</p>
                    </div>
                    <span class="status-chip status-draft">{{ $products->count() }}</span>
                </div>
                <div class="space-y-3">
                    @forelse($products as $product)
                        <div class="rounded-lg border border-slate-200 bg-white p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-slate-950">{{ $product->name }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $product->sku ?: 'No SKU' }} · {{ ucfirst($product->type) }} · {{ $product->unit }}</p>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">Sell: {{ $companyProfile->formatMoney($product->selling_price) }} · Cost: {{ $companyProfile->formatMoney($product->cost_price) }}</p>
                                </div>
                                @if($canManageProducts)
                                    <a class="btn btn-secondary min-h-9 px-3 py-1.5" href="{{ route('products.edit', $product) }}">Open</a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-sm font-semibold text-slate-500">No matching products or services.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </section>
@endif
@endsection
