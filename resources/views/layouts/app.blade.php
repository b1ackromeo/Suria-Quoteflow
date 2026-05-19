<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $layoutCompanyProfile = \App\Models\CompanyProfile::active();
    @endphp
    <title>{{ $title ?? 'Suria QuoteFlow' }} | Suria QuoteFlow</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('brand/favicon.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="text-slate-950 antialiased">
@auth
    @php
        $contentMode = $contentMode ?? 'default';
        $companyProfile = $layoutCompanyProfile;
        $moduleRoute = request()->route('module');
        $isModule = fn ($module) => request()->routeIs('documents.*') && $moduleRoute === $module;
        $primaryNav = [
            ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
            ['label' => 'Finance reports', 'icon' => 'reports', 'route' => route('reports.index'), 'active' => request()->routeIs('reports.*')],
        ];
        if (auth()->user()->hasRole('admin', 'manager', 'accounts')) {
            $primaryNav[] = ['label' => 'Payments', 'icon' => 'payments', 'route' => route('payments.index'), 'active' => request()->routeIs('payments.*')];
        }
        $salesLinks = [
            ['label' => 'Customer quotations', 'icon' => 'quote', 'module' => 'customer-quotations'],
            ['label' => 'Customer POs received', 'icon' => 'purchase', 'module' => 'customer-pos'],
            ['label' => 'Customer invoices', 'icon' => 'receipt', 'module' => 'customer-invoices'],
        ];
        $procurementLinks = [
            ['label' => 'Purchase requests', 'icon' => 'quote', 'module' => 'purchase-requests'],
            ['label' => 'Supplier quotations', 'icon' => 'quote', 'module' => 'supplier-quotations'],
            ['label' => 'Purchase orders', 'icon' => 'purchase', 'module' => 'supplier-pos'],
            ['label' => 'Goods receipts', 'icon' => 'receipt', 'module' => 'goods-receipts'],
            ['label' => 'Supplier invoices', 'icon' => 'receipt', 'module' => 'supplier-invoices'],
        ];
        $taskLinks = [
            ['label' => 'Pending approvals', 'icon' => 'admin', 'route' => route('approvals.pending'), 'active' => request()->routeIs('approvals.pending')],
            ['label' => 'Verify supplier invoices', 'icon' => 'receipt', 'route' => route('documents.index', ['module' => 'supplier-invoices', 'status' => 'draft']), 'active' => $isModule('supplier-invoices') && request('status') === 'draft'],
            ['label' => 'Match supplier invoices', 'icon' => 'purchase', 'route' => route('documents.index', ['module' => 'supplier-invoices', 'status' => 'approved']), 'active' => $isModule('supplier-invoices') && request('status') === 'approved'],
        ];
        if (auth()->user()->hasRole('admin', 'manager', 'accounts')) {
            $taskLinks[] = ['label' => 'Payments', 'icon' => 'payments', 'route' => route('payments.index'), 'active' => request()->routeIs('payments.*')];
        }
    @endphp

    <div class="min-h-screen xl:flex">
        <aside class="app-sidebar" aria-label="Application navigation">
            <div class="sidebar-brand">
                <a href="{{ route('dashboard') }}" class="brand-lockup-horizontal" aria-label="Suria QuoteFlow dashboard">
                    <img class="brand-horizontal-logo" src="{{ asset('brand/suria-quoteflow-horizontal-lockup.svg') }}" alt="" aria-hidden="true">
                </a>
            </div>

            @include('layouts.partials.sidebar-workspace', [
                'companyProfile' => $companyProfile,
            ])

            <details class="mobile-nav-menu">
                <summary>
                    <span class="font-bold text-slate-900">Menu</span>
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Navigation</span>
                </summary>
                @include('layouts.partials.navigation', [
                    'primaryNav' => $primaryNav,
                    'taskLinks' => $taskLinks,
                    'salesLinks' => $salesLinks,
                    'procurementLinks' => $procurementLinks,
                    'isModule' => $isModule,
                    'mobile' => true,
                ])
                @include('layouts.partials.sidebar-account')
            </details>

            @include('layouts.partials.navigation', [
                'primaryNav' => $primaryNav,
                'taskLinks' => $taskLinks,
                'salesLinks' => $salesLinks,
                'procurementLinks' => $procurementLinks,
                'isModule' => $isModule,
                'mobile' => false,
            ])
            <div class="sidebar-account-desktop">
                @include('layouts.partials.sidebar-account', [
                    'companyProfile' => $companyProfile,
                ])
            </div>
        </aside>

        <main class="flex min-h-screen min-w-0 flex-1 flex-col lg:h-screen lg:min-h-0 lg:pl-72">
            <header class="app-header">
                <div class="header-tools">
                    <form class="global-search" method="get" action="{{ route('search.index') }}" role="search" aria-label="Global search">
                        <x-icon name="search" class="h-4 w-4 text-slate-400" aria-hidden="true" />
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search document no., customer, supplier, item, amount..." aria-label="Search Suria QuoteFlow">
                        <button type="submit" class="sr-only">Search</button>
                    </form>
                    @yield('header_actions')
                </div>
            </header>

            @php
                $contentClass = match ($contentMode) {
                    'fullscreen' => 'min-h-0 min-w-0 flex-1 overflow-hidden',
                    'dashboard' => 'min-h-0 min-w-0 flex-1 overflow-auto px-4 py-3 sm:px-5 lg:overflow-hidden lg:px-6',
                    default => 'w-full px-4 py-5 sm:px-6 lg:px-8',
                };
                $noticeClass = in_array($contentMode, ['fullscreen', 'dashboard'], true)
                    ? 'mb-3'
                    : 'mb-5';
            @endphp

            <div class="{{ $contentClass }}">
                @if(session('status'))
                    <div class="{{ $noticeClass }} rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('status') }}</div>
                @endif
                @if($errors->any())
                    <div class="{{ $noticeClass }} rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        <p class="font-semibold">Please fix the highlighted fields.</p>
                        <ul class="mt-2 list-disc pl-5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
@else
    <main class="flex min-h-screen items-center justify-center bg-slate-100 px-4 py-10">
        @yield('content')
    </main>
@endauth

</body>
</html>
