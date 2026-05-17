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
        $showDateControl = $showDateControl ?? request()->routeIs('documents.*', 'reports.*', 'payments.*');
        $showApprovalShortcut = $showApprovalShortcut ?? ! request()->routeIs('dashboard');
        $companyProfile = $layoutCompanyProfile;
        $moduleRoute = request()->route('module');
        $isModule = fn ($module) => request()->routeIs('documents.*') && $moduleRoute === $module;
        $primaryNav = [
            ['label' => 'Dashboard', 'icon' => 'dashboard', 'route' => route('dashboard'), 'active' => request()->routeIs('dashboard')],
            ['label' => 'Reports', 'icon' => 'reports', 'route' => route('reports.index'), 'active' => request()->routeIs('reports.*')],
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
                    @if($showDateControl)
                        <div class="date-control">
                            <x-icon name="calendar" class="h-4 w-4 text-slate-500" aria-hidden="true" />
                            <span>{{ now()->startOfMonth()->format('d M Y') }} - {{ now()->format('d M Y') }}</span>
                            <x-icon name="chevron" class="h-4 w-4 text-slate-400" aria-hidden="true" />
                        </div>
                    @endif
                    @if($showApprovalShortcut)
                        <a class="icon-button notification-button" href="{{ route('approvals.pending') }}" aria-label="Pending approvals">
                            <x-icon name="bell" class="h-5 w-5" aria-hidden="true" />
                            <span>{{ \App\Models\Approval::where('status', 'pending')->count() }}</span>
                        </a>
                    @endif
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

@if(request()->routeIs('documents.create') && request()->route('module') === 'purchase-requests')
<script>
(() => {
    const form = document.querySelector('form[action*="/documents/purchase-requests"]');
    if (!form || form.dataset.purchaseRequestQuoteUploadReady === 'true') return;

    form.dataset.purchaseRequestQuoteUploadReady = 'true';
    form.enctype = 'multipart/form-data';

    const header = form.querySelector('.workspace-pane-header');
    const firstSection = form.querySelector('.studio-section');
    const insertBefore = firstSection || header?.nextElementSibling;
    const panel = document.createElement('section');
    panel.className = 'studio-source-panel';
    panel.setAttribute('aria-labelledby', 'purchase-request-source-heading');
    panel.innerHTML = `
        <div>
            <p class="studio-section-kicker">Source path</p>
            <h2 id="purchase-request-source-heading" class="studio-section-title">Start from supplier quotation</h2>
            <p class="studio-section-copy">Upload the supplier quotation first. OCR will read the quote fields and line items after the draft PR is created, then the verified quote lines will populate the PR line items.</p>
        </div>
        <div class="source-choice-grid">
            <label class="source-choice-card">
                <input class="source-choice-input" type="radio" name="source_type" value="supplier_quote" checked data-pr-source-type>
                <span>
                    <span class="source-choice-title">Supplier quotation upload</span>
                    <span class="source-choice-copy">Normal path. The quotation file is the source for OCR and PR line items.</span>
                </span>
            </label>
            <label class="source-choice-card">
                <input class="source-choice-input" type="radio" name="source_type" value="quote_exception" data-pr-source-type>
                <span>
                    <span class="source-choice-title">Quote exception</span>
                    <span class="source-choice-copy">Use only when no supplier quotation is available. Add a reason and enter PR lines manually.</span>
                </span>
            </label>
        </div>
        <label class="form-label" data-pr-quote-upload-wrap>Supplier quotation PDF or image
            <input class="form-input file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-bold file:text-slate-700 hover:file:bg-slate-200" type="file" name="source_attachment" accept=".pdf,.jpg,.jpeg,.png,.webp,.bmp,.tif,.tiff" data-pr-quote-upload>
            <span class="mt-1 block text-xs font-semibold text-slate-500">Choose the supplier quotation, then click Create draft & run OCR.</span>
        </label>
        <div class="flex flex-wrap items-center gap-3" data-pr-run-ocr-wrap>
            <button class="btn btn-primary" type="submit" disabled data-pr-run-ocr>Create draft & run OCR</button>
            <span class="text-xs font-semibold text-slate-500" data-pr-run-ocr-help>Choose a supplier quotation file to enable OCR.</span>
        </div>
        <label class="form-label">Source note
            <textarea class="form-input min-h-20" name="source_note" placeholder="Required only for quote exception. Example: emergency purchase approved before supplier quote was received."></textarea>
            <span class="mt-1 block text-xs font-semibold text-slate-500">For normal supplier quote upload, this note is optional.</span>
        </label>
    `;

    if (insertBefore) {
        form.insertBefore(panel, insertBefore);
    } else {
        form.prepend(panel);
    }

    const uploadWrap = panel.querySelector('[data-pr-quote-upload-wrap]');
    const uploadInput = panel.querySelector('[data-pr-quote-upload]');
    const runOcrWrap = panel.querySelector('[data-pr-run-ocr-wrap]');
    const runOcrButton = panel.querySelector('[data-pr-run-ocr]');
    const runOcrHelp = panel.querySelector('[data-pr-run-ocr-help]');
    const heroSubmit = form.querySelector('.workspace-pane-header button[type="submit"]');
    const lineRows = () => Array.from(form.querySelectorAll('#line-items tbody tr'));

    function firstLineFields() {
        return {
            description: form.querySelector('[name="items[0][description]"]'),
            quantity: form.querySelector('[name="items[0][quantity]"]'),
            unit: form.querySelector('[name="items[0][unit]"]'),
            price: form.querySelector('[name="items[0][unit_price]"]'),
        };
    }

    function applyQuoteUploadPlaceholder() {
        const fields = firstLineFields();
        if (fields.description && fields.description.value.trim() === '') {
            fields.description.value = 'Supplier quote OCR pending verification';
        }
        if (fields.quantity && Number(fields.quantity.value || 0) <= 0) {
            fields.quantity.value = '1';
        }
        if (fields.unit && fields.unit.value.trim() === '') {
            fields.unit.value = 'lot';
        }
        if (fields.price && fields.price.value.trim() === '') {
            fields.price.value = '0';
        }
    }

    function setManualLineRequirement(required) {
        lineRows().forEach((row, index) => {
            row.querySelectorAll('input, select, textarea').forEach((field) => {
                if (field.name?.endsWith('[description]')) {
                    field.required = required;
                }
                if (field.name?.endsWith('[quantity]')) {
                    field.min = required ? '0.001' : '0';
                }
            });

            if (!required && index > 0) {
                row.querySelectorAll('input, select, textarea').forEach((field) => {
                    if (!field.name?.endsWith('[product_id]')) {
                        field.disabled = true;
                    }
                });
            }
        });
    }

    function syncSourceMode() {
        const selected = panel.querySelector('[data-pr-source-type]:checked')?.value || 'supplier_quote';
        const isException = selected === 'quote_exception';
        const hasUpload = !!uploadInput.files?.length;

        uploadWrap.classList.toggle('hidden', isException);
        runOcrWrap.classList.toggle('hidden', isException);
        uploadInput.disabled = isException;
        runOcrButton.disabled = isException || !hasUpload;

        if (heroSubmit) {
            heroSubmit.textContent = isException ? 'Save quote exception PR' : (hasUpload ? 'Create draft & run OCR' : 'Save manual draft');
        }

        if (runOcrHelp) {
            runOcrHelp.textContent = hasUpload
                ? 'This will create a draft PR, attach the quotation, and start OCR.'
                : 'Choose a supplier quotation file to enable OCR.';
        }

        if (!isException && hasUpload) {
            applyQuoteUploadPlaceholder();
            setManualLineRequirement(false);
        } else {
            setManualLineRequirement(true);
        }
    }

    panel.addEventListener('change', (event) => {
        if (event.target.matches('[data-pr-source-type], [data-pr-quote-upload]')) {
            syncSourceMode();
        }
    });

    form.addEventListener('submit', () => {
        if (!uploadInput.files?.length) return;
        applyQuoteUploadPlaceholder();
        setManualLineRequirement(false);
    }, true);

    syncSourceMode();
})();
</script>
@endif
</body>
</html>