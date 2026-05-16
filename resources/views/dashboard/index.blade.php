@extends('layouts.app', ['title' => 'Dashboard'])

@php
    $companyProfile = \App\Models\CompanyProfile::active();
    $agingTotal = collect($invoiceAging)->sum('value');
    $donutStops = ['primary' => '#2563eb', 'blue' => '#60a5fa', 'amber' => '#f59e0b', 'red' => '#ef4444'];
    $cursor = 0;
    $segments = [];
    foreach ($invoiceAging as $bucket) {
        $size = $agingTotal > 0 ? (($bucket['value'] / $agingTotal) * 100) : 0;
        if ($size > 0) {
            $segments[] = ($donutStops[$bucket['accent']] ?? '#64748b').' '.$cursor.'% '.($cursor + $size).'%';
            $cursor += $size;
        }
    }
    $donutBackground = $segments === [] ? '#e2e8f0 0% 100%' : implode(', ', $segments);
@endphp

@section('content')
<div class="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_19rem]">
    <div class="space-y-4">
        <section class="dashboard-hero">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="dashboard-hero-eyebrow">Suria QuoteFlow</p>
                    <h1>Operations dashboard</h1>
                    <p>
                        Company identity: <strong class="text-white">{{ $companyProfile->displayName() }}</strong>. Monitor quotations, approvals, purchase orders, invoices, payments, and procurement work from one operating view.
                    </p>
                </div>
                <div class="dashboard-hero-actions">
                    <a class="btn btn-primary" href="{{ route('documents.create', 'customer-quotations') }}">New quotation</a>
                    <a class="dashboard-hero-secondary" href="{{ route('documents.index', 'customer-quotations') }}">Review quotations</a>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($cards as $card)
                <div class="metric-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="metric-icon metric-icon-{{ $card['accent'] ?? 'primary' }}">
                            <x-icon :name="$card['icon'] ?? 'dashboard'" class="h-4 w-4" />
                        </div>
                        <span class="metric-foot">{{ $card['trend'] }}</span>
                    </div>
                    <p class="metric-label mt-4">{{ $card['label'] }}</p>
                    <p class="metric-value">
                        @if($card['plain'] ?? false)
                            {{ number_format($card['value']) }}
                        @else
                            RM {{ number_format($card['value'], 2) }}
                        @endif
                    </p>
                    <p class="mt-4 text-xs font-semibold leading-5 text-slate-500">{{ $card['note'] }}</p>
                </div>
            @endforeach
        </section>

        <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="workflow-card workflow-card-outgoing">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Outgoing Workflow</h2>
                        <p class="panel-subtitle">Inquiry to payment collection</p>
                    </div>
                    <a class="link text-sm" href="{{ route('documents.index', 'customer-quotations') }}">View outgoing</a>
                </div>
                <div class="workflow-track">
                    @foreach($workflow['outgoing'] as $step)
                        <a class="workflow-node" href="{{ $step['route'] }}">
                            <span class="workflow-node-count workflow-node-primary">{{ number_format($step['count']) }}</span>
                            <span class="workflow-node-label">{{ $step['label'] }}</span>
                        </a>
                        @unless($loop->last)
                            <span class="workflow-arrow"><x-icon name="arrow" class="h-4 w-4" /></span>
                        @endunless
                    @endforeach
                </div>
                <a class="workflow-action-band" href="{{ route('documents.index', 'customer-quotations') }}">View all outgoing documents <x-icon name="arrow" class="h-4 w-4" /></a>
            </div>

            <div class="workflow-card workflow-card-incoming">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Incoming Workflow</h2>
                        <p class="panel-subtitle">Request to supplier payment</p>
                    </div>
                    <a class="link text-sm" href="{{ route('documents.index', 'purchase-requests') }}">View incoming</a>
                </div>
                <div class="workflow-track">
                    @foreach($workflow['incoming'] as $step)
                        <a class="workflow-node" href="{{ $step['route'] }}">
                            <span class="workflow-node-count workflow-node-blue">{{ number_format($step['count']) }}</span>
                            <span class="workflow-node-label">{{ $step['label'] }}</span>
                        </a>
                        @unless($loop->last)
                            <span class="workflow-arrow"><x-icon name="arrow" class="h-4 w-4" /></span>
                        @endunless
                    @endforeach
                </div>
                <a class="workflow-action-band workflow-action-band-blue" href="{{ route('documents.index', 'purchase-requests') }}">View all incoming documents <x-icon name="arrow" class="h-4 w-4" /></a>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Pending Approvals</h2>
                        <p class="panel-subtitle">Manager actions waiting now</p>
                    </div>
                    <a class="link text-sm" href="{{ route('documents.index', ['module' => 'customer-quotations', 'status' => 'pending_approval']) }}">Review</a>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                        <tr><th>Document</th><th>Party</th><th class="text-right">Total</th></tr>
                        </thead>
                        <tbody>
                        @forelse($pendingApprovals->take(5) as $approval)
                            <tr>
                                <td>
                                    <a class="link" href="{{ route('documents.show', $approval->document) }}">{{ $approval->document->document_number }}</a>
                                    <div class="mt-1"><span class="status-chip status-pending_approval">Pending</span></div>
                                </td>
                                <td>{{ $approval->document->partyName() }}</td>
                                <td class="text-right font-bold text-slate-900">{{ $approval->document->currency }} {{ number_format($approval->document->total, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty-cell">No approvals waiting.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <div>
                        <h2 class="panel-title">Payment Summary</h2>
                        <p class="panel-subtitle">Open invoice aging by due date</p>
                    </div>
                    <a class="link text-sm" href="{{ route('reports.index') }}">Open report</a>
                </div>
                <div class="grid gap-5 sm:grid-cols-[11rem_1fr] sm:items-center">
                    <div class="donut" style="background: conic-gradient({{ $donutBackground }});">
                        <div>
                            <span>Total due</span>
                            <strong>RM {{ number_format($agingTotal, 2) }}</strong>
                        </div>
                    </div>
                    <div class="space-y-3">
                        @foreach($invoiceAging as $bucket)
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="flex items-center gap-2 font-semibold text-slate-600"><span class="legend-dot legend-{{ $bucket['accent'] }}"></span>{{ $bucket['label'] }}</span>
                                <span class="font-bold text-slate-900">RM {{ number_format($bucket['value'], 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Recent Documents</h2>
                    <p class="panel-subtitle">Latest commercial records across both workflows</p>
                </div>
                <a class="btn btn-secondary" href="{{ route('documents.index', 'customer-quotations') }}">View all</a>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr><th>Number</th><th>Type</th><th>Party</th><th>Status</th><th>Issued</th><th class="text-right">Total</th></tr>
                    </thead>
                    <tbody>
                    @forelse($recentDocuments as $document)
                        <tr>
                            <td><a class="link" href="{{ route('documents.show', $document) }}">{{ $document->document_number }}</a></td>
                            <td>{{ collect(\App\Models\Document::TYPES)->firstWhere('type', $document->type)['singular'] ?? ucwords(str_replace('_', ' ', $document->type)) }}</td>
                            <td>{{ $document->partyName() }}</td>
                            <td><span class="status-chip status-{{ $document->status }}">{{ $document->statusDisplay() }}</span></td>
                            <td>{{ optional($document->issue_date)->format('Y-m-d') }}</td>
                            <td class="text-right font-bold text-slate-900">{{ $document->currency }} {{ number_format($document->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-cell">No documents yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <aside class="space-y-4" aria-label="Dashboard actions and summaries">
        <section class="action-rail">
            <h2 class="panel-title">Quick Actions</h2>
            <div class="mt-4 space-y-2">
                @foreach($quickActions as $action)
                    <a class="quick-action-link" href="{{ $action['route'] }}">
                        <x-icon :name="$action['icon']" class="nav-icon" />
                        <span>{{ $action['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="action-rail">
            <h2 class="panel-title">Approval Overview</h2>
            <div class="mt-4 grid grid-cols-3 divide-x divide-slate-200 rounded-lg border border-slate-200 bg-slate-50 text-center">
                <div class="p-3">
                    <p class="text-xl font-black text-amber-600">{{ number_format($approvalStats['pending']) }}</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Pending</p>
                </div>
                <div class="p-3">
                    <p class="text-xl font-black text-red-600">{{ number_format($approvalStats['rejected']) }}</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Rejected</p>
                </div>
                <div class="p-3">
                    <p class="text-xl font-black text-emerald-600">{{ number_format($approvalStats['approved']) }}</p>
                    <p class="mt-1 text-xs font-semibold text-slate-500">Approved</p>
                </div>
            </div>
            <a class="quick-action-link mt-4" href="{{ route('audit.index') }}"><x-icon name="admin" class="nav-icon" /><span>View audit trail</span></a>
        </section>

        <section class="action-rail">
            <h2 class="panel-title">Top Customers</h2>
            <div class="mt-4 space-y-3">
                @forelse($topCustomers as $row)
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span class="font-semibold text-slate-600">{{ $row->customer?->name ?? 'Customer' }}</span>
                        <span class="font-bold text-slate-950">RM {{ number_format($row->total, 2) }}</span>
                    </div>
                @empty
                    <div class="soft-band text-sm font-medium text-slate-500">Customer invoice totals will appear here once invoices are issued.</div>
                @endforelse
            </div>
        </section>
    </aside>
</div>
@endsection
