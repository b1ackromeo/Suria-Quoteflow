@extends('layouts.app', [
    'title' => $project->project_code,
    'contentMode' => 'fullscreen',
])

@php
    $companyProfile = \App\Models\CompanyProfile::active();
    $money = fn ($value) => $companyProfile->formatMoney($value);
    $date = fn ($value) => $value ? $companyProfile->formatDate($value) : 'Not set';
    $percent = fn ($value) => $value === null ? 'Not available' : rtrim(rtrim(number_format((float) $value, 2), '0'), '.').'%';
    $marginTarget = rtrim(rtrim(number_format((float) $project->margin_target_percent, 2), '0'), '.');
    $billingProgress = $billingProgress ?? [
        'customer' => [],
        'supplier' => [],
        'recent_invoices' => [],
    ];
    $retentionSummary = $retentionSummary ?? [
        'customer_retention_held' => 0,
        'supplier_retention_held' => 0,
        'net_retention_exposure' => 0,
        'customer_release_date' => null,
        'supplier_release_date' => null,
        'document_count' => 0,
    ];
    $variationSummary = $variationSummary ?? [
        'original_contract_value' => (float) $project->contract_value,
        'approved_customer_change' => 0,
        'pending_customer_change' => 0,
        'revised_contract_value' => (float) $project->contract_value,
        'original_budget_amount' => (float) $project->budget_amount,
        'approved_supplier_cost_change' => 0,
        'pending_supplier_cost_change' => 0,
        'revised_budget_amount' => (float) $project->budget_amount,
        'approved_margin_baseline' => (float) $project->contract_value - (float) $project->budget_amount,
        'approved_margin_baseline_percent' => null,
        'approved_count' => 0,
        'pending_count' => 0,
        'not_proceeding_count' => 0,
        'total_count' => 0,
        'items' => [],
    ];
    $customerBilling = $billingProgress['customer'] ?? [];
    $supplierBilling = $billingProgress['supplier'] ?? [];
    $recentBillingInvoices = $billingProgress['recent_invoices'] ?? [];
    $deliveryBilling = $billingProgress['delivery'] ?? [
        'customer' => [],
        'supplier' => [],
        'work_items' => [],
        'recent_documents' => [],
    ];
    $deliveryCustomer = $deliveryBilling['customer'] ?? [];
    $deliverySupplier = $deliveryBilling['supplier'] ?? [];
    $deliveryWorkItems = $deliveryBilling['work_items'] ?? [];
    $deliveryEvidence = collect($deliveryBilling['evidence'] ?? [])
        ->filter(fn ($issue) => (int) ($issue['line_count'] ?? 0) > 0)
        ->values();
    $recentDeliveryDocuments = $deliveryBilling['recent_documents'] ?? [];
    $variationOrders = $variationSummary['items'] ?? [];
    $projectExceptions = $projectExceptions ?? [];
    $projectEvidence = $projectEvidence ?? [];
    $sourceDocuments = $sourceDocuments ?? collect();
    $unassignedEvidence = $projectEvidence['unassigned_lines'] ?? ['line_count' => 0, 'showing_count' => 0, 'is_truncated' => false, 'items' => []];
    $overCommittedEvidence = $projectEvidence['over_committed_work_items'] ?? [];
    $overActualEvidence = $projectEvidence['over_actual_work_items'] ?? [];
    $supplierActualEvidence = $projectEvidence['supplier_actual_lines'] ?? ['line_count' => 0, 'showing_count' => 0, 'is_truncated' => false, 'items' => []];
    $sourceDocumentLabel = function ($document) {
        if (! $document) {
            return null;
        }

        $slug = \App\Models\Document::slugForType($document->type);
        $meta = \App\Models\Document::metaForSlug($slug);

        return $meta['singular'] ?? 'Document';
    };
    $variationError = collect([
        'variation_number',
        'title',
        'status',
        'effective_date',
        'customer_value',
        'supplier_cost',
        'source_document_id',
        'notes',
    ])->map(fn ($field) => $errors->first($field))->filter()->first();
    $evidenceSectionCount = collect([
        ($unassignedEvidence['line_count'] ?? 0) > 0,
        count($overCommittedEvidence) > 0,
        count($overActualEvidence) > 0,
        ($supplierActualEvidence['line_count'] ?? 0) > 0,
    ])->filter()->count();
@endphp

