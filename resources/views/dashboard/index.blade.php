@extends('layouts.app', [
    'title' => 'Dashboard',
    'contentMode' => 'dashboard',
    'showDateControl' => false,
    'showApprovalShortcut' => false,
])

@php
    $companyProfile = \App\Models\CompanyProfile::active();
    $salesPurchasingGroups = [
        [
            'key' => 'sales',
            'title' => 'Customer sales',
            'note' => 'Quotation to customer payment',
            'route' => route('documents.index', 'customer-quotations'),
            'action' => 'Open sales records',
        ],
        [
            'key' => 'purchasing',
            'title' => 'Supplier purchasing',
            'note' => 'Request to supplier payment',
            'route' => route('documents.index', 'purchase-requests'),
            'action' => 'Open purchasing records',
        ],
    ];
@endphp

@section('content')
<section class="dashboard-screen" aria-labelledby="dashboard-title">
    <header class="dashboard-titlebar">
        <div class="min-w-0">
            <h1 id="dashboard-title">Operations today</h1>
            <p>{{ $companyProfile->displayName() }}: work queue, cash exposure, and sales to purchasing movement.</p>
        </div>
    </header>

    <div class="dashboard-command-grid">
        <div class="dashboard-main-stack">
            <section class="dashboard-command-panel dashboard-needs-attention" aria-labelledby="dashboard-needs-heading">
                <div class="dashboard-panel-heading">
                    <div>
                        <h2 id="dashboard-needs-heading">Needs attention</h2>
                        <p>Open work by urgency. Each card opens the records behind it.</p>
                    </div>
                </div>

                <div class="needs-attention-card-grid">
                    @foreach($todayWork as $work)
                        <a class="needs-attention-card needs-attention-{{ $work['tone'] }}" href="{{ $work['route'] }}">
                            <span class="needs-attention-marker" aria-hidden="true"></span>
                            <span class="needs-attention-card-top">
                                <strong class="needs-attention-count">{{ number_format($work['count']) }}</strong>
                                <span class="needs-attention-action">{{ $work['action'] }} <x-icon name="arrow" class="h-4 w-4" aria-hidden="true" /></span>
                            </span>
                            <span class="needs-attention-title">{{ $work['label'] }}</span>
                            <span class="needs-attention-note">{{ $work['note'] }}</span>
                            <span class="needs-attention-meter" aria-hidden="true">
                                <span style="width: {{ $work['percent'] }}%"></span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="dashboard-command-panel dashboard-workflow-panel" aria-labelledby="sales-purchasing-heading">
                <div class="dashboard-panel-heading dashboard-panel-heading-row">
                    <div>
                        <h2 id="sales-purchasing-heading">Sales and purchasing</h2>
                        <p>Count by stage so bottlenecks are visible without opening reports.</p>
                    </div>
                </div>

                <div class="dashboard-workflow-grid">
                    @foreach($salesPurchasingGroups as $group)
                        <article class="dashboard-workflow-lane dashboard-workflow-{{ $group['key'] }}">
                            <div class="dashboard-workflow-lane-header">
                                <div>
                                    <h3>{{ $group['title'] }}</h3>
                                    <p>{{ $group['note'] }}</p>
                                </div>
                                <a class="dashboard-workflow-link" href="{{ $group['route'] }}">{{ $group['action'] }}</a>
                            </div>

                            <div class="dashboard-pipeline-chart">
                                @foreach($workflow[$group['key']] as $step)
                                    <a class="dashboard-pipeline-step" href="{{ $step['route'] }}">
                                        <span class="dashboard-pipeline-count">{{ number_format($step['count']) }}</span>
                                        <span class="dashboard-pipeline-copy">
                                            <span>{{ $step['label'] }}</span>
                                            <span class="dashboard-pipeline-bar" aria-hidden="true">
                                                <span style="width: {{ $step['percent'] }}%"></span>
                                            </span>
                                        </span>
                                    </a>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="dashboard-movement-chart" aria-labelledby="movement-chart-heading">
                    <div class="dashboard-movement-header">
                        <div>
                            <h3 id="movement-chart-heading">6-month movement</h3>
                            <p>Invoices issued and payments recorded.</p>
                        </div>
                        <a href="{{ route('reports.index') }}">Open reports</a>
                    </div>

                    <div class="dashboard-movement-grid" role="list">
                        @foreach($monthlyMovement as $month)
                            <div class="dashboard-movement-month" role="listitem">
                                <div class="dashboard-movement-bars" aria-label="{{ $month['label'] }} movement">
                                    <span class="movement-bar movement-customer" title="Customer invoices: RM {{ number_format($month['customer_invoices'], 2) }}" style="height: {{ $month['customer_invoices_percent'] }}%"></span>
                                    <span class="movement-bar movement-supplier" title="Supplier invoices: RM {{ number_format($month['supplier_invoices'], 2) }}" style="height: {{ $month['supplier_invoices_percent'] }}%"></span>
                                    <span class="movement-bar movement-incoming" title="Incoming payments: RM {{ number_format($month['incoming_payments'], 2) }}" style="height: {{ $month['incoming_payments_percent'] }}%"></span>
                                    <span class="movement-bar movement-outgoing" title="Outgoing payments: RM {{ number_format($month['outgoing_payments'], 2) }}" style="height: {{ $month['outgoing_payments_percent'] }}%"></span>
                                </div>
                                <span>{{ $month['label'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="dashboard-movement-legend" aria-label="Movement chart legend">
                        <span><i class="movement-customer"></i> Customer invoices</span>
                        <span><i class="movement-supplier"></i> Supplier invoices</span>
                        <span><i class="movement-incoming"></i> Incoming paid</span>
                        <span><i class="movement-outgoing"></i> Outgoing paid</span>
                    </div>
                </div>
            </section>
        </div>

        <aside class="dashboard-side-stack" aria-label="Dashboard charts and shortcuts">
            <section class="dashboard-command-panel dashboard-quick-create-panel" aria-labelledby="quick-create-heading">
                <div class="dashboard-panel-heading">
                    <div>
                        <h2 id="quick-create-heading">Quick create</h2>
                        <p>Start a new document without leaving the dashboard.</p>
                    </div>
                </div>
                <div class="quick-create-list">
                    @foreach($quickActions as $action)
                        <a class="quick-action-link" href="{{ $action['route'] }}" aria-label="{{ $action['aria'] ?? $action['label'] }}">
                            <x-icon :name="$action['icon']" class="nav-icon" aria-hidden="true" />
                            <span>{{ $action['label'] }}</span>
                            <x-icon name="arrow" class="h-4 w-4" aria-hidden="true" />
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="dashboard-command-panel dashboard-financial-panel" aria-labelledby="financial-exposure-heading">
                <div class="dashboard-panel-heading">
                    <div>
                        <h2 id="financial-exposure-heading">Financial exposure</h2>
                        <p>Open money by type and invoice age.</p>
                    </div>
                </div>
                <div class="financial-chart-stack">
                    <div class="financial-exposure-list">
                        @foreach($financialExposure as $item)
                            <a class="financial-exposure-row financial-exposure-{{ $item['tone'] }}" href="{{ $item['route'] }}">
                                <span class="financial-exposure-icon"><x-icon :name="$item['icon']" class="h-4 w-4" aria-hidden="true" /></span>
                                <span class="financial-exposure-copy">
                                    <span class="financial-exposure-main">
                                        <span class="financial-exposure-label">{{ $item['label'] }}</span>
                                        <strong>RM {{ number_format($item['value'], 2) }}</strong>
                                    </span>
                                    <span class="financial-exposure-note">{{ $item['note'] }}</span>
                                    <span class="financial-exposure-bar" aria-hidden="true">
                                        <span style="width: {{ $item['percent'] }}%"></span>
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>

                    <div class="invoice-aging-chart" aria-labelledby="invoice-aging-heading">
                        <h3 id="invoice-aging-heading">Invoice aging</h3>
                        <div class="invoice-aging-bars">
                            @foreach($invoiceAging as $bucket)
                                <div class="invoice-aging-row invoice-aging-{{ $bucket['accent'] }}">
                                    <span class="invoice-aging-label">{{ $bucket['label'] }}</span>
                                    <span class="invoice-aging-bar" aria-hidden="true">
                                        <span style="width: {{ $bucket['percent'] }}%"></span>
                                    </span>
                                    <strong>RM {{ number_format($bucket['value'], 2) }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        </aside>
    </div>
</section>
@endsection
