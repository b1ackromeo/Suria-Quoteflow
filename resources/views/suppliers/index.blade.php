@extends('layouts.app', [
    'title' => 'Suppliers',
    'contentMode' => 'fullscreen',
    'showDateControl' => false,
])

@php
    $statusFilter = $statusFilter ?? null;
    $categoryFilter = $categoryFilter ?? '';
    $filterUrl = fn (?string $status = null) => route('suppliers.index', array_filter([
        'q' => $searchTerm ?: null,
        'status' => $status,
        'category' => $categoryFilter ?: null,
    ], fn ($value) => filled($value)));
@endphp

@section('content')
<div class="fullscreen-workspace supplier-directory-workspace">
    <section class="directory-page-header">
        <div class="min-w-0">
            <p class="document-pane-kicker">Supplier Directory</p>
            <h1 class="document-pane-title">Suppliers</h1>
            <p class="mt-1 max-w-3xl text-sm font-semibold leading-6 text-slate-500">
                Supplier records used for quotations, purchase orders, goods or service receipts, supplier invoices, and payment follow-up.
            </p>
        </div>
        <div class="directory-header-actions">
            <span class="issuer-mini">{{ $suppliers->total() }} shown</span>
            <span class="status-chip status-approved">{{ $summary['active'] }} active</span>
            <a class="btn btn-primary" href="{{ route('suppliers.create') }}">New supplier</a>
        </div>
    </section>

    <main class="supplier-directory-body">
        <section class="supplier-directory-toolbar">
            <form class="supplier-directory-search" method="get">
                <input type="hidden" name="status" value="{{ $statusFilter }}">
                <label class="form-label supplier-directory-search-field">Find supplier
                    <input class="form-input" name="q" value="{{ $searchTerm }}" placeholder="Search name, code, category, contact, email, phone, address, or term">
                </label>
                <label class="form-label supplier-directory-category-field">Category
                    <select class="form-input" name="category">
                        <option value="">All categories</option>
                        @foreach($categoryOptions as $categoryOption)
                            <option value="{{ $categoryOption }}" @selected($categoryFilter === $categoryOption)>{{ $categoryOption }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="btn btn-primary" type="submit">Search</button>
                @if($searchTerm !== '' || $statusFilter || $categoryFilter !== '')
                    <a class="btn btn-secondary" href="{{ route('suppliers.index') }}">Clear</a>
                @endif
            </form>

            <div class="access-filter-row" aria-label="Filter suppliers">
                <a class="access-filter-chip {{ $statusFilter === null ? 'is-active' : '' }}" href="{{ $filterUrl(null) }}">All {{ $summary['total'] }}</a>
                <a class="access-filter-chip {{ $statusFilter === 'active' ? 'is-active' : '' }}" href="{{ $filterUrl('active') }}">Active {{ $summary['active'] }}</a>
                <a class="access-filter-chip {{ $statusFilter === 'inactive' ? 'is-active' : '' }}" href="{{ $filterUrl('inactive') }}">Inactive {{ $summary['inactive'] }}</a>
            </div>
        </section>

        <section class="supplier-directory-panel">
            <div class="supplier-directory-panel-header">
                <div>
                    <h2>Supplier records</h2>
                    <p>Open a record to update contacts, address, default term, or selectable status.</p>
                </div>
                @if($searchTerm !== '')
                    <span class="status-chip status-draft">Search: {{ $searchTerm }}</span>
                @elseif($categoryFilter !== '')
                    <span class="status-chip status-draft">Category: {{ $categoryFilter }}</span>
                @else
                    <span class="issuer-mini">Average simple term {{ $summary['average_terms'] }} days</span>
                @endif
            </div>

            <div class="supplier-directory-list">
                @forelse($suppliers as $supplier)
                    <article class="supplier-directory-row">
                        <div class="supplier-directory-identity">
                            <div class="supplier-directory-avatar">{{ mb_substr($supplier->name, 0, 1) }}</div>
                            <div class="min-w-0">
                                <div class="supplier-directory-tags">
                                    <span class="supplier-directory-code">{{ $supplier->code ?: 'No supplier code' }}</span>
                                    <span class="supplier-directory-category">{{ $supplier->category ?: 'Uncategorised' }}</span>
                                    <span class="status-chip {{ $supplier->is_active ? 'status-approved' : 'status-cancelled' }}">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</span>
                                </div>
                                <h3>{{ $supplier->name }}</h3>
                                @if($supplier->address)
                                    <p class="supplier-directory-address">{{ $supplier->address }}</p>
                                @else
                                    <p class="supplier-directory-address text-amber-800">Address missing. Add it before issuing supplier documents.</p>
                                @endif
                            </div>
                        </div>

                        <div class="supplier-directory-contact">
                            <span>Procurement contact</span>
                            <strong>{{ $supplier->contact_person ?: 'No contact person' }}</strong>
                            <p>
                                @if($supplier->email)
                                    <a class="link" href="mailto:{{ $supplier->email }}">{{ $supplier->email }}</a>
                                @else
                                    No email
                                @endif
                                @if($supplier->phone)
                                    <br>{{ $supplier->phone }}
                                @endif
                            </p>
                        </div>

                        <div class="supplier-directory-term">
                            <span>Default invoice term</span>
                            <strong>{{ $supplier->payment_terms_days }} days after invoice</strong>
                            <p>Used only as a simple default. Staged terms are set on the purchase order or supplier invoice.</p>
                        </div>

                        <div class="supplier-directory-actions">
                            <a class="btn btn-secondary w-full" href="{{ route('suppliers.edit', $supplier) }}">Open</a>
                        </div>
                    </article>
                @empty
                    <div class="product-library-empty">
                        No suppliers found.
                    </div>
                @endforelse
            </div>

            <div class="directory-pagination">{{ $suppliers->links() }}</div>
        </section>
    </main>
</div>
@endsection
