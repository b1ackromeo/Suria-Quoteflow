@extends('layouts.app', [
    'title' => 'Products and services',
    'contentMode' => 'fullscreen',
])

@php
    $companyProfile = \App\Models\CompanyProfile::active();
    $typeFilter = $typeFilter ?? null;
    $filterUrl = fn (?string $type = null) => route('products.index', array_filter([
        'q' => $searchTerm ?: null,
        'type' => $type,
    ], fn ($value) => filled($value)));
@endphp

@section('content')
<div class="fullscreen-workspace directory-workspace">
    <section class="directory-page-header">
        <div class="min-w-0">
            <p class="document-pane-kicker">Item library</p>
            <h1 class="document-pane-title">Products and services</h1>
            <p class="mt-1 max-w-3xl text-sm font-semibold leading-6 text-slate-500">
                Maintain the product and service descriptions that appear on quotations, purchase orders, and invoices.
            </p>
        </div>
        <div class="directory-header-actions">
            <a class="btn btn-primary" href="{{ route('products.create') }}">New item</a>
        </div>
    </section>

    <main class="product-library-body">
        <section class="product-library-workbench">
            <form class="product-library-search" method="get">
                <input type="hidden" name="type" value="{{ $typeFilter }}">
                <label class="form-label product-library-search-field">Search item
                    <input class="form-input" name="q" value="{{ $searchTerm }}" placeholder="Search name, SKU, description, service type, or unit">
                </label>
                <button class="btn btn-primary" type="submit">Search</button>
                @if($searchTerm !== '' || $typeFilter)
                    <a class="btn btn-secondary" href="{{ route('products.index') }}">Clear</a>
                @endif
            </form>

            <div class="product-library-tabs" aria-label="Filter products and services">
                <a class="product-library-tab {{ $typeFilter === null ? 'is-active' : '' }}" href="{{ $filterUrl(null) }}">
                    <span>All</span>
                    <strong>{{ $summary['total'] }}</strong>
                </a>
                <a class="product-library-tab {{ $typeFilter === 'service' ? 'is-active' : '' }}" href="{{ $filterUrl('service') }}">
                    <span>Services</span>
                    <strong>{{ $summary['services'] }}</strong>
                </a>
                <a class="product-library-tab {{ $typeFilter === 'product' ? 'is-active' : '' }}" href="{{ $filterUrl('product') }}">
                    <span>Products</span>
                    <strong>{{ $summary['products'] }}</strong>
                </a>
            </div>
        </section>

        <section class="product-library-panel">
            <div class="product-library-panel-header">
                <div>
                    <h2>Products and services</h2>
                </div>
                <span class="issuer-mini">{{ $products->total() }} shown</span>
            </div>

            <div class="product-library-list">
                @forelse($products as $product)
                    <article class="product-library-row">
                        <div class="product-library-main">
                            <div class="product-library-tags">
                                <span class="product-library-type">{{ ucfirst($product->type) }}</span>
                                <span class="product-library-code">{{ $product->sku ?: 'No item code' }}</span>
                                <span class="status-chip {{ $product->is_active ? 'status-approved' : 'status-cancelled' }}">{{ $product->is_active ? 'Available' : 'Not selectable' }}</span>
                            </div>

                            <h3>{{ $product->name }}</h3>
                        </div>

                        <div class="product-library-description">
                            <p class="product-library-description-label">Description shown on documents</p>
                            @if($product->description)
                                <p>{{ $product->description }}</p>
                            @else
                                <p class="text-amber-800">Description missing. Add the text that should appear on quotation, PO, and invoice line items.</p>
                            @endif
                        </div>

                        <aside class="product-library-facts">
                            <dl>
                                <div>
                                    <dt>Unit</dt>
                                    <dd>{{ $product->unit }}</dd>
                                </div>
                                <div>
                                    <dt>Default document price</dt>
                                    <dd>{{ $companyProfile->formatMoney($product->selling_price) }}</dd>
                                </div>
                            </dl>
                        </aside>

                        <div class="product-library-actions">
                            <a class="btn btn-secondary w-full" href="{{ route('products.edit', $product) }}">Open</a>
                        </div>
                    </article>
                @empty
                    <div class="product-library-empty">
                        No products or services found.
                    </div>
                @endforelse
            </div>

            <div class="directory-pagination">{{ $products->links() }}</div>
        </section>
    </main>
</div>
@endsection
