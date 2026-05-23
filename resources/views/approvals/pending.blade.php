@extends('layouts.app', ['title' => 'Pending Approvals'])

@php
    $companyProfile = \App\Models\CompanyProfile::active();
@endphp

@section('content')
<section class="panel">
    <div class="panel-header">
        <div>
            <h1 class="panel-title">Pending approvals</h1>
            <p class="panel-subtitle">Documents waiting for manager review across all modules.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr>
                <th>Document</th>
                <th>Type</th>
                <th>Party</th>
                <th>Status</th>
                <th class="text-right">Amount</th>
                <th>Requester</th>
                <th>Requested</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            @forelse($approvals as $approval)
                @php
                    $document = $approval->document;
                    $typeLabel = $document
                        ? (collect(\App\Models\Document::TYPES)->firstWhere('type', $document->type)['singular'] ?? ucwords(str_replace('_', ' ', $document->type)))
                        : 'Document';
                @endphp
                <tr>
                    <td>
                        @if($document)
                            <a class="link" href="{{ route('documents.show', $document) }}">{{ $document->document_number }}</a>
                        @else
                            Document removed
                        @endif
                    </td>
                    <td>{{ $typeLabel }}</td>
                    <td>{{ $document?->partyName() ?? 'Unavailable' }}</td>
                    <td>
                        @if($document)
                            <span
                                class="status-chip status-{{ $document->status }}"
                                aria-label="{{ $document->statusAriaLabel() }}"
                                data-status-group="{{ $document->statusSemanticGroupDisplay() }}"
                            >{{ $document->statusDisplay() }}</span>
                        @else
                            <span class="status-chip status-cancelled">Unavailable</span>
                        @endif
                    </td>
                    <td class="text-right font-bold text-slate-900">
                        @if($document)
                            {{ $companyProfile->formatMoney($document->total, $document->currency) }}
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $approval->requester?->name ?? 'Unknown' }}</td>
                    <td>{{ $companyProfile->formatDate($approval->created_at) }}</td>
                    <td>
                        @if($document)
                            <a class="link" href="{{ route('documents.show', $document) }}">Open</a>
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty-cell">No approvals waiting.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $approvals->links() }}</div>
</section>
@endsection
