@extends('layouts.app', [
    'title' => 'Customers',
    'contentMode' => 'fullscreen',
])

@section('content')
<div class="fullscreen-workspace directory-workspace">
    <section class="directory-page-header">
        <div class="min-w-0">
            <p class="document-pane-kicker">Directory</p>
            <h1 class="document-pane-title">Customers</h1>
            <p class="mt-1 max-w-3xl text-sm font-semibold leading-6 text-slate-500">
                Maintain customer billing details, delivery contacts, payment terms, and account status used across quotations, customer POs, and invoices.
            </p>
        </div>
        <div class="directory-header-actions">
            <span class="issuer-mini">{{ $customers->total() }} shown</span>
            <span class="status-chip status-approved">{{ $summary['active'] }} active</span>
            <a class="btn btn-primary" href="{{ route('customers.create') }}">New customer</a>
        </div>
    </section>

    <div class="directory-body">
        <aside class="directory-side-panel">
            <form class="directory-filter-card" method="get">
                <label class="form-label">Find customer
                    <input class="form-input" name="q" value="{{ $searchTerm }}" placeholder="Name, code, contact, email, phone">
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <button class="btn btn-primary" type="submit">Search</button>
                    <a class="btn btn-secondary" href="{{ route('customers.index') }}">Clear</a>
                </div>
            </form>

            <div class="directory-stat-grid">
                <div class="directory-stat-card">
                    <span>Total customers</span>
                    <strong>{{ $summary['total'] }}</strong>
                </div>
                <div class="directory-stat-card">
                    <span>Active accounts</span>
                    <strong>{{ $summary['active'] }}</strong>
                </div>
                <div class="directory-stat-card">
                    <span>Avg default term</span>
                    <strong>{{ $summary['average_terms'] }} days</strong>
                </div>
            </div>
        </aside>

        <section class="directory-table-pane">
            <div class="directory-table-header">
                <div>
                    <h2 class="panel-title">Customer List</h2>
                    <p class="panel-subtitle">Open a customer to update contact, address, tax, and payment term details.</p>
                </div>
                @if($searchTerm !== '')
                    <span class="status-chip status-draft">Search: {{ $searchTerm }}</span>
                @endif
            </div>

            <div class="directory-table-scroll">
                <div class="table-wrap directory-table-wrap">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Contact</th>
                            <th>Email</th>
                            <th>Default term</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($customers as $customer)
                            <tr>
                                <td>
                                    <p class="font-bold text-slate-950">{{ $customer->name }}</p>
                                    @if($customer->billing_address)
                                        <p class="mt-1 max-w-md truncate text-xs font-semibold text-slate-500">{{ $customer->billing_address }}</p>
                                    @endif
                                </td>
                                <td class="font-semibold">{{ $customer->code ?: '-' }}</td>
                                <td>{{ $customer->contact_person ?: '-' }}</td>
                                <td>
                                    @if($customer->email)
                                        <a class="link" href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="font-semibold">{{ $customer->payment_terms_days }} days</td>
                                <td>
                                    <span class="status-chip {{ $customer->is_active ? 'status-paid' : 'status-cancelled' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span>
                                </td>
                                <td class="text-right">
                                    <a class="btn btn-secondary" href="{{ route('customers.edit', $customer) }}">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="empty-cell">No customers found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="directory-pagination">{{ $customers->links() }}</div>
        </section>
    </div>
</div>
@endsection