@section('content')
<div class="fullscreen-workspace directory-workspace">
    <section class="directory-page-header">
        <div class="min-w-0">
            <p class="document-pane-kicker">Project control</p>
            <h1 class="document-pane-title">{{ $project->name }}</h1>
            <p class="mt-1 max-w-3xl text-sm font-semibold leading-6 text-slate-500">
                {{ $project->project_code }} · {{ $project->customer?->name ?? 'No customer assigned' }}
            </p>
        </div>
        <div class="directory-header-actions">
            <span class="status-chip {{ $project->statusChipClass() }}">{{ $project->statusDisplay() }}</span>
            <a class="btn btn-secondary" href="{{ route('projects.report.export', $project) }}">Export report CSV</a>
            <a class="btn btn-secondary" href="{{ route('projects.index') }}">Back</a>
            @if($canManageProject)
                <a class="btn btn-primary" href="{{ route('projects.edit', $project) }}">Edit project</a>
            @endif
        </div>
    </section>

    <div class="directory-body">
        <aside class="directory-side-panel">
            <section class="directory-filter-card">
                <p class="document-pane-kicker">Project overview</p>
                <dl class="document-detail-list">
                    <div>
                        <dt>Customer</dt>
                        <dd>{{ $project->customer?->name ?? 'Not assigned' }}</dd>
                    </div>
                    <div>
                        <dt>Project manager</dt>
                        <dd>{{ $project->manager?->name ?? 'Not assigned' }}</dd>
                    </div>
                    <div>
                        <dt>Start date</dt>
                        <dd>{{ $date($project->start_date) }}</dd>
                    </div>
                    <div>
                        <dt>Expected completion</dt>
                        <dd>{{ $date($project->expected_completion_date) }}</dd>
                    </div>
                    <div>
                        <dt>Margin target</dt>
                        <dd>{{ $marginTarget }}%</dd>
                    </div>
                </dl>
                @if($project->description)
                    <div class="document-side-notes">
                        <div>
                            <strong>Project summary</strong>
                            <p>{{ $project->description }}</p>
                        </div>
                    </div>
                @endif
            </section>

            <div class="directory-stat-grid">
                <div class="directory-stat-card">
                    <span>Original contract value</span>
                    <strong>{{ $money($variationSummary['original_contract_value'] ?? $project->contract_value) }}</strong>
                </div>
                <div class="directory-stat-card">
                    <span>Original budget amount</span>
                    <strong>{{ $money($variationSummary['original_budget_amount'] ?? $project->budget_amount) }}</strong>
                </div>
                <div class="directory-stat-card">
                    <span>Budget remaining</span>
                    <strong>{{ $money($summary['budget_remaining']) }}</strong>
                </div>
            </div>
        </aside>

        <section class="directory-table-pane">
            <div class="directory-table-header">
                <div>
                    <h2 class="panel-title">Commercial summary</h2>
                    <p class="panel-subtitle">Totals are calculated from documents linked to this project.</p>
                </div>
                <span class="issuer-mini">{{ $summary['document_count'] }} linked document{{ $summary['document_count'] === 1 ? '' : 's' }}</span>
            </div>

            <div class="directory-table-scroll space-y-5">
                <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <div class="directory-stat-card">
                        <span>Quoted revenue</span>
                        <strong>{{ $money($summary['quoted_revenue']) }}</strong>
                    </div>
                    <div class="directory-stat-card">
                        <span>Customer confirmed</span>
                        <strong>{{ $money($summary['customer_confirmed']) }}</strong>
                    </div>
                    <div class="directory-stat-card">
                        <span>Customer invoiced</span>
                        <strong>{{ $money($summary['customer_invoiced']) }}</strong>
                    </div>
                    <div class="directory-stat-card">
                        <span>Customer paid</span>
                        <strong>{{ $money($summary['customer_paid']) }}</strong>
                    </div>
                    <div class="directory-stat-card">
                        <span>Estimated cost</span>
                        <strong>{{ $money($summary['estimated_cost']) }}</strong>
                    </div>
                    <div class="directory-stat-card">
                        <span>Supplier committed</span>
                        <strong>{{ $money($summary['supplier_committed']) }}</strong>
                    </div>
                    <div class="directory-stat-card">
                        <span>Received / accepted</span>
                        <strong>{{ $money($summary['received_cost']) }}</strong>
                    </div>
                    <div class="directory-stat-card">
                        <span>Supplier invoiced</span>
                        <strong>{{ $money($summary['supplier_invoiced']) }}</strong>
                    </div>
                    <div class="directory-stat-card">
                        <span>Supplier paid</span>
                        <strong>{{ $money($summary['supplier_paid']) }}</strong>
                    </div>
                    <div class="directory-stat-card">
                        <span>Expected margin</span>
                        <strong>{{ $money($summary['expected_margin']) }}</strong>
                    </div>
                    <div class="directory-stat-card">
                        <span>Actual margin</span>
                        <strong>{{ $money($summary['actual_margin']) }}</strong>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="directory-table-header border-0 p-0">
                        <div>
                            <h2 class="panel-title">Budget and margin</h2>
                            <p class="panel-subtitle">Commercial position based on customer confirmations, supplier commitments, invoices, and payments.</p>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <div class="directory-stat-card">
                            <span>Expected margin</span>
                            <strong>{{ $percent($summary['expected_margin_percent']) }}</strong>
                        </div>
                        <div class="directory-stat-card">
                            <span>Actual margin</span>
                            <strong>{{ $percent($summary['actual_margin_percent']) }}</strong>
                        </div>
                        <div class="directory-stat-card">
                            <span>Unbilled revenue</span>
                            <strong>{{ $money($summary['unbilled_revenue']) }}</strong>
                        </div>
                        <div class="directory-stat-card">
                            <span>Unpaid supplier cost</span>
                            <strong>{{ $money($summary['unpaid_supplier_cost']) }}</strong>
                        </div>
                        <div class="directory-stat-card">
                            <span>Budget remaining</span>
                            <strong>{{ $money($summary['budget_remaining']) }}</strong>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="directory-table-header border-0 p-4">
                        <div>
                            <h2 class="panel-title">Billing progress</h2>
                            <p class="panel-subtitle">Milestone and progress billing totals are grouped from customer and supplier documents linked to this project.</p>
                        </div>
                        <span class="issuer-mini">{{ count($recentBillingInvoices) }} recent invoice{{ count($recentBillingInvoices) === 1 ? '' : 's' }}</span>
                    </div>

                    <div class="grid gap-4 border-t border-slate-200 p-4 xl:grid-cols-2">
                        @foreach([
                            'Customer billing' => $customerBilling,
                            'Supplier billing' => $supplierBilling,
                        ] as $label => $billing)
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <h3 class="text-sm font-bold text-slate-950">{{ $label }}</h3>
                                        <p class="mt-1 text-sm font-medium leading-6 text-slate-500">Roll up planned milestone value, current invoicing, and the latest recorded billing stage.</p>
                                    </div>
                                    <span class="issuer-mini">{{ (int) ($billing['schedule_document_count'] ?? 0) }} schedule{{ (int) ($billing['schedule_document_count'] ?? 0) === 1 ? '' : 's' }}</span>
                                </div>

                                <dl class="document-detail-list mt-4">
                                    <div>
                                        <dt>Planned stages</dt>
                                        <dd>{{ (int) ($billing['planned_stage_count'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Progress invoices</dt>
                                        <dd>{{ (int) ($billing['progress_invoice_count'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Scheduled value</dt>
                                        <dd>{{ $money($billing['scheduled_value'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Invoiced value</dt>
                                        <dd>{{ $money($billing['invoiced_value'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Remaining staged value</dt>
                                        <dd>{{ $money($billing['remaining_value'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Above scheduled value</dt>
                                        <dd>{{ $money($billing['over_billed_value'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Latest progress</dt>
                                        <dd>{{ $billing['latest_progress_label'] ?? 'Not billed yet' }}</dd>
                                    </div>
                                    <div>
                                        <dt>Latest billing stage</dt>
                                        <dd>{{ $billing['latest_stage_name'] ?? 'Not billed yet' }}</dd>
                                    </div>
                                </dl>

                                @if(! empty($billing['latest_invoice_id']))
                                    <div class="mt-4 text-right">
                                        <a class="btn btn-secondary" href="{{ route('documents.show', $billing['latest_invoice_id']) }}">Open latest invoice</a>
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>

                    <div class="border-t border-slate-200 p-4">
                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-950">Recent staged invoices</h3>
                                <p class="mt-1 text-sm font-medium leading-6 text-slate-500">Use these latest milestone invoices to trace the billing stage currently recorded on the project.</p>
                            </div>
                            <span class="issuer-mini">{{ count($recentBillingInvoices) }} row{{ count($recentBillingInvoices) === 1 ? '' : 's' }}</span>
                        </div>

                        @if($recentBillingInvoices !== [])
                            <div class="document-compact-table mt-4">
                                <table>
                                    <thead>
                                    <tr>
                                        <th>Document</th>
                                        <th>Party</th>
                                        <th>Issue date</th>
                                        <th>Progress invoice</th>
                                        <th>Billing stage</th>
                                        <th class="text-right">Amount</th>
                                        <th></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($recentBillingInvoices as $invoice)
                                        <tr>
                                            <td data-label="Document">
                                                <strong>{{ $invoice['document_number'] }}</strong>
                                                <span>{{ $invoice['document_label'] }}</span>
                                            </td>
                                            <td data-label="Party">{{ $invoice['party_name'] }}</td>
                                            <td data-label="Issue date">{{ $date($invoice['issue_date']) }}</td>
                                            <td data-label="Progress invoice">{{ $invoice['progress_label'] ?? 'Not set' }}</td>
                                            <td data-label="Billing stage">{{ $invoice['billing_stage_name'] ?? 'Not set' }}</td>
                                            <td data-label="Amount" class="text-right font-bold text-slate-950">{{ $money($invoice['total']) }}</td>
                                            <td data-label="Action" class="text-right">
                                                <a class="btn btn-secondary" href="{{ route('documents.show', $invoice['document_id']) }}">Open</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <article class="document-readiness-item readiness-state-ready mt-4">
                                <strong>No staged invoices recorded</strong>
                                <p>No milestone customer or supplier invoices are linked to this project yet.</p>
                            </article>
                        @endif
                    </div>

                    <div class="border-t border-slate-200 p-4">
                        <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-950">Partial delivery billing</h3>
                                <p class="mt-1 text-sm font-medium leading-6 text-slate-500">Compare customer billing, purchase orders, goods receipts, and supplier invoices for partially delivered project work.</p>
                            </div>
                            <span class="issuer-mini">{{ count($deliveryWorkItems) }} work item{{ count($deliveryWorkItems) === 1 ? '' : 's' }}</span>
                        </div>

                        <div class="mt-4 grid gap-4 xl:grid-cols-2">
                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <h4 class="text-sm font-bold text-slate-950">Customer delivery billing</h4>
                                <dl class="document-detail-list mt-4">
                                    <div>
                                        <dt>Customer confirmed</dt>
                                        <dd>{{ $money($deliveryCustomer['confirmed_value'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Customer invoiced</dt>
                                        <dd>{{ $money($deliveryCustomer['invoiced_value'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Customer unbilled</dt>
                                        <dd>{{ $money($deliveryCustomer['unbilled_value'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Above confirmed value</dt>
                                        <dd>{{ $money($deliveryCustomer['above_confirmed_value'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Invoiced percentage</dt>
                                        <dd>{{ $percent($deliveryCustomer['invoiced_percent'] ?? null) }}</dd>
                                    </div>
                                </dl>
                            </article>

                            <article class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                <h4 class="text-sm font-bold text-slate-950">Supplier delivery and invoices</h4>
                                <dl class="document-detail-list mt-4">
                                    <div>
                                        <dt>Purchase order committed</dt>
                                        <dd>{{ $money($deliverySupplier['committed_value'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Received / accepted</dt>
                                        <dd>{{ $money($deliverySupplier['received_value'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Supplier invoiced</dt>
                                        <dd>{{ $money($deliverySupplier['invoiced_value'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Not yet received</dt>
                                        <dd>{{ $money($deliverySupplier['not_yet_received_value'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Received not invoiced</dt>
                                        <dd>{{ $money($deliverySupplier['received_not_invoiced_value'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Above received value</dt>
                                        <dd>{{ $money($deliverySupplier['above_received_value'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Received percentage</dt>
                                        <dd>{{ $percent($deliverySupplier['received_percent'] ?? null) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Supplier invoiced percentage</dt>
                                        <dd>{{ $percent($deliverySupplier['invoiced_percent'] ?? null) }}</dd>
                                    </div>
                                </dl>
                            </article>
                        </div>

                        @if($deliveryWorkItems !== [])
                            <div class="document-compact-table mt-4">
                                <table>
                                    <thead>
                                    <tr>
                                        <th>Work item / cost code</th>
                                        <th class="text-right">Customer unbilled</th>
                                        <th class="text-right">Not yet received</th>
                                        <th class="text-right">Received not invoiced</th>
                                        <th class="text-right">Above confirmed</th>
                                        <th class="text-right">Above received</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($deliveryWorkItems as $item)
                                        <tr>
                                            <td data-label="Work item / cost code">
                                                <strong>{{ $item['work_item_label'] }}</strong>
                                                <span>{{ $item['line_count'] }} project line{{ (int) $item['line_count'] === 1 ? '' : 's' }}</span>
                                            </td>
                                            <td data-label="Customer unbilled" class="text-right font-bold text-slate-950">{{ $money($item['customer_unbilled_value']) }}</td>
                                            <td data-label="Not yet received" class="text-right font-bold text-slate-950">{{ $money($item['supplier_not_yet_received_value']) }}</td>
                                            <td data-label="Received not invoiced" class="text-right font-bold text-slate-950">{{ $money($item['received_not_invoiced_value']) }}</td>
                                            <td data-label="Above confirmed" class="text-right font-bold text-slate-950">{{ $money($item['customer_above_confirmed_value']) }}</td>
                                            <td data-label="Above received" class="text-right font-bold text-slate-950">{{ $money($item['supplier_above_received_value']) }}</td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <article class="document-readiness-item readiness-state-ready mt-4">
                                <strong>No partial delivery billing yet</strong>
                                <p>No customer PO received, customer invoice, purchase order, goods receipt, or supplier invoice lines are linked to project work items yet.</p>
                            </article>
                        @endif

                        @if($deliveryEvidence->isNotEmpty())
                            <div class="mt-4">
                                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-950">Delivery billing evidence</h4>
                                        <p class="mt-1 text-sm font-medium leading-6 text-slate-500">Open the document lines behind each delivery billing gap before deciding the next action.</p>
                                    </div>
                                    <span class="issuer-mini">{{ $deliveryEvidence->count() }} issue{{ $deliveryEvidence->count() === 1 ? '' : 's' }}</span>
                                </div>

                                <div class="mt-4 space-y-4">
                                    @foreach($deliveryEvidence as $issue)
                                        <article class="rounded-lg border border-slate-200 bg-white p-4">
                                            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                                <div>
                                                    <h5 class="text-sm font-bold text-slate-950">{{ $issue['title'] }}</h5>
                                                    <p class="mt-1 text-sm font-medium leading-6 text-slate-600">{{ $issue['message'] }}</p>
                                                </div>
                                                <span class="issuer-mini">{{ $money($issue['amount'] ?? 0) }}</span>
                                            </div>

                                            <div class="document-compact-table mt-4">
                                                <table>
                                                    <thead>
                                                    <tr>
                                                        <th>Document</th>
                                                        <th>Party</th>
                                                        <th>Issue date</th>
                                                        <th>Line description</th>
                                                        <th>Work item</th>
                                                        <th class="text-right">Amount</th>
                                                        <th></th>
                                                    </tr>
                                                    </thead>
                                                    <tbody>
                                                    @foreach($issue['items'] as $item)
                                                        <tr>
                                                            <td data-label="Document">
                                                                <strong>{{ $item['document_number'] }}</strong>
                                                                <span>{{ $item['document_label'] }}</span>
                                                            </td>
                                                            <td data-label="Party">{{ $item['party_name'] }}</td>
                                                            <td data-label="Issue date">{{ $date($item['issue_date']) }}</td>
                                                            <td data-label="Line description">{{ $item['description'] }}</td>
                                                            <td data-label="Work item">{{ $item['work_item_label'] ?? 'Not assigned' }}</td>
                                                            <td data-label="Amount" class="text-right font-bold text-slate-950">{{ $money($item['line_total']) }}</td>
                                                            <td data-label="Action" class="text-right">
                                                                <a class="btn btn-secondary" href="{{ route('documents.show', $item['document_id']) }}">Open</a>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                    </tbody>
                                                </table>
                                            </div>

                                            @if($issue['is_truncated'] ?? false)
                                                <p class="mt-3 text-xs font-semibold text-slate-500">Showing first {{ $issue['showing_count'] }} of {{ $issue['line_count'] }} lines on this page.</p>
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="mt-4">
                            <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <h4 class="text-sm font-bold text-slate-950">Recent delivery documents</h4>
                                    <p class="mt-1 text-sm font-medium leading-6 text-slate-500">Review the documents used in the delivery billing totals.</p>
                                </div>
                                <span class="issuer-mini">{{ count($recentDeliveryDocuments) }} row{{ count($recentDeliveryDocuments) === 1 ? '' : 's' }}</span>
                            </div>

                            @if($recentDeliveryDocuments !== [])
                                <div class="document-compact-table mt-4">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th>Document</th>
                                            <th>Party</th>
                                            <th>Issue date</th>
                                            <th class="text-right">Amount</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($recentDeliveryDocuments as $document)
                                            <tr>
                                                <td data-label="Document">
                                                    <strong>{{ $document['document_number'] }}</strong>
                                                    <span>{{ $document['document_label'] }}</span>
                                                </td>
                                                <td data-label="Party">{{ $document['party_name'] }}</td>
                                                <td data-label="Issue date">{{ $date($document['issue_date']) }}</td>
                                                <td data-label="Amount" class="text-right font-bold text-slate-950">{{ $money($document['total']) }}</td>
                                                <td data-label="Action" class="text-right">
                                                    <a class="btn btn-secondary" href="{{ route('documents.show', $document['document_id']) }}">Open</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <article class="document-readiness-item readiness-state-ready mt-4">
                                    <strong>No delivery documents recorded</strong>
                                    <p>No customer PO received, customer invoice, purchase order, goods receipt, or supplier invoice is linked to this project yet.</p>
                                </article>
                            @endif
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="directory-table-header border-0 p-0">
                        <div>
                            <h2 class="panel-title">Retention</h2>
                            <p class="panel-subtitle">Track retention amounts already held on project invoices and the next recorded release dates.</p>
                        </div>
                        <span class="issuer-mini">{{ (int) ($retentionSummary['document_count'] ?? 0) }} document{{ (int) ($retentionSummary['document_count'] ?? 0) === 1 ? '' : 's' }}</span>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                        <div class="directory-stat-card">
                            <span>Customer retention held</span>
                            <strong>{{ $money($retentionSummary['customer_retention_held'] ?? 0) }}</strong>
                        </div>
                        <div class="directory-stat-card">
                            <span>Supplier retention held</span>
                            <strong>{{ $money($retentionSummary['supplier_retention_held'] ?? 0) }}</strong>
                        </div>
                        <div class="directory-stat-card">
                            <span>Net retention exposure</span>
                            <strong>{{ $money($retentionSummary['net_retention_exposure'] ?? 0) }}</strong>
                        </div>
                        <div class="directory-stat-card">
                            <span>Customer release date</span>
                            <strong>{{ $date($retentionSummary['customer_release_date'] ?? null) }}</strong>
                        </div>
                        <div class="directory-stat-card">
                            <span>Supplier release date</span>
                            <strong>{{ $date($retentionSummary['supplier_release_date'] ?? null) }}</strong>
                        </div>
                    </div>
                </section>

                <section class="table-wrap directory-table-wrap">
                    <div class="directory-table-header rounded-t-lg border-t-0">
                        <div>
                            <h2 class="panel-title">Variation orders</h2>
                            <p class="panel-subtitle">Track approved and pending scope changes without replacing the original project value baseline.</p>
                        </div>
                        <span class="issuer-mini">{{ (int) ($variationSummary['total_count'] ?? 0) }} variation order{{ (int) ($variationSummary['total_count'] ?? 0) === 1 ? '' : 's' }}</span>
                    </div>

                    <div class="space-y-4 p-4">
                        @if($variationError)
                            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-800">
                                {{ $variationError }}
                            </div>
                        @endif

                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            <div class="directory-stat-card">
                                <span>Approved customer change</span>
                                <strong>{{ $money($variationSummary['approved_customer_change'] ?? 0) }}</strong>
                            </div>
                            <div class="directory-stat-card">
                                <span>Pending customer change</span>
                                <strong>{{ $money($variationSummary['pending_customer_change'] ?? 0) }}</strong>
                            </div>
                            <div class="directory-stat-card">
                                <span>Approved supplier cost change</span>
                                <strong>{{ $money($variationSummary['approved_supplier_cost_change'] ?? 0) }}</strong>
                            </div>
                            <div class="directory-stat-card">
                                <span>Pending supplier cost change</span>
                                <strong>{{ $money($variationSummary['pending_supplier_cost_change'] ?? 0) }}</strong>
                            </div>
                            <div class="directory-stat-card">
                                <span>Revised contract value</span>
                                <strong>{{ $money($variationSummary['revised_contract_value'] ?? $project->contract_value) }}</strong>
                            </div>
                            <div class="directory-stat-card">
                                <span>Revised budget amount</span>
                                <strong>{{ $money($variationSummary['revised_budget_amount'] ?? $project->budget_amount) }}</strong>
                            </div>
                            <div class="directory-stat-card">
                                <span>Approved margin baseline</span>
                                <strong>{{ $money($variationSummary['approved_margin_baseline'] ?? 0) }}</strong>
                            </div>
                            <div class="directory-stat-card">
                                <span>Pending review</span>
                                <strong>{{ (int) ($variationSummary['pending_count'] ?? 0) }}</strong>
                            </div>
                        </div>

                        @if($canManageProject)
                            <form method="post" action="{{ route('projects.variations.store', $project) }}" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                @csrf
                                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                    <label class="form-label">Variation number
                                        <input class="form-input" name="variation_number" value="{{ old('variation_number') }}" placeholder="VO-2026-001" required>
                                    </label>
                                    <label class="form-label xl:col-span-2">Variation title
                                        <input class="form-input" name="title" value="{{ old('title') }}" placeholder="Additional switch cabinet scope" required>
                                    </label>
                                    <label class="form-label">Status
                                        <select class="form-input" name="status" required>
                                            @foreach(\App\Models\ProjectVariation::STATUSES as $value => $label)
                                                <option value="{{ $value }}" @selected(old('status', 'pending_review') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="form-label">Effective date
                                        <input class="form-input" type="date" name="effective_date" value="{{ old('effective_date') }}">
                                    </label>
                                    <label class="form-label xl:col-span-2">Linked document
                                        <select class="form-input" name="source_document_id">
                                            <option value="">No linked document</option>
                                            @foreach($sourceDocuments as $sourceDocument)
                                                <option value="{{ $sourceDocument->id }}" @selected((string) old('source_document_id') === (string) $sourceDocument->id)>
                                                    {{ $sourceDocument->document_number }} · {{ $sourceDocumentLabel($sourceDocument) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="form-label">Customer value change
                                        <input class="form-input" type="number" step="0.01" name="customer_value" value="{{ old('customer_value', 0) }}" placeholder="0.00">
                                    </label>
                                    <label class="form-label">Supplier cost change
                                        <input class="form-input" type="number" step="0.01" name="supplier_cost" value="{{ old('supplier_cost', 0) }}" placeholder="0.00">
                                    </label>
                                    <label class="form-label md:col-span-2 xl:col-span-4">Notes
                                        <textarea class="form-input min-h-20" name="notes" placeholder="Approval note, customer instruction, or commercial summary.">{{ old('notes') }}</textarea>
                                    </label>
                                </div>
                                <div class="mt-3 flex justify-end">
                                    <button class="btn btn-secondary" type="submit">Add variation order</button>
                                </div>
                            </form>
                        @endif

                        <div class="space-y-3">
                            @forelse($variationOrders as $variation)
                                <article class="rounded-lg border border-slate-200 bg-white p-4">
                                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <strong class="text-sm text-slate-950">{{ $variation['variation_number'] }}</strong>
                                                <span class="status-chip {{ $variation['status_class'] }}">{{ $variation['status_label'] }}</span>
                                                @if($variation['source_document_id'])
                                                    <a class="status-chip status-draft" href="{{ route('documents.show', $variation['source_document_id']) }}">{{ $variation['source_document_number'] }}</a>
                                                @endif
                                            </div>
                                            <h3 class="mt-2 text-base font-bold text-slate-950">{{ $variation['title'] }}</h3>
                                            @if($variation['source_document_label'])
                                                <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $variation['source_document_label'] }}</p>
                                            @endif
                                            @if($variation['notes'])
                                                <p class="mt-2 text-sm font-medium leading-6 text-slate-600">{{ $variation['notes'] }}</p>
                                            @endif
                                        </div>
                                        <span class="issuer-mini">{{ $variation['effective_date'] ? $date($variation['effective_date']) : 'No effective date' }}</span>
                                    </div>

                                    <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                        <div class="directory-stat-card">
                                            <span>Customer value change</span>
                                            <strong>{{ $money($variation['customer_value']) }}</strong>
                                        </div>
                                        <div class="directory-stat-card">
                                            <span>Supplier cost change</span>
                                            <strong>{{ $money($variation['supplier_cost']) }}</strong>
                                        </div>
                                        <div class="directory-stat-card">
                                            <span>Margin impact</span>
                                            <strong>{{ $money($variation['margin_impact']) }}</strong>
                                        </div>
                                        <div class="directory-stat-card">
                                            <span>Effective date</span>
                                            <strong>{{ $variation['effective_date'] ? $date($variation['effective_date']) : 'Not set' }}</strong>
                                        </div>
                                    </div>

                                    @if($canManageProject)
                                        <details class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                            <summary class="cursor-pointer text-sm font-bold text-slate-700">Edit variation order</summary>
                                            <div class="mt-3 space-y-3">
                                                <form method="post" action="{{ route('projects.variations.update', [$project, $variation['id']]) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                                        <label class="form-label">Variation number
                                                            <input class="form-input" name="variation_number" value="{{ $variation['variation_number'] }}" required>
                                                        </label>
                                                        <label class="form-label xl:col-span-2">Variation title
                                                            <input class="form-input" name="title" value="{{ $variation['title'] }}" required>
                                                        </label>
                                                        <label class="form-label">Status
                                                            <select class="form-input" name="status" required>
                                                                @foreach(\App\Models\ProjectVariation::STATUSES as $value => $label)
                                                                    <option value="{{ $value }}" @selected($variation['status'] === $value)>{{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        <label class="form-label">Effective date
                                                            <input class="form-input" type="date" name="effective_date" value="{{ $variation['effective_date'] }}">
                                                        </label>
                                                        <label class="form-label xl:col-span-2">Linked document
                                                            <select class="form-input" name="source_document_id">
                                                                <option value="">No linked document</option>
                                                                @foreach($sourceDocuments as $sourceDocument)
                                                                    <option value="{{ $sourceDocument->id }}" @selected((string) ($variation['source_document_id'] ?? '') === (string) $sourceDocument->id)>
                                                                        {{ $sourceDocument->document_number }} · {{ $sourceDocumentLabel($sourceDocument) }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        <label class="form-label">Customer value change
                                                            <input class="form-input" type="number" step="0.01" name="customer_value" value="{{ $variation['customer_value'] }}">
                                                        </label>
                                                        <label class="form-label">Supplier cost change
                                                            <input class="form-input" type="number" step="0.01" name="supplier_cost" value="{{ $variation['supplier_cost'] }}">
                                                        </label>
                                                        <label class="form-label md:col-span-2 xl:col-span-4">Notes
                                                            <textarea class="form-input min-h-20" name="notes">{{ $variation['notes'] }}</textarea>
                                                        </label>
                                                    </div>
                                                    <div class="mt-3 flex justify-end">
                                                        <button class="btn btn-secondary" type="submit">Save variation order</button>
                                                    </div>
                                                </form>
                                                <form method="post" action="{{ route('projects.variations.destroy', [$project, $variation['id']]) }}" onsubmit="return confirm('Delete this variation order?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-danger" type="submit">Delete</button>
                                                </form>
                                            </div>
                                        </details>
                                    @endif
                                </article>
                            @empty
                                <div class="empty-cell rounded-lg border border-slate-200 bg-slate-50">No variation orders recorded yet. Add approved or pending scope changes here when the original project baseline is no longer enough.</div>
                            @endforelse
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="directory-table-header border-0 p-0">
                        <div>
                            <h2 class="panel-title">Project exceptions</h2>
                            <p class="panel-subtitle">Items that need management review before relying on the project position.</p>
                        </div>
                        <span class="issuer-mini">{{ count($projectExceptions) }} open</span>
                    </div>

                    <div class="document-readiness-list">
                        @forelse($projectExceptions as $exception)
                            <article class="document-readiness-item readiness-state-{{ $exception['state'] }}">
                                <strong>{{ $exception['title'] }}</strong>
                                <p>{{ $exception['message'] }}</p>
                                @if(isset($exception['amount']))
                                    <p class="mt-1 text-xs font-bold uppercase tracking-wide text-slate-500">Amount: {{ $money($exception['amount']) }}</p>
                                @endif
                            </article>
                        @empty
                            <article class="document-readiness-item readiness-state-ready">
                                <strong>No project exceptions visible</strong>
                                <p>Budget, margin, actual cost, and work-item assignment checks have no visible blockers.</p>
                            </article>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                    <div class="directory-table-header border-0 p-4">
                        <div>
                            <h2 class="panel-title">Review evidence</h2>
                            <p class="panel-subtitle">Open the document lines behind each visible exception before deciding the next action.</p>
                        </div>
                        <span class="issuer-mini">{{ $evidenceSectionCount }} section{{ $evidenceSectionCount === 1 ? '' : 's' }}</span>
                    </div>

                    <div class="divide-y divide-slate-200">
                        @if(($unassignedEvidence['line_count'] ?? 0) > 0)
                            <article class="p-4">
                                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <h3 class="text-sm font-bold text-slate-950">Work item missing</h3>
                                        <p class="mt-1 text-sm font-medium leading-6 text-slate-600">These project lines still need a work item or cost code before the project position is fully traceable.</p>
                                    </div>
                                    <span class="issuer-mini">{{ $unassignedEvidence['line_count'] }} line{{ (int) $unassignedEvidence['line_count'] === 1 ? '' : 's' }}</span>
                                </div>

                                <div class="document-compact-table mt-4">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th>Document</th>
                                            <th>Party</th>
                                            <th>Issue date</th>
                                            <th>Line description</th>
                                            <th>Work item</th>
                                            <th class="text-right">Amount</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($unassignedEvidence['items'] as $item)
                                            <tr>
                                                <td data-label="Document">
                                                    <strong>{{ $item['document_number'] }}</strong>
                                                    <span>{{ $item['document_label'] }}</span>
                                                </td>
                                                <td data-label="Party">{{ $item['party_name'] }}</td>
                                                <td data-label="Issue date">{{ $date($item['issue_date']) }}</td>
                                                <td data-label="Line description">{{ $item['description'] }}</td>
                                                <td data-label="Work item">Not assigned</td>
                                                <td data-label="Amount" class="text-right font-bold text-slate-950">{{ $money($item['line_total']) }}</td>
                                                <td data-label="Action" class="text-right">
                                                    <a class="btn btn-secondary" href="{{ route('documents.show', $item['document_id']) }}">Open</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                @if($unassignedEvidence['is_truncated'] ?? false)
                                    <p class="mt-3 text-xs font-semibold text-slate-500">Showing first {{ $unassignedEvidence['showing_count'] }} of {{ $unassignedEvidence['line_count'] }} lines on this page.</p>
                                @endif
                            </article>
                        @endif

                        @foreach($overCommittedEvidence as $group)
                            <article class="p-4">
                                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <h3 class="text-sm font-bold text-slate-950">Budget overrun</h3>
                                        <p class="mt-1 text-sm font-medium leading-6 text-slate-600">{{ $group['work_item_label'] }} has supplier commitments of {{ $money($group['supplier_committed']) }} against a cost budget of {{ $money($group['cost_budget']) }}.</p>
                                    </div>
                                    <span class="issuer-mini">{{ $group['line_count'] }} line{{ (int) $group['line_count'] === 1 ? '' : 's' }}</span>
                                </div>

                                <div class="document-compact-table mt-4">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th>Document</th>
                                            <th>Party</th>
                                            <th>Issue date</th>
                                            <th>Line description</th>
                                            <th>Work item</th>
                                            <th class="text-right">Amount</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($group['items'] as $item)
                                            <tr>
                                                <td data-label="Document">
                                                    <strong>{{ $item['document_number'] }}</strong>
                                                    <span>{{ $item['document_label'] }}</span>
                                                </td>
                                                <td data-label="Party">{{ $item['party_name'] }}</td>
                                                <td data-label="Issue date">{{ $date($item['issue_date']) }}</td>
                                                <td data-label="Line description">{{ $item['description'] }}</td>
                                                <td data-label="Work item">{{ $group['work_item_label'] }}</td>
                                                <td data-label="Amount" class="text-right font-bold text-slate-950">{{ $money($item['line_total']) }}</td>
                                                <td data-label="Action" class="text-right">
                                                    <a class="btn btn-secondary" href="{{ route('documents.show', $item['document_id']) }}">Open</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                @if($group['is_truncated'] ?? false)
                                    <p class="mt-3 text-xs font-semibold text-slate-500">Showing first {{ $group['showing_count'] }} of {{ $group['line_count'] }} lines on this page.</p>
                                @endif
                            </article>
                        @endforeach

                        @foreach($overActualEvidence as $group)
                            <article class="p-4">
                                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <h3 class="text-sm font-bold text-slate-950">Actual cost over budget</h3>
                                        <p class="mt-1 text-sm font-medium leading-6 text-slate-600">{{ $group['work_item_label'] }} has supplier invoices of {{ $money($group['supplier_actual']) }} against a cost budget of {{ $money($group['cost_budget']) }}.</p>
                                    </div>
                                    <span class="issuer-mini">{{ $group['line_count'] }} line{{ (int) $group['line_count'] === 1 ? '' : 's' }}</span>
                                </div>

                                <div class="document-compact-table mt-4">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th>Document</th>
                                            <th>Party</th>
                                            <th>Issue date</th>
                                            <th>Line description</th>
                                            <th>Work item</th>
                                            <th class="text-right">Amount</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($group['items'] as $item)
                                            <tr>
                                                <td data-label="Document">
                                                    <strong>{{ $item['document_number'] }}</strong>
                                                    <span>{{ $item['document_label'] }}</span>
                                                </td>
                                                <td data-label="Party">{{ $item['party_name'] }}</td>
                                                <td data-label="Issue date">{{ $date($item['issue_date']) }}</td>
                                                <td data-label="Line description">{{ $item['description'] }}</td>
                                                <td data-label="Work item">{{ $group['work_item_label'] }}</td>
                                                <td data-label="Amount" class="text-right font-bold text-slate-950">{{ $money($item['line_total']) }}</td>
                                                <td data-label="Action" class="text-right">
                                                    <a class="btn btn-secondary" href="{{ route('documents.show', $item['document_id']) }}">Open</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                @if($group['is_truncated'] ?? false)
                                    <p class="mt-3 text-xs font-semibold text-slate-500">Showing first {{ $group['showing_count'] }} of {{ $group['line_count'] }} lines on this page.</p>
                                @endif
                            </article>
                        @endforeach

                        @if(($supplierActualEvidence['line_count'] ?? 0) > 0)
                            <article class="p-4">
                                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <h3 class="text-sm font-bold text-slate-950">Supplier actual above committed</h3>
                                        <p class="mt-1 text-sm font-medium leading-6 text-slate-600">Supplier invoices total {{ $money($supplierActualEvidence['supplier_invoiced']) }} against supplier commitments of {{ $money($supplierActualEvidence['supplier_committed']) }}.</p>
                                    </div>
                                    <span class="issuer-mini">{{ $supplierActualEvidence['line_count'] }} line{{ (int) $supplierActualEvidence['line_count'] === 1 ? '' : 's' }}</span>
                                </div>

                                <div class="document-compact-table mt-4">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th>Document</th>
                                            <th>Party</th>
                                            <th>Issue date</th>
                                            <th>Line description</th>
                                            <th>Work item</th>
                                            <th class="text-right">Amount</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        @foreach($supplierActualEvidence['items'] as $item)
                                            <tr>
                                                <td data-label="Document">
                                                    <strong>{{ $item['document_number'] }}</strong>
                                                    <span>{{ $item['document_label'] }}</span>
                                                </td>
                                                <td data-label="Party">{{ $item['party_name'] }}</td>
                                                <td data-label="Issue date">{{ $date($item['issue_date']) }}</td>
                                                <td data-label="Line description">{{ $item['description'] }}</td>
                                                <td data-label="Work item">{{ $item['work_item_label'] ?? 'Not assigned' }}</td>
                                                <td data-label="Amount" class="text-right font-bold text-slate-950">{{ $money($item['line_total']) }}</td>
                                                <td data-label="Action" class="text-right">
                                                    <a class="btn btn-secondary" href="{{ route('documents.show', $item['document_id']) }}">Open</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                @if($supplierActualEvidence['is_truncated'] ?? false)
                                    <p class="mt-3 text-xs font-semibold text-slate-500">Showing first {{ $supplierActualEvidence['showing_count'] }} of {{ $supplierActualEvidence['line_count'] }} lines on this page.</p>
                                @endif
                            </article>
                        @endif

                        @if($evidenceSectionCount === 0)
                            <article class="p-4">
                                <p class="text-sm font-semibold text-slate-600">No document lines are currently listed for the visible project exceptions.</p>
                            </article>
                        @endif
                    </div>
                </section>

                <section class="table-wrap directory-table-wrap">
                    <div class="directory-table-header rounded-t-lg border-t-0">
                        <div>
                            <h2 class="panel-title">Work breakdown</h2>
                            <p class="panel-subtitle">Use work items and cost codes when project lines need budget tracking.</p>
                        </div>
                        <span class="issuer-mini">{{ $workItems->count() }} work item{{ $workItems->count() === 1 ? '' : 's' }}</span>
                    </div>

                    <div class="space-y-4 p-4">
                        @if($errors->any())
                            <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-800">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                            <div class="directory-stat-card">
                                <span>Revenue budget</span>
                                <strong>{{ $money($workItemTotals['revenue_budget']) }}</strong>
                            </div>
                            <div class="directory-stat-card">
                                <span>Cost budget</span>
                                <strong>{{ $money($workItemTotals['cost_budget']) }}</strong>
                            </div>
                            <div class="directory-stat-card">
                                <span>Supplier committed</span>
                                <strong>{{ $money($workItemTotals['supplier_committed']) }}</strong>
                            </div>
                            <div class="directory-stat-card">
                                <span>Received / accepted</span>
                                <strong>{{ $money($workItemTotals['received_cost']) }}</strong>
                            </div>
                            <div class="directory-stat-card">
                                <span>Supplier actual</span>
                                <strong>{{ $money($workItemTotals['supplier_actual']) }}</strong>
                            </div>
                            <div class="directory-stat-card">
                                <span>Actual variance</span>
                                <strong>{{ $money($workItemTotals['actual_variance']) }}</strong>
                            </div>
                            <div class="directory-stat-card">
                                <span>Unassigned project lines</span>
                                <strong>{{ $unassignedWorkItemSummary['line_count'] }}</strong>
                            </div>
                        </div>

                        @if($unassignedWorkItemSummary['line_count'] > 0)
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm font-semibold text-amber-900">
                                {{ $unassignedWorkItemSummary['line_count'] }} project line{{ $unassignedWorkItemSummary['line_count'] === 1 ? '' : 's' }} still need a work item or cost code.
                            </div>
                        @endif

                        @if($canManageProject)
                            <form method="post" action="{{ route('projects.work-items.store', $project) }}" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                                @csrf
                                <input type="hidden" name="status" value="active">
                                <input type="hidden" name="sort_order" value="0">
                                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                    <label class="form-label">Cost code
                                        <input class="form-input" name="code" value="{{ old('code') }}" placeholder="1.01" required>
                                    </label>
                                    <label class="form-label xl:col-span-2">Work item
                                        <input class="form-input" name="name" value="{{ old('name') }}" placeholder="Installation and commissioning" required>
                                    </label>
                                    <label class="form-label">Cost type
                                        <select class="form-input" name="cost_type" required>
                                            @foreach($workItemCostTypes as $value => $label)
                                                <option value="{{ $value }}" @selected(old('cost_type', 'service') === $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="form-label">Parent work item
                                        <select class="form-input" name="parent_id">
                                            <option value="">No parent work item</option>
                                            @foreach($workItems as $parentOption)
                                                <option value="{{ $parentOption->id }}" @selected((string) old('parent_id') === (string) $parentOption->id)>{{ $parentOption->displayLabel() }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="form-label">Revenue budget
                                        <input class="form-input" type="number" step="0.01" min="0" name="revenue_budget" value="{{ old('revenue_budget', 0) }}">
                                    </label>
                                    <label class="form-label">Cost budget
                                        <input class="form-input" type="number" step="0.01" min="0" name="cost_budget" value="{{ old('cost_budget', 0) }}">
                                    </label>
                                    <label class="form-label md:col-span-2 xl:col-span-4">Description
                                        <textarea class="form-input min-h-20" name="description" placeholder="Scope, deliverable, or cost-code note">{{ old('description') }}</textarea>
                                    </label>
                                </div>
                                <div class="mt-3 flex justify-end">
                                    <button class="btn btn-secondary" type="submit">Add work item</button>
                                </div>
                            </form>
                        @endif

                        <div class="space-y-3">
                            @forelse($workItems as $workItem)
                                @php
                                    $itemSummary = $workItemSummaries[$workItem->id] ?? [
                                        'line_count' => 0,
                                        'quoted_revenue' => 0,
                                        'customer_confirmed' => 0,
                                        'supplier_committed' => 0,
                                        'received_cost' => 0,
                                        'supplier_actual' => 0,
                                        'remaining_budget' => (float) $workItem->cost_budget,
                                        'actual_variance' => (float) $workItem->cost_budget,
                                    ];
                                @endphp
                                <article class="rounded-lg border border-slate-200 bg-white p-4">
                                    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <strong class="text-sm text-slate-950">{{ $workItem->code }}</strong>
                                                <span class="status-chip {{ $workItem->statusChipClass() }}">{{ $workItem->statusDisplay() }}</span>
                                                <span class="status-chip status-draft">{{ $workItem->costTypeDisplay() }}</span>
                                            </div>
                                            <h3 class="mt-2 text-base font-bold text-slate-950">{{ $workItem->name }}</h3>
                                            @if($workItem->parent)
                                                <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Under {{ $workItem->parent->displayLabel() }}</p>
                                            @endif
                                            @if($workItem->description)
                                                <p class="mt-2 text-sm font-medium leading-6 text-slate-600">{{ $workItem->description }}</p>
                                            @endif
                                        </div>
                                        <span class="issuer-mini">{{ $itemSummary['line_count'] }} line{{ $itemSummary['line_count'] === 1 ? '' : 's' }}</span>
                                    </div>

                                    <div class="mt-4 grid gap-3 md:grid-cols-4 xl:grid-cols-8">
                                        <div class="directory-stat-card">
                                            <span>Revenue budget</span>
                                            <strong>{{ $money($workItem->revenue_budget) }}</strong>
                                        </div>
                                        <div class="directory-stat-card">
                                            <span>Cost budget</span>
                                            <strong>{{ $money($workItem->cost_budget) }}</strong>
                                        </div>
                                        <div class="directory-stat-card">
                                            <span>Quoted revenue</span>
                                            <strong>{{ $money($itemSummary['quoted_revenue']) }}</strong>
                                        </div>
                                        <div class="directory-stat-card">
                                            <span>Customer confirmed</span>
                                            <strong>{{ $money($itemSummary['customer_confirmed']) }}</strong>
                                        </div>
                                        <div class="directory-stat-card">
                                            <span>Supplier committed</span>
                                            <strong>{{ $money($itemSummary['supplier_committed']) }}</strong>
                                        </div>
                                        <div class="directory-stat-card">
                                            <span>Received / accepted</span>
                                            <strong>{{ $money($itemSummary['received_cost']) }}</strong>
                                        </div>
                                        <div class="directory-stat-card">
                                            <span>Supplier actual</span>
                                            <strong>{{ $money($itemSummary['supplier_actual']) }}</strong>
                                        </div>
                                        <div class="directory-stat-card">
                                            <span>Remaining budget</span>
                                            <strong>{{ $money($itemSummary['remaining_budget']) }}</strong>
                                        </div>
                                        <div class="directory-stat-card">
                                            <span>Actual variance</span>
                                            <strong>{{ $money($itemSummary['actual_variance']) }}</strong>
                                        </div>
                                    </div>

                                    @if($canManageProject)
                                        <details class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                                            <summary class="cursor-pointer text-sm font-bold text-slate-700">Edit work item</summary>
                                            <div class="mt-3 space-y-3">
                                                <form method="post" action="{{ route('projects.work-items.update', [$project, $workItem]) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                                        <label class="form-label">Cost code
                                                            <input class="form-input" name="code" value="{{ old('code', $workItem->code) }}" required>
                                                        </label>
                                                        <label class="form-label xl:col-span-2">Work item
                                                            <input class="form-input" name="name" value="{{ old('name', $workItem->name) }}" required>
                                                        </label>
                                                        <label class="form-label">Status
                                                            <select class="form-input" name="status" required>
                                                                @foreach($workItemStatuses as $value => $label)
                                                                    <option value="{{ $value }}" @selected(old('status', $workItem->status) === $value)>{{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        <label class="form-label">Cost type
                                                            <select class="form-input" name="cost_type" required>
                                                                @foreach($workItemCostTypes as $value => $label)
                                                                    <option value="{{ $value }}" @selected(old('cost_type', $workItem->cost_type) === $value)>{{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        <label class="form-label">Parent work item
                                                            <select class="form-input" name="parent_id">
                                                                <option value="">No parent work item</option>
                                                                @foreach($workItems as $parentOption)
                                                                    @continue($parentOption->id === $workItem->id)
                                                                    <option value="{{ $parentOption->id }}" @selected((string) old('parent_id', $workItem->parent_id) === (string) $parentOption->id)>{{ $parentOption->displayLabel() }}</option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        <label class="form-label">Revenue budget
                                                            <input class="form-input" type="number" step="0.01" min="0" name="revenue_budget" value="{{ old('revenue_budget', $workItem->revenue_budget) }}">
                                                        </label>
                                                        <label class="form-label">Cost budget
                                                            <input class="form-input" type="number" step="0.01" min="0" name="cost_budget" value="{{ old('cost_budget', $workItem->cost_budget) }}">
                                                        </label>
                                                        <label class="form-label">Sort order
                                                            <input class="form-input" type="number" min="0" name="sort_order" value="{{ old('sort_order', $workItem->sort_order) }}">
                                                        </label>
                                                        <label class="form-label md:col-span-2 xl:col-span-4">Description
                                                            <textarea class="form-input min-h-20" name="description">{{ old('description', $workItem->description) }}</textarea>
                                                        </label>
                                                    </div>
                                                    <div class="mt-3 flex justify-end">
                                                        <button class="btn btn-secondary" type="submit">Save work item</button>
                                                    </div>
                                                </form>
                                                @if($itemSummary['line_count'] === 0)
                                                    <form method="post" action="{{ route('projects.work-items.destroy', [$project, $workItem]) }}" onsubmit="return confirm('Delete this work item?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-danger" type="submit">Delete</button>
                                                    </form>
                                                @else
                                                    <p class="text-xs font-semibold text-slate-500">Used on document lines. Set the work item to inactive if it should no longer be used.</p>
                                                @endif
                                            </div>
                                        </details>
                                    @endif
                                </article>
                            @empty
                                <div class="empty-cell rounded-lg border border-slate-200 bg-slate-50">No work items yet. Add work items when this project needs line-level budget or cost-code tracking.</div>
                            @endforelse
                        </div>
                    </div>
                </section>

                <section class="table-wrap directory-table-wrap">
                    <div class="directory-table-header rounded-t-lg border-t-0">
                        <div>
                            <h2 class="panel-title">Project documents</h2>
                            <p class="panel-subtitle">Documents linked through the Project / job field.</p>
                        </div>
                    </div>
                    <div class="document-compact-table">
                        <table>
                            <thead>
                            <tr>
                                <th>Document</th>
                                <th>Party</th>
                                <th>Issue date</th>
                                <th class="text-right">Total</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($documents as $document)
                                @php
                                    $slug = \App\Models\Document::slugForType($document->type);
                                    $meta = \App\Models\Document::metaForSlug($slug);
                                @endphp
                                <tr>
                                    <td data-label="Document">
                                        <strong>{{ $document->document_number }}</strong>
                                        <span>{{ $meta['singular'] }}</span>
                                    </td>
                                    <td data-label="Party">{{ $document->partyName() }}</td>
                                    <td data-label="Issue date">{{ $date($document->issue_date) }}</td>
                                    <td data-label="Total" class="text-right font-bold text-slate-950">{{ $money($document->total) }}</td>
                                    <td data-label="Status">
                                        <span
                                            class="status-chip status-{{ $document->status }}"
                                            aria-label="{{ $document->statusAriaLabel() }}"
                                            data-status-group="{{ $document->statusSemanticGroupDisplay() }}"
                                        >{{ $document->statusDisplay() }}</span>
                                    </td>
                                    <td data-label="Action" class="text-right">
                                        <a class="btn btn-secondary" href="{{ route('documents.show', $document) }}">Open</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="empty-cell">No documents are linked to this project yet.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="directory-pagination">{{ $documents->links() }}</div>
        </section>
    </div>
</div>
@endsection
