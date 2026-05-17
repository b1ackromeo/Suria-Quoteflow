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
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="text-slate-950 antialiased">
@auth
    @php
        $contentMode = $contentMode ?? 'default';
        $showDateControl = $showDateControl ?? request()->routeIs('dashboard', 'documents.*', 'reports.*', 'payments.*');
        $companyProfile = $layoutCompanyProfile;
        $moduleRoute = request()->route('module');
        $isModule = fn ($module) => request()->routeIs('documents.*') && $moduleRoute === $module;
        $primaryNav = [
            ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
            ['label' => 'Reports', 'icon' => 'reports', 'route' => route('reports.index'), 'active' => request()->routeIs('reports.*')],
            ['label' => 'Exports', 'icon' => 'export', 'route' => route('reports.export'), 'active' => false],
        ];
        if (auth()->user()->hasRole('admin', 'manager', 'accounts')) {
            $primaryNav[] = ['label' => 'Payments', 'icon' => 'payments', 'route' => route('payments.index'), 'active' => request()->routeIs('payments.*')];
        }
        $salesLinks = [
            ['label' => 'Customer Quotations', 'icon' => 'quote', 'module' => 'customer-quotations'],
            ['label' => 'PO Received', 'icon' => 'purchase', 'module' => 'customer-pos'],
            ['label' => 'Customer Invoices', 'icon' => 'receipt', 'module' => 'customer-invoices'],
        ];
        $procurementLinks = [
            ['label' => 'Purchase Requests', 'icon' => 'quote', 'module' => 'purchase-requests'],
            ['label' => 'Supplier Quotations', 'icon' => 'quote', 'module' => 'supplier-quotations'],
            ['label' => 'Purchase Orders', 'icon' => 'purchase', 'module' => 'supplier-pos'],
            ['label' => 'Receiving Records', 'icon' => 'receipt', 'module' => 'goods-receipts'],
            ['label' => 'Supplier Invoices', 'icon' => 'receipt', 'module' => 'supplier-invoices'],
        ];
        $taskLinks = [
            ['label' => 'Pending approvals', 'icon' => 'admin', 'route' => route('approvals.pending'), 'active' => request()->routeIs('approvals.pending')],
            ['label' => 'Verify invoices', 'icon' => 'receipt', 'route' => route('documents.index', ['module' => 'supplier-invoices', 'status' => 'draft']), 'active' => $isModule('supplier-invoices') && request('status') === 'draft'],
            ['label' => 'Match invoices', 'icon' => 'purchase', 'route' => route('documents.index', ['module' => 'supplier-invoices', 'status' => 'approved']), 'active' => $isModule('supplier-invoices') && request('status') === 'approved'],
        ];
        if (auth()->user()->hasRole('admin', 'manager', 'accounts')) {
            $taskLinks[] = ['label' => 'Payments', 'icon' => 'payments', 'route' => route('payments.index'), 'active' => request()->routeIs('payments.*')];
        }
    @endphp

    <div class="min-h-screen xl:flex">
        <aside class="app-sidebar" aria-label="Application navigation">
            <div class="sidebar-brand">
                <div class="flex items-start justify-between gap-4">
                <a href="{{ route('dashboard') }}" class="brand-lockup" aria-label="Suria QuoteFlow dashboard">
                    <span class="brand-mark">SQ</span>
                    <span>
                        <span class="block text-base font-black leading-tight tracking-tight">Suria QuoteFlow</span>
                        <span class="block text-xs font-semibold text-slate-500">Commercial operations workspace</span>
                    </span>
                </a>
                <form method="post" action="{{ route('logout') }}">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <button type="submit" class="inline-flex min-h-9 items-center rounded-md px-2 text-xs font-semibold uppercase tracking-wide text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:ring-offset-2">Logout</button>
                </form>
                </div>
                <div class="company-identity-card">
                    <div class="flex items-center gap-3">
                        <img class="h-9 w-9 rounded-lg object-cover shadow-sm" src="{{ $companyProfile->logoUrl() }}" alt="{{ $companyProfile->displayName() }}">
                        <div class="min-w-0">
                            <p class="text-sm font-black leading-tight text-slate-950">{{ $companyProfile->displayName() }}</p>
                        </div>
                    </div>
                </div>
            </div>

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
            </details>

            @include('layouts.partials.navigation', [
                'primaryNav' => $primaryNav,
                'taskLinks' => $taskLinks,
                'salesLinks' => $salesLinks,
                'procurementLinks' => $procurementLinks,
                'isModule' => $isModule,
                'mobile' => false,
            ])
        </aside>

        <main class="flex min-h-screen min-w-0 flex-1 flex-col xl:h-screen xl:min-h-0 xl:pl-72">
            <header class="app-header">
                <div class="header-tools">
                    <form class="global-search" method="get" action="{{ route('search.index') }}" role="search" aria-label="Global search">
                        <x-icon name="search" class="h-4 w-4 text-slate-400" aria-hidden="true" />
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="Search document no., customer, supplier, item, amount..." aria-label="Search Suria QuoteFlow">
                        <button type="submit" class="sr-only">Search</button>
                    </form>
                    <div class="company-identity-pill">
                        <img src="{{ $companyProfile->logoUrl() }}" alt="{{ $companyProfile->displayName() }}">
                        <span class="min-w-0">
                            <strong class="company-identity-pill-name">{{ $companyProfile->displayName() }}</strong>
                        </span>
                    </div>
                    @if($showDateControl)
                        <div class="date-control">
                            <x-icon name="calendar" class="h-4 w-4 text-slate-500" aria-hidden="true" />
                            <span>{{ now()->startOfMonth()->format('d M Y') }} - {{ now()->format('d M Y') }}</span>
                            <x-icon name="chevron" class="h-4 w-4 text-slate-400" aria-hidden="true" />
                        </div>
                    @endif
                    <a class="icon-button notification-button" href="{{ route('approvals.pending') }}" aria-label="Pending approvals">
                        <x-icon name="bell" class="h-5 w-5" aria-hidden="true" />
                        <span>{{ \App\Models\Approval::where('status', 'pending')->count() }}</span>
                    </a>
                    <div class="user-chip">
                        <span class="user-avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="hidden min-w-0 lg:block">
                            <span class="block truncate text-sm font-bold text-slate-900">{{ auth()->user()->name }}</span>
                            <span class="block text-xs font-semibold text-slate-500">{{ \App\Models\User::ROLES[auth()->user()->role] ?? auth()->user()->role }}</span>
                        </span>
                    </div>
                    @yield('header_actions')
                </div>
            </header>

            @php
                $contentClass = $contentMode === 'fullscreen'
                    ? 'min-h-0 min-w-0 flex-1 overflow-hidden'
                    : 'w-full px-4 py-5 sm:px-6 lg:px-8';
                $noticeClass = $contentMode === 'fullscreen'
                    ? 'mx-4 mt-4'
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
