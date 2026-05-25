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
    $projectExceptions = $projectExceptions ?? [];
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
                    <span>Contract value</span>
                    <strong>{{ $money($project->contract_value) }}</strong>
                </div>
                <div class="directory-stat-card">
                    <span>Budget amount</span>
                    <strong>{{ $money($project->budget_amount) }}</strong>
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
