@extends('layouts.app', [
    'title' => $user->exists ? 'Edit User' : 'New User',
    'contentMode' => 'fullscreen',
])

@section('content')
@php
    $roleNotes = [
        'admin' => 'Full system setup, users, approvals, and company profile.',
        'manager' => 'Approvals, document review, reports, and operational oversight.',
        'sales' => 'Customer quotations, customer PO records, and customer invoices.',
        'procurement' => 'Supplier quotations, purchase orders, and goods/service receipts.',
        'accounts' => 'Customer invoices, supplier invoices, payments, and reports.',
        'viewer' => 'Read-only access to permitted operational records.',
    ];
@endphp

<form
    method="post"
    action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}"
    class="fullscreen-workspace access-form-workspace"
>
    @csrf
    @if($user->exists)
        @method('put')
    @endif

    <section class="directory-page-header">
        <div class="min-w-0">
            <p class="document-pane-kicker">Access control</p>
            <h1 class="document-pane-title">{{ $user->exists ? 'Edit User' : 'New User' }}</h1>
            <p class="mt-1 max-w-3xl text-sm font-semibold leading-6 text-slate-500">
                Set the account details, role, and sign-in status for Suria QuoteFlow.
            </p>
        </div>
        <div class="directory-header-actions">
            <a class="btn btn-secondary" href="{{ route('users.index') }}">Cancel</a>
            <button class="btn btn-primary" type="submit">Save user</button>
        </div>
    </section>

    <div class="access-form-body">
        <section class="directory-form-section">
            <div class="directory-form-section-header">
                <div>
                    <p class="studio-section-kicker">Account details</p>
                    <h2 class="studio-section-title">User profile</h2>
                    <p class="studio-section-copy">Use a real work email and assign the role that matches the person’s responsibility.</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <label class="form-label">Full name
                    <input class="form-input" name="name" value="{{ old('name', $user->name) }}" placeholder="Name shown in approvals and audit history" required>
                </label>
                <label class="form-label">Work email
                    <input class="form-input" type="email" name="email" value="{{ old('email', $user->email) }}" placeholder="name@company.com" required>
                </label>
                <label class="form-label">Role
                    <select class="form-input" name="role" required>
                        @foreach($roles as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', $user->role) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="directory-toggle-card">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                    <span>
                        <strong>Active account</strong>
                        <small>Allow this person to sign in.</small>
                    </span>
                </label>
            </div>
        </section>

        <section class="directory-form-section">
            <div class="directory-form-section-header">
                <div>
                    <p class="studio-section-kicker">Sign-in password</p>
                    <h2 class="studio-section-title">{{ $user->exists ? 'Change password' : 'Create password' }}</h2>
                    <p class="studio-section-copy">{{ $user->exists ? 'Leave both password fields blank to keep the current password.' : 'Create the first password for this account.' }}</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <label class="form-label">Password
                    <input class="form-input" type="password" name="password" autocomplete="new-password" @required(! $user->exists)>
                </label>
                <label class="form-label">Confirm password
                    <input class="form-input" type="password" name="password_confirmation" autocomplete="new-password" @required(! $user->exists)>
                </label>
            </div>
        </section>

        <aside class="access-role-guide">
            <h2>Role guide</h2>
            <div class="access-role-guide-list">
                @foreach($roles as $value => $label)
                    <article>
                        <strong>{{ $label }}</strong>
                        <p>{{ $roleNotes[$value] ?? 'Role permissions follow the selected access level.' }}</p>
                    </article>
                @endforeach
            </div>
        </aside>
    </div>
</form>
@endsection
