@extends('layouts.app')

@section('content')
@php
    $companyProfile = \App\Models\CompanyProfile::active();
@endphp

<div class="w-full max-w-md rounded-xl border border-zinc-200 bg-white p-8 shadow-sm">
    <div class="flex items-center gap-3">
        <span class="brand-mark">
            <img class="brand-logo" src="{{ asset('brand/suria-quoteflow-app-icon.svg') }}" alt="" aria-hidden="true">
        </span>
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Suria QuoteFlow</h1>
            <p class="mt-1 text-sm font-semibold text-zinc-600">Commercial document system</p>
        </div>
    </div>
    <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-3">
        <div class="flex items-center gap-3">
            @include('company_profiles.partials.logo-mark', [
                'company' => $companyProfile,
                'imageClass' => 'h-10 w-10 rounded-lg object-cover shadow-sm',
                'placeholderClass' => 'company-logo-placeholder h-10 w-10 rounded-lg text-xs',
            ])
            <div class="min-w-0">
                <p class="text-sm font-bold leading-tight text-slate-950">{{ $companyProfile->displayName() }}</p>
            </div>
        </div>
    </div>
    <p class="mt-5 text-sm font-medium text-zinc-600">Use your company account to continue.</p>
    <form method="post" action="{{ route('login.store') }}" class="mt-6 space-y-4">
        @csrf
        <label class="form-label">Email
            <input class="form-input" type="email" name="email" value="{{ old('email') }}" required autofocus>
        </label>
        <label class="form-label">Password
            <input class="form-input" type="password" name="password" required>
        </label>
        <label class="flex items-center gap-2 text-sm text-zinc-600">
            <input type="checkbox" name="remember" value="1" class="rounded border-zinc-300">
            Keep me signed in
        </label>
        <button class="btn btn-primary w-full" type="submit">Sign in</button>
    </form>
</div>
@endsection
