@extends('layouts.app', [
    'title' => 'Projects',
    'contentMode' => 'fullscreen',
])

@php
    $companyProfile = \App\Models\CompanyProfile::active();
    $money = fn ($value) => $companyProfile->formatMoney($value);
    $date = fn ($value) => $value ? $companyProfile->formatDate($value) : 'Not set';
    $canManageProjects = auth()->user()->hasRole('admin', 'manager');
@endphp

@section('content')
<div class="fullscreen-workspace directory-workspace">
    <section class="directory-page-header">
        <div class="min-w-0">
            <p class="document-pane-kicker">Project control</p>
            <h1 class="document-pane-title">Projects</h1>
            <p class="mt-1 max-w-3xl text-sm font-semibold leading-6 text-slate-500">
                Group commercial documents by job, track basic budget exposure, and open the document trail for each project.
            </p>
        </div>
        <div class="directory-header-actions">
            <span class="issuer-mini">{{ $projects->total() }} shown</span>
            <span class="status-chip status-approved">{{ $summary['active'] }} active</span>
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
                    <span>Contract value</span>
                    <strong>{{ $money($summary['contract_value']) }}</strong>
                </div>
                <div class="directory-stat-card">
                    <span>Budget amount</span>
                    <strong>{{ $money($summary['budget_amount']) }}</strong>
                </div>
            </div>
        </aside>

        <section class="directory-table-pane">
            <div class="directory-table-header">
                <div>
                    <h2 class="panel-title">Project list</h2>
                    <p class="panel-subtitle">Open a project to review linked documents, budget reference, and margin position.</p>
                </div>
                @if($searchTerm !== '' || $statusFilter)
                    <span class="status-chip status-draft">Filtered</span>
                @endif
            </div>

            <div class="directory-table-scroll">
                <div class="space-y-3">
                    @forelse($projects as $project)
                        <article class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                                <div class="min-w-0 xl:max-w-xs">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="status-chip {{ $project->statusChipClass() }}">{{ $project->statusDisplay() }}</span>
                                        <span class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $project->project_code }}</span>
                                    </div>
                                    <h3 class="mt-3 text-base font-bold leading-6 text-slate-950">{{ $project->name }}</h3>
                                    <p class="mt-1 text-xs font-semibold text-slate-500">{{ $project->documents_count }} linked document{{ $project->documents_count === 1 ? '' : 's' }}</p>
                                </div>

                                <dl class="grid min-w-0 flex-1 gap-3 text-sm sm:grid-cols-2 xl:grid-cols-5">
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Customer</dt>
                                        <dd class="mt-1 font-bold text-slate-950">{{ $project->customer?->name ?? 'Not assigned' }}</dd>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Manager</dt>
                                        <dd class="mt-1 font-bold text-slate-950">{{ $project->manager?->name ?? 'Not assigned' }}</dd>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Target date</dt>
                                        <dd class="mt-1 font-bold text-slate-950">{{ $date($project->expected_completion_date) }}</dd>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Contract value</dt>
                                        <dd class="mt-1 font-bold text-slate-950">{{ $money($project->contract_value) }}</dd>
                                    </div>
                                    <div class="rounded-lg bg-slate-50 p-3">
                                        <dt class="text-[11px] font-bold uppercase tracking-wide text-slate-400">Committed cost</dt>
                                        <dd class="mt-1 font-bold text-slate-950">{{ $money($project->supplier_committed_total ?? 0) }}</dd>
                                    </div>
                                </dl>

                                <div class="shrink-0 xl:self-center">
                                    <a class="btn btn-secondary w-full xl:w-auto" href="{{ route('projects.show', $project) }}">Open</a>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm font-semibold text-slate-500">
                            No projects yet. Create a project when a job needs budget, margin, or document grouping.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="directory-pagination">{{ $projects->links() }}</div>
        </section>
    </div>
</div>
@endsection
