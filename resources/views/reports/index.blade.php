@extends('layouts.app', ['title' => 'Reports'])

@section('header_actions')
<div class="flex flex-wrap gap-2">
    <a class="btn btn-secondary" href="{{ route('reports.export', ['report' => 'receivables']) }}">Export receivables</a>
    <a class="btn btn-secondary" href="{{ route('reports.export', ['report' => 'payables']) }}">Export payables</a>
    <a class="btn btn-secondary" href="{{ route('reports.export', ['report' => 'payments']) }}">Export payments</a>
</div>
@endsection

@section('content')
<div class="grid gap-4 md:grid-cols-2">
    <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-zinc-500">Incoming payments, last 30 days</p>
        <p class="mt-2 text-2xl font-semibold">RM {{ number_format($incomingPayments, 2) }}</p>
    </div>
    <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-zinc-500">Outgoing payments, last 30 days</p>
        <p class="mt-2 text-2xl font-semibold">RM {{ number_format($outgoingPayments, 2) }}</p>
    </div>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Open Receivables</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Invoice</th><th>Customer</th><th>Due</th><th class="text-right">Balance</th></tr></thead>
                <tbody>
                @forelse($receivables as $document)
                    <tr>
                        <td><a class="link" href="{{ route('documents.show', $document) }}">{{ $document->document_number }}</a></td>
                        <td>{{ $document->partyName() }}</td>
                        <td>{{ optional($document->due_date)->format('Y-m-d') }}</td>
                        <td class="text-right">{{ $document->currency }} {{ number_format($document->balanceDue(), 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-cell">No open receivables.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
    <section class="panel">
        <div class="panel-header"><h2 class="panel-title">Open Payables</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Invoice</th><th>Supplier</th><th>Due</th><th class="text-right">Balance</th></tr></thead>
                <tbody>
                @forelse($payables as $document)
                    <tr>
                        <td><a class="link" href="{{ route('documents.show', $document) }}">{{ $document->document_number }}</a></td>
                        <td>{{ $document->partyName() }}</td>
                        <td>{{ optional($document->due_date)->format('Y-m-d') }}</td>
                        <td class="text-right">{{ $document->currency }} {{ number_format($document->balanceDue(), 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty-cell">No open payables.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>

<section class="panel mt-6">
    <div class="panel-header"><h2 class="panel-title">Invoice Totals by Month</h2></div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Month</th><th>Direction</th><th class="text-right">Total</th></tr></thead>
            <tbody>
            @forelse($monthlyInvoices as $row)
                <tr>
                    <td>{{ $row->month }}</td>
                    <td>{{ ucfirst($row->direction) }}</td>
                    <td class="text-right">RM {{ number_format($row->total, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="empty-cell">No invoice totals yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
