@extends('layouts.app', [
    'title' => $product->exists ? 'Edit Product / Service' : 'New Product / Service',
    'contentMode' => 'fullscreen',
    'showDateControl' => false,
])

@php
    $isEditing = $product->exists;
    $selectedType = old('type', $product->type ?? 'product');
@endphp

@section('content')
<form
    method="post"
    action="{{ $isEditing ? route('products.update', $product) : route('products.store') }}"
    class="fullscreen-workspace product-form-workspace"
    data-product-form
>
    @csrf
    @if($isEditing)
        @method('put')
    @endif

    <section class="directory-page-header">
        <div class="min-w-0">
            <p class="document-pane-kicker">Products & Services</p>
            <h1 class="document-pane-title">{{ $isEditing ? 'Edit Item' : 'New Item' }}</h1>
            <p class="mt-1 max-w-3xl text-sm font-semibold leading-6 text-slate-500">
                Set up the reusable description, unit, and pricing defaults for quotations, purchase orders, and invoices.
            </p>
        </div>
        <div class="directory-header-actions">
            <a class="btn btn-secondary" href="{{ route('products.index') }}">Cancel</a>
            <button class="btn btn-primary" type="submit">Save item</button>
        </div>
    </section>

    <div class="product-form-body">
        <section class="product-form-main">
            <section class="directory-form-section">
                <div class="directory-form-section-header">
                    <div>
                        <p class="studio-section-kicker">Item setup</p>
                        <h2 class="studio-section-title">Identity and document description</h2>
                        <p class="studio-section-copy">Write the description as it should appear on the quotation, PO, or invoice line item.</p>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-[15rem_1fr]">
                    <div class="space-y-4">
                        <label class="form-label">Type
                            <select class="form-input" name="type" data-product-type required>
                                <option value="product" @selected($selectedType === 'product')>Product</option>
                                <option value="service" @selected($selectedType === 'service')>Service</option>
                            </select>
                        </label>
                        <label class="form-label">SKU / item code
                            <input class="form-input" name="sku" value="{{ old('sku', $product->sku) }}" data-product-sku placeholder="SVC-IMPL">
                        </label>
                        <label class="directory-toggle-card">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active))>
                            <span>
                                <strong>Active item</strong>
                                <small>Allow this item to be selected in new documents.</small>
                            </span>
                        </label>
                    </div>

                    <div class="space-y-4">
                        <label class="form-label">Item name
                            <input class="form-input" name="name" value="{{ old('name', $product->name) }}" data-product-name placeholder="Implementation service" required>
                        </label>
                        <div class="grid gap-4 md:grid-cols-2">
                            <label class="form-label">Unit
                                <input class="form-input" name="unit" value="{{ old('unit', $product->unit) }}" data-product-unit placeholder="job, set, lot, hour" required>
                            </label>
                            <label class="form-label">Default document price
                                <input class="form-input" type="number" step="0.01" name="selling_price" value="{{ old('selling_price', $product->selling_price) }}" data-product-sell required>
                            </label>
                        </div>
                        <label class="form-label">Reusable document description
                            <span class="mt-1 block text-xs font-semibold leading-5 text-slate-500">Appears on quotation, purchase order, and invoice line items when this product or service is selected.</span>
                            <textarea class="form-input min-h-40 leading-6" name="description" data-product-description placeholder="Describe the actual work, deliverable, material, or service scope the customer/supplier will understand.">{{ old('description', $product->description) }}</textarea>
                        </label>
                    </div>
                </div>
            </section>

            <section class="directory-form-section">
                <div class="directory-form-section-header">
                    <div>
                        <p class="studio-section-kicker">Commercial defaults</p>
                        <h2 class="studio-section-title">Internal commercial defaults</h2>
                        <p class="studio-section-copy">These values support internal review and tax defaults. They are not shown as line-item cost on quotation, PO, or invoice documents.</p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="form-label">Internal cost estimate
                        <span class="mt-1 block text-xs font-semibold leading-5 text-slate-500">For internal margin reference. Not printed on quotation, PO, or invoice documents.</span>
                        <input class="form-input" type="number" step="0.01" name="cost_price" value="{{ old('cost_price', $product->cost_price) }}" data-product-cost placeholder="Optional">
                    </label>
                    <label class="form-label">Default tax rate for totals (%)
                        <input class="form-input" type="number" step="0.01" name="tax_rate" value="{{ old('tax_rate', $product->tax_rate) }}" data-product-tax required>
                    </label>
                </div>
            </section>
        </section>

        <aside class="product-preview-pane">
            <section class="product-preview-card">
                <p class="document-pane-kicker">Document preview</p>
                <h2 class="mt-1 text-lg font-bold text-slate-950">How this item will appear</h2>
                <p class="mt-1 text-sm font-semibold leading-6 text-slate-500">Preview the line-item text before saving this item.</p>

                <div class="mt-5 rounded-lg border border-slate-200 bg-white p-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <span class="status-chip status-approved" data-preview-type>{{ ucfirst($selectedType) }}</span>
                            <h3 class="mt-3 text-base font-bold text-slate-950" data-preview-name>{{ old('name', $product->name) ?: 'Item name' }}</h3>
                            <p class="mt-1 text-xs font-bold uppercase tracking-wide text-slate-400" data-preview-sku>{{ old('sku', $product->sku) ?: 'SKU / item code' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Unit</p>
                            <p class="mt-1 text-sm font-bold text-slate-950" data-preview-unit>{{ old('unit', $product->unit) ?: 'unit' }}</p>
                        </div>
                    </div>

                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Description</p>
                        <p class="mt-2 min-h-20 whitespace-pre-line text-sm font-semibold leading-6 text-slate-700" data-preview-description>{{ old('description', $product->description) ?: 'Add a reusable description so this item is clear on quotations, purchase orders, and invoices.' }}</p>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg bg-slate-50 p-3">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Default document price</dt>
                            <dd class="mt-1 font-bold text-slate-950" data-preview-sell>{{ number_format((float) old('selling_price', $product->selling_price), 2) }}</dd>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3">
                            <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Unit</dt>
                            <dd class="mt-1 font-bold text-slate-950" data-preview-unit-summary>{{ old('unit', $product->unit) ?: 'unit' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>
        </aside>
    </div>
</form>

<script>
(() => {
    const form = document.querySelector('[data-product-form]');
    if (!form) return;

    const text = (selector, fallback = '') => {
        const value = form.querySelector(selector)?.value?.trim();
        return value || fallback;
    };

    const money = (value) => Number(value || 0).toLocaleString('en-MY', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    function set(selector, value) {
        const target = form.querySelector(selector);
        if (target) target.textContent = value;
    }

    function refreshPreview() {
        set('[data-preview-type]', text('[data-product-type]', 'product').replace(/^./, c => c.toUpperCase()));
        set('[data-preview-name]', text('[data-product-name]', 'Item name'));
        set('[data-preview-sku]', text('[data-product-sku]', 'SKU / item code'));
        set('[data-preview-unit]', text('[data-product-unit]', 'unit'));
        set('[data-preview-description]', text('[data-product-description]', 'Add a reusable description so this item is clear on quotations, purchase orders, and invoices.'));
        set('[data-preview-sell]', money(text('[data-product-sell]', '0')));
        set('[data-preview-unit-summary]', text('[data-product-unit]', 'unit'));
    }

    form.querySelectorAll('input, select, textarea').forEach((field) => {
        field.addEventListener('input', refreshPreview);
        field.addEventListener('change', refreshPreview);
    });

    refreshPreview();
})();
</script>
@endsection
