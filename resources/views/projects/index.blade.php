@extends('layouts.app', [
    'title' => 'Projects',
    'contentMode' => 'fullscreen',
])

@php
    $companyProfile = \App\Models\CompanyProfile::active();
    $money = fn ($value) => $companyProfile->formatMoney($value);
    $date = fn ($value) => $value ? $companyProfile->formatDate($value) : 'Not set';
    $percent = fn ($value) => $value === null ? 'Not available' : rtrim(rtrim(number_format((float) $value, 2), '0'), '.').'%';
    $canManageProjects = auth()->user()->hasRole('admin', 'manager');
@endphp

@section('content')
<div class="fullscreen-workspace directory-workspace">
    <section class="directory-page-header">
        <div class="min-w-0">
            <p class="document-pane-kicker">Project control</p>
            <h1 class="document-pane-title">Projects</h1>
            <p class="mt-1 max-w-3xl text-sm font-semibold leading-6 text-slate-500">
                Review project budget, margin, supplier commitments, and documents that need management attention.
            </p>
        </div>
        <div class="directory-header-actions">
            <span class="issuer-mini">{{ $projects->total() }} shown</span>
            <span class="status-chip status-approved">{{ $summary['active'] }} active</span>
            <span class="status-chip {{ $summary['review_projects'] > 0 ? 'status-pending_approval' : 'status-paid' }}">{{ $summary['review_projects'] }} need review</span>
            <a class="btn btn-secondary" href="{{ route('projects.export', request()->query()) }}">Export CSV</a>
            @if($canManageProjects)
                <a class="btn btn-primary" href="{{ route('projects.create') }}">New project</a>
            @endif
        </div>
    </section>

    <div class="directory-body">
        <aside class="directory-side-panel">
            <form class="directory-filter-card" method="get">
                <label class="form-label">Find project
                    <input class="form-input" name="q" value="{{ $searchTerm }}" placeholder="Code, name, customer, manager, amount">
                </label>
                <label class="form-label">Status
                    <select class="form-input" name="status">
                        <option value="">All statuses</option>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($statusFilter === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-label">Commercial review
                    <select class="form-input" name="review">
                        <option value="">All projects</option>
                        @foreach($reviewFilters as $value => $label)
                            <option value="{{ $value }}" @selected($reviewFilter === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="grid grid-cols-2 gap-2">
                    <button class="btn btn-primary" type="submit">Search</button>
                    <a class="btn btn-secondary" href="{{ route('projects.index') }}">Clear</a>
                </div>
            </form>

            <div class="directory-stat-grid">
                <div class="directory-stat-card">
                    <span>Total projects</span>
                    <strong>{{ $summary['total'] }}</strong>
                </div>
                <div class="directory-stat-card">
                    <span>Projects needing review</span>
                    <strong>{{ $summary['review_projects'] }}</strong>
                </div>
                <div class="directory-stat-card">
                    <span>Contract value</span>
                    <strong>{{ $money($summary['contract_value']) }}</strong>
                </div>
            </div>

            <div class="directory-stat-grid">
                <div class="directory-stat-card">
                    <span>Customer confirmed</span>
                    <strong>{{ $money($summary['customer_confirmed']) }}</strong>
                </div>
                <div class="directory-stat-card">
                    <span>Supplier committed</span>
                    <strong>{{ $money($summary['supplier_committed']) }}</strong>
                </div>
                <div class="directory-stat-card">
                    <span>Expected margin</span>
                    <strong>{{ $money($summary['expected_margin']) }}</strong>
                </div>
                <div class="directory-stat-card">
                    <span>Expected margin %</span>
                    <strong>{{ $percent($summary['expected_margin_percent']) }}</strong>
                </div>
                <div class="directory-stat-card">
                    <span>Unassigned project lines</span>
                    <strong>{{ $summary['unassigned_line_count'] }}</strong>
                </div>
            </div>
        </aside>

        <section class="directory-table-pane">
            <div class="directory-table-header">
                <div>
                    <h2 class="panel-title">Project list</h2>
                    <p class="panel-subtitle">Open a project to review the full budget, margin, work breakdown, and document trail.</p>
                </div>
                @if($searchTerm !== '' || $statusFilter || $reviewFilter)
                    <span class="status-chip status-draft">Filtered</span>
                @endif
            </div>

            <div class="directory-table-scroll">
                <div class="space-y-3">
                    @forelse($projects as $project)
                        @php
                            $commercial = $portfolioSummaries[$project->id] ?? [
                                'customer_confirmed' => 0,
                                'supplier_committed' => 0,
                                'expected_margin' => 0,
                                'expected_margin_percent' => null,
                                'budget_remaining' => (float) $project->budget_amount,
                                'review_count' => 0,
                                'review_reasons' => [],
                            ];
                        @endphp
                        <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="flex flex-col gap-4">
                                <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="status-chip {{ $project->statusChipClass() }}">{{ $project->statusDisplay() }}</span>
                                            <span class="status-chip {{ $commercial['review_count'] > 0 ? 'status-pending_approval' : 'status-paid' }}">{{ $commercial['review_count'] > 0 ? 'Review needed' : 'No visible exceptions' }}</span>
                                            <span class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $project->project_code }}</span>
                                        </div>
                                        <h3 class="mt-3 text-base font-bold leading-6 text-slate-950">{{ $project->name }}</h3>
                                        <p class="mt-1 text-xs font-semibold text-slate-500">{{ $project->documents_count }} linked document{{ $project->documents_count === 1 ? '' : 's' }} · {{ $project->customer?->name ?? 'No customer assigned' }}</p>

                                        @if($commercial['review_reasons'] !== [])
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                @foreach(array_slice($commercial['review_reasons'], 0, 3) as $reason)
                                                    <span class="status-chip status-draft">{{ $reason }}</span>
                                                @endforeach
                                                @if(count($commercial['review_reasons']) > 3)
                                                    <span class="issuer-mini">+{{ count($commercial['review_reasons']) - 3 }} more</span>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    <div class="shrink-0">
                                        <a class="btn btn-secondary w-full md:w-auto" href="{{ route('projects.show', $project) }}">Open project</a>
                                    </div>
                                </div>

                                <dl class="grid min-w-0 gap-3 text-sm sm:grid-cols-2 xl:grid-cols-3">
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Manager</dt>
                                        <dd class="mt-1 font-bold text-slate-950">{{ $project->manager?->name ?? 'Not assigned' }}</dd>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Target date</dt>
                                        <dd class="mt-1 font-bold text-slate-950">{{ $date($project->expected_completion_date) }}</dd>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Customer confirmed</dt>
                                        <dd class="mt-1 font-bold text-slate-950">{{ $money($commercial['customer_confirmed']) }}</dd>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Supplier committed</dt>
                                        <dd class="mt-1 font-bold text-slate-950">{{ $money($commercial['supplier_committed']) }}</dd>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Expected margin</dt>
                                        <dd class="mt-1 font-bold text-slate-950">{{ $money($commercial['expected_margin']) }}</dd>
                                        <dd class="mt-1 text-xs font-bold text-slate-500">{{ $percent($commercial['expected_margin_percent']) }}</dd>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Budget remaining</dt>
                                        <dd class="mt-1 font-bold text-slate-950">{{ $money($commercial['budget_remaining']) }}</dd>
                                    </div>
                                </dl>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm font-semibold text-slate-500">
                            @if($searchTerm !== '' || $statusFilter || $reviewFilter)
                                No projects match these filters.
                            @else
                                No projects yet. Create a project when a job needs budget, margin, or document grouping.
                            @endif
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="directory-pagination">{{ $projects->links() }}</div>
        </section>
    </div>
</div>
@endsection
