@extends('layouts.app', ['title' => 'Reports'])

@php
    $money = fn ($value) => 'RM '.number_format((float) $value, 2);
    $balanceDue = fn ($document) => max(0, (float) $document->total - (float) ($document->paid_total ?? 0));
    $movementLabel = function (float $current, float $previous): string {
        if ($previous <= 0 && $current <= 0) {
            return 'No movement in this or previous period';
        }

        if ($previous <= 0) {
            return 'New movement this period';
        }

        $change = (($current - $previous) / $previous) * 100;
        $direction = $change >= 0 ? 'up' : 'down';

        return abs($change) < 0.1
            ? 'Flat against previous period'
            : number_format(abs($change), 1).'% '.$direction.' against previous period';
    };
    $agingTotal = fn ($buckets) => max(0.01, collect($buckets)->sum(fn ($bucket) => (float) $bucket['amount']));
    $netMovement = (float) $incomingPayments - (float) $outgoingPayments;
@endphp

@section('content')
<div class="reports-page">
    <section class="reports-hero">
        <div>
            <p class="reports-eyebrow">Finance reports</p>
            <h1>Exposure and cash movement</h1>
            <p>See what is overdue, what cash moved, what is due soon, and which invoices accounts should open next.</p>
        </div>
        <div class="reports-control-row">
            <form class="reports-range-form" method="GET" action="{{ route('reports.index') }}">
                <label class="sr-only" for="range">Report period</label>
                <select id="range" name="range" class="form-input">
                    @foreach([30, 60, 90, 365] as $days)
                        <option value="{{ $days }}" @selected($rangeDays === $days)>Last {{ $days }} days</option>
                    @endforeach
                </select>
                <button class="btn btn-secondary" type="submit">Apply</button>
            </form>
            <div class="reports-actions" aria-label="Report exports">
                <a class="btn btn-secondary" href="{{ route('reports.export', ['report' => 'receivables']) }}">Export receivables</a>
                <a class="btn btn-secondary" href="{{ route('reports.export', ['report' => 'payables']) }}">Export payables</a>
                <a class="btn btn-secondary" href="{{ route('reports.export', ['report' => 'payments']) }}">Export payments</a>
            </div>
        </div>
    </section>

    <section class="reports-risk-grid" aria-label="Finance attention">
        <a class="reports-risk-card reports-risk-card-alert" href="{{ route('documents.index', 'customer-invoices') }}">
            <span>Overdue receivables</span>
            <strong>{{ $money($overdueReceivableBalance) }}</strong>
            <p>Customer balances past due date. Open invoices for follow-up.</p>
        </a>
        <a class="reports-risk-card reports-risk-card-warning" href="{{ route('documents.index', 'supplier-invoices') }}">
            <span>Supplier payments due soon</span>
            <strong>{{ $money($supplierDueSoonBalance) }}</strong>
            <p>Matched supplier invoices due within 7 days.</p>
        </a>
        <section class="reports-risk-card">
            <span>Net exposure</span>
            <strong>{{ $money($netExposure) }}</strong>
            <p>Open receivables minus open payables.</p>
        </section>
    </section>

    <div class="reports-metric-grid">
        <section class="reports-metric-card">
            <span>Incoming payments</span>
            <strong>{{ $money($incomingPayments) }}</strong>
            <p>Last {{ $rangeDays }} days. {{ $movementLabel((float) $incomingPayments, (float) $previousIncomingPayments) }}.</p>
        </section>
        <section class="reports-metric-card">
            <span>Outgoing payments</span>
            <strong>{{ $money($outgoingPayments) }}</strong>
            <p>Last {{ $rangeDays }} days. {{ $movementLabel((float) $outgoingPayments, (float) $previousOutgoingPayments) }}.</p>
        </section>
        <section class="reports-metric-card">
            <span>Open receivables</span>
            <strong>{{ $money($receivableBalance) }}</strong>
            <p>{{ $receivableCount }} issued or part-paid customer invoices still carrying balance.</p>
        </section>
        <section class="reports-metric-card">
            <span>Open payables</span>
            <strong>{{ $money($payableBalance) }}</strong>
            <p>{{ $payableCount }} matched or part-paid supplier invoices still carrying balance.</p>
        </section>
    </div>

    <div class="reports-insight-grid">
        <section class="reports-aging-card">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Receivables aging</h2>
                    <p class="panel-subtitle">Customer money still to collect.</p>
                </div>
                <a class="link" href="{{ route('documents.index', 'customer-invoices') }}">Open receivables</a>
            </div>
            <div class="reports-aging-list">
                @foreach($receivableAging as $bucket)
                    @php
                        $width = (float) $bucket['amount'] > 0
                            ? min(100, max(4, ((float) $bucket['amount'] / $agingTotal($receivableAging)) * 100))
                            : 0;
                    @endphp
                    <div class="reports-aging-row">
                        <div>
                            <strong>{{ $bucket['label'] }}</strong>
                            <span>{{ $bucket['helper'] }}</span>
                        </div>
                        <em>{{ $money($bucket['amount']) }}</em>
                        <div class="reports-aging-bar" aria-hidden="true">
                            <span style="width: {{ $width }}%"></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="reports-aging-card">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Payables aging</h2>
                    <p class="panel-subtitle">Supplier money still to pay.</p>
                </div>
                <a class="link" href="{{ route('documents.index', 'supplier-invoices') }}">Open payables</a>
            </div>
            <div class="reports-aging-list">
                @foreach($payableAging as $bucket)
                    @php
                        $width = (float) $bucket['amount'] > 0
                            ? min(100, max(4, ((float) $bucket['amount'] / $agingTotal($payableAging)) * 100))
                            : 0;
                    @endphp
                    <div class="reports-aging-row">
                        <div>
                            <strong>{{ $bucket['label'] }}</strong>
                            <span>{{ $bucket['helper'] }}</span>
                        </div>
                        <em>{{ $money($bucket['amount']) }}</em>
                        <div class="reports-aging-bar" aria-hidden="true">
                            <span style="width: {{ $width }}%"></span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="reports-cash-card">
            <p class="reports-eyebrow">Payment movement</p>
            <h2>{{ $money($netMovement) }}</h2>
            <p>Net cash movement for the selected period.</p>
            <dl>
                <div>
                    <dt>Collected</dt>
                    <dd>{{ $money($incomingPayments) }}</dd>
                </div>
                <div>
                    <dt>Paid out</dt>
                    <dd>{{ $money($outgoingPayments) }}</dd>
                </div>
                <div>
                    <dt>Previous collected</dt>
                    <dd>{{ $money($previousIncomingPayments) }}</dd>
                </div>
                <div>
                    <dt>Previous paid out</dt>
                    <dd>{{ $money($previousOutgoingPayments) }}</dd>
                </div>
            </dl>
        </section>
    </div>

    <div class="reports-table-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Receivables to follow up</h2>
                    <p class="panel-subtitle">Earliest due customer invoices with open balance.</p>
                </div>
                <a class="link" href="{{ route('documents.index', 'customer-invoices') }}">Open invoices</a>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Customer</th>
                            <th>Due</th>
                            <th>Status</th>
                            <th class="text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($receivables as $document)
                        <tr>
                            <td><a class="link" href="{{ route('documents.show', $document) }}">{{ $document->document_number }}</a></td>
                            <td>{{ $document->partyName() }}</td>
                            <td>{{ optional($document->due_date)->format('d M Y') ?? '-' }}</td>
                            <td><span class="status-chip status-{{ $document->status }}">{{ $document->statusDisplay() }}</span></td>
                            <td class="text-right">{{ $document->currency }} {{ number_format($balanceDue($document), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-cell">No open receivables.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($receivables->isNotEmpty())
                <p class="reports-table-note">Showing the earliest 8 open receivables so the page stays fast.</p>
            @endif
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2 class="panel-title">Payables to schedule</h2>
                    <p class="panel-subtitle">Earliest due supplier invoices with open balance.</p>
                </div>
                <a class="link" href="{{ route('documents.index', 'supplier-invoices') }}">Open invoices</a>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Supplier</th>
                            <th>Due</th>
                            <th>Status</th>
                            <th class="text-right">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($payables as $document)
                        <tr>
                            <td><a class="link" href="{{ route('documents.show', $document) }}">{{ $document->document_number }}</a></td>
                            <td>{{ $document->partyName() }}</td>
                            <td>{{ optional($document->due_date)->format('d M Y') ?? '-' }}</td>
                            <td><span class="status-chip status-{{ $document->status }}">{{ $document->statusDisplay() }}</span></td>
                            <td class="text-right">{{ $document->currency }} {{ number_format($balanceDue($document), 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-cell">No open payables.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($payables->isNotEmpty())
                <p class="reports-table-note">Showing the earliest 8 open payables so the page stays fast.</p>
            @endif
        </section>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Invoice totals by month</h2>
                <p class="panel-subtitle">Customer and supplier invoice totals for the last 12 months.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Direction</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
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
</div>
@endsection
