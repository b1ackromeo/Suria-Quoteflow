@extends('layouts.app', [
    'title' => 'Users',
    'contentMode' => 'fullscreen',
])

@php
    $companyProfile = \App\Models\CompanyProfile::active();
    $roleFilter = $roleFilter ?? null;
    $statusFilter = $statusFilter ?? null;
    $roleNotes = [
        'admin' => 'Full system setup, users, approvals, and company profile.',
        'manager' => 'Approvals, document review, reports, and operational oversight.',
        'sales' => 'Customer quotations, customer PO records, and customer invoices.',
        'procurement' => 'Supplier quotations, purchase orders, and goods/service receipts.',
        'accounts' => 'Customer invoices, supplier invoices, payments, and reports.',
        'viewer' => 'Read-only access to permitted operational records.',
    ];
    $filterUrl = fn (?string $role = null, ?string $status = null) => route('users.index', array_filter([
        'q' => $searchTerm ?: null,
        'role' => $role,
        'status' => $status,
    ], fn ($value) => filled($value)));
@endphp

@section('content')
<div class="fullscreen-workspace access-workspace">
    <section class="directory-page-header">
        <div class="min-w-0">
            <p class="document-pane-kicker">Access control</p>
            <h1 class="document-pane-title">Users</h1>
            <p class="mt-1 max-w-3xl text-sm font-semibold leading-6 text-slate-500">
                Manage sign-in access, account status, and role permissions for Suria QuoteFlow.
            </p>
        </div>
        <div class="directory-header-actions">
            <span class="issuer-mini">{{ $users->total() }} shown</span>
            <span class="status-chip status-approved">{{ $summary['active'] }} active</span>
            <a class="btn btn-primary" href="{{ route('users.create') }}">New user</a>
        </div>
    </section>

    <main class="access-body">
        <section class="access-toolbar">
            <form class="access-search" method="get">
                <input type="hidden" name="role" value="{{ $roleFilter }}">
                <input type="hidden" name="status" value="{{ $statusFilter }}">
                <label class="form-label access-search-field">Find account
                    <input class="form-input" name="q" value="{{ $searchTerm }}" placeholder="Search name, email, or role">
                </label>
                <button class="btn btn-primary" type="submit">Search</button>
                @if($searchTerm !== '' || $roleFilter || $statusFilter)
                    <a class="btn btn-secondary" href="{{ route('users.index') }}">Clear</a>
                @endif
            </form>

            <div class="access-filter-row" aria-label="Filter users">
                <a class="access-filter-chip {{ $roleFilter === null && $statusFilter === null ? 'is-active' : '' }}" href="{{ $filterUrl(null, null) }}">All {{ $summary['total'] }}</a>
                <a class="access-filter-chip {{ $statusFilter === 'active' ? 'is-active' : '' }}" href="{{ $filterUrl($roleFilter, 'active') }}">Active {{ $summary['active'] }}</a>
                <a class="access-filter-chip {{ $statusFilter === 'inactive' ? 'is-active' : '' }}" href="{{ $filterUrl($roleFilter, 'inactive') }}">Inactive {{ $summary['inactive'] }}</a>
                <a class="access-filter-chip {{ $roleFilter === 'admin' ? 'is-active' : '' }}" href="{{ $filterUrl('admin', $statusFilter) }}">Admins</a>
                <a class="access-filter-chip {{ $roleFilter === 'manager' ? 'is-active' : '' }}" href="{{ $filterUrl('manager', $statusFilter) }}">Approvers</a>
            </div>
        </section>

        <section class="access-panel">
            <div class="access-panel-header">
                <div>
                    <h2>Users and permissions</h2>
                    <p>Open an account to update role, active status, or password.</p>
                </div>
                <span class="issuer-mini">{{ $summary['approvers'] }} active approver{{ $summary['approvers'] === 1 ? '' : 's' }}</span>
            </div>

            <div class="access-list">
                @forelse($users as $user)
                    <article class="access-row">
                        <div class="access-person">
                            <div class="access-avatar">{{ collect(explode(' ', $user->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}</div>
                            <div class="min-w-0">
                                <h3>{{ $user->name }}</h3>
                                <a href="mailto:{{ $user->email }}">{{ $user->email }}</a>
                            </div>
                        </div>

                        <div class="access-role">
                            <span>{{ $roles[$user->role] ?? $user->role }}</span>
                            <p>{{ $roleNotes[$user->role] ?? 'Role permissions follow the selected access level.' }}</p>
                        </div>

                        <div class="access-status-cell">
                            <span class="status-chip {{ $user->is_active ? 'status-approved' : 'status-cancelled' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                            <small>Updated {{ $companyProfile->formatDate($user->updated_at) }}</small>
                        </div>

                        <div class="access-actions">
                            <a class="btn btn-secondary w-full" href="{{ route('users.edit', $user) }}">Edit</a>
                        </div>
                    </article>
                @empty
                    <div class="product-library-empty">No user accounts found.</div>
                @endforelse
            </div>

            <div class="directory-pagination">{{ $users->links() }}</div>
        </section>
    </main>
</div>
@endsection
