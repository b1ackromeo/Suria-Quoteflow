@extends('layouts.app', [
    'title' => $project->project_code,
    'contentMode' => 'fullscreen',
])

@php
    $companyProfile = \App\Models\CompanyProfile::active();
    $money = fn ($value) => $companyProfile->formatMoney($value);
    $date = fn ($value) => $value ? $companyProfile->formatDate($value) : 'Not set';
    $marginTarget = rtrim(rtrim(number_format((float) $project->margin_target_percent, 2), '0'), '.');
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
                        <span>Supplier committed</span>
                        <strong>{{ $money($summary['supplier_committed']) }}</strong>
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

                <section class="table-wrap directory-table-wrap">
                    <div class="directory-table-header rounded-t-lg border-t-0">
                        <div>
                            <h2 class="panel-title">Project documents</h2>
                            <p class="panel-subtitle">Documents linked through the Project / job field.</p>
                        </div>
                    </div>
                    <table class="data-table">
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
                                <td>
                                    <p class="font-bold text-slate-950">{{ $document->document_number }}</p>
                                    <p class="mt-1 text-xs font-bold uppercase tracking-wide text-slate-400">{{ $meta['singular'] }}</p>
                                </td>
                                <td>{{ $document->partyName() }}</td>
                                <td>{{ $date($document->issue_date) }}</td>
                                <td class="text-right font-bold text-slate-950">{{ $money($document->total) }}</td>
                                <td>
                                    <span
                                        class="status-chip status-{{ $document->status }}"
                                        aria-label="{{ $document->statusAriaLabel() }}"
                                        data-status-group="{{ $document->statusSemanticGroupDisplay() }}"
                                    >{{ $document->statusDisplay() }}</span>
                                </td>
                                <td class="text-right">
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
                </section>
            </div>

            <div class="directory-pagination">{{ $documents->links() }}</div>
        </section>
    </div>
</div>
@endsection
