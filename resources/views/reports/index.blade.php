@extends('layouts.app', ['title' => 'Reports', 'contentMode' => 'dashboard', 'showDateControl' => false])

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
    <section class="reports-titlebar">
        <div>
            <h1>Finance reports</h1>
            <p>Receivables, payables, cash movement, and invoice aging for finance follow-up.</p>
        </div>
        <form class="reports-range-form" method="GET" action="{{ route('reports.index') }}">
            <label for="range">Report period</label>
            <select id="range" name="range" class="form-input">
                @foreach([30, 60, 90, 365] as $days)
                    <option value="{{ $days }}" @selected($rangeDays === $days)>Last {{ $days }} days</option>
                @endforeach
            </select>
            <button class="btn btn-secondary" type="submit">Apply</button>
        </form>
    </section>

    <div class="reports-workbench">
        <div class="reports-main-column">
            <section class="reports-position-panel" aria-labelledby="money-position-title">
                <div class="reports-position-lead">
                    <h2 id="money-position-title" class="reports-section-label">Money position</h2>
                    <strong class="reports-position-value">{{ $money($netExposure) }}</strong>
                    <p>Open receivables minus open payables. Use this as the starting point before opening the detail lists.</p>
                    <div class="reports-net-strip {{ $netExposure >= 0 ? 'is-positive' : 'is-negative' }}">
                        <span>{{ $netExposure >= 0 ? 'Customer side is ahead' : 'Supplier side is ahead' }}</span>
                        <strong>{{ $money(abs($netExposure)) }}</strong>
                    </div>
                </div>

                <div class="reports-position-bars" aria-label="Open balance comparison">
                    @foreach($exposureBars as $bar)
                        <a class="reports-comparison-row reports-comparison-{{ $bar['tone'] }}" href="{{ $bar['route'] }}">
                            <span>
                                <strong>{{ $bar['label'] }}</strong>
                                <em>{{ $bar['helper'] }}</em>
                            </span>
                            <b>{{ $money($bar['amount']) }}</b>
                            <i aria-hidden="true"><u style="width: {{ $bar['percent'] }}%"></u></i>
                        </a>
                    @endforeach
                </div>
            </section>

            <div class="reports-analysis-grid">
                <section class="reports-chart-panel" aria-labelledby="cash-movement-title">
                    <div class="reports-panel-heading">
                        <div>
                            <h2 id="cash-movement-title">Cash movement</h2>
                            <p>Payments recorded in the selected period.</p>
                        </div>
                        <strong>{{ $money($netMovement) }}</strong>
                    </div>
                    <div class="reports-cash-bars">
                        @foreach($cashMovementBars as $bar)
                            <div class="reports-cash-row reports-cash-{{ $bar['tone'] }}">
                                <div>
                                    <strong>{{ $bar['label'] }}</strong>
                                    <span>{{ $movementLabel((float) $bar['amount'], (float) $bar['previous']) }}</span>
                                </div>
                                <b>{{ $money($bar['amount']) }}</b>
                                <i aria-hidden="true"><u style="width: {{ $bar['percent'] }}%"></u></i>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="reports-chart-panel" aria-labelledby="invoice-movement-title">
                    <div class="reports-panel-heading">
                        <div>
                            <h2 id="invoice-movement-title">12-month invoice movement</h2>
                            <p>Customer invoices against supplier invoices.</p>
                        </div>
                        <a class="link" href="{{ route('reports.index', ['range' => 365]) }}">Year view</a>
                    </div>
                    <div class="reports-month-chart" aria-label="Customer and supplier invoice totals for the last 12 months">
                        @foreach($monthlyInvoiceChart as $month)
                            <div class="reports-month-column">
                                <div class="reports-month-bars" aria-hidden="true">
                                    <span class="reports-month-customer" style="height: {{ $month['customer_percent'] }}%"></span>
                                    <span class="reports-month-supplier" style="height: {{ $month['supplier_percent'] }}%"></span>
                                </div>
                                <span>{{ $month['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="reports-chart-legend" aria-hidden="true">
                        <span><i class="reports-legend-customer"></i>Customer invoices</span>
                        <span><i class="reports-legend-supplier"></i>Supplier invoices</span>
                    </div>
                </section>
            </div>

            <section class="reports-aging-panel" aria-labelledby="aging-title">
                <div class="reports-panel-heading">
                    <div>
                        <h2 id="aging-title">Collection and payment aging</h2>
                        <p>Balances grouped by due-date risk.</p>
                    </div>
                </div>
                <div class="reports-aging-grid">
                    <div>
                        <div class="reports-aging-heading">
                            <h3>Receivables aging</h3>
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
                                    <span>
                                        <strong>{{ $bucket['label'] }}</strong>
                                        <em>{{ $bucket['helper'] }}</em>
                                    </span>
                                    <b>{{ $money($bucket['amount']) }}</b>
                                    <i aria-hidden="true"><u style="width: {{ $width }}%"></u></i>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <div class="reports-aging-heading">
                            <h3>Payables aging</h3>
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
                                    <span>
                                        <strong>{{ $bucket['label'] }}</strong>
                                        <em>{{ $bucket['helper'] }}</em>
                                    </span>
                                    <b>{{ $money($bucket['amount']) }}</b>
                                    <i aria-hidden="true"><u style="width: {{ $width }}%"></u></i>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <aside class="reports-side-column" aria-label="Report actions and risks">
            <section class="reports-attention-panel">
                <h2>Finance attention</h2>
                <p>Open the records behind the amount before acting.</p>
                <div class="reports-attention-list">
                    @foreach($attentionCards as $card)
                        <a class="reports-attention-card reports-attention-{{ $card['tone'] }}" href="{{ $card['route'] }}">
                            <span>{{ $card['label'] }}</span>
                            <strong>{{ $money($card['amount']) }}</strong>
                            <em>{{ $card['helper'] }}</em>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="reports-download-panel">
                <h2>Download CSV</h2>
                <p>Exports are streamed in chunks for shared hosting.</p>
                <div class="reports-download-list">
                    <a href="{{ route('reports.export', ['report' => 'receivables']) }}">
                        <x-icon name="export" class="h-4 w-4" aria-hidden="true" />
                        <span>Receivables CSV</span>
                    </a>
                    <a href="{{ route('reports.export', ['report' => 'payables']) }}">
                        <x-icon name="export" class="h-4 w-4" aria-hidden="true" />
                        <span>Payables CSV</span>
                    </a>
                    <a href="{{ route('reports.export', ['report' => 'payments']) }}">
                        <x-icon name="export" class="h-4 w-4" aria-hidden="true" />
                        <span>Payments CSV</span>
                    </a>
                </div>
            </section>

            <section class="reports-table-panel">
                <div class="reports-panel-heading">
                    <div>
                        <h2>Receivables to follow up</h2>
                        <p>Earliest due customer invoices.</p>
                    </div>
                    <a class="link" href="{{ route('documents.index', 'customer-invoices') }}">All</a>
                </div>
                <div class="reports-compact-list">
                    @forelse($receivables as $document)
                        <a class="reports-record-row" href="{{ route('documents.show', $document) }}">
                            <span>
                                <strong>{{ $document->document_number }}</strong>
                                <em>{{ $document->partyName() }}</em>
                            </span>
                            <b>{{ $document->currency }} {{ number_format($balanceDue($document), 2) }}</b>
                        </a>
                    @empty
                        <p class="reports-empty">No open receivables.</p>
                    @endforelse
                </div>
                @if($receivables->isNotEmpty())
                    <p class="reports-table-note">Showing 8 earliest open receivables.</p>
                @endif
            </section>

            <section class="reports-table-panel">
                <div class="reports-panel-heading">
                    <div>
                        <h2>Payables to schedule</h2>
                        <p>Earliest due supplier invoices.</p>
                    </div>
                    <a class="link" href="{{ route('documents.index', 'supplier-invoices') }}">All</a>
                </div>
                <div class="reports-compact-list">
                    @forelse($payables as $document)
                        <a class="reports-record-row" href="{{ route('documents.show', $document) }}">
                            <span>
                                <strong>{{ $document->document_number }}</strong>
                                <em>{{ $document->partyName() }}</em>
                            </span>
                            <b>{{ $document->currency }} {{ number_format($balanceDue($document), 2) }}</b>
                        </a>
                    @empty
                        <p class="reports-empty">No open payables.</p>
                    @endforelse
                </div>
                @if($payables->isNotEmpty())
                    <p class="reports-table-note">Showing 8 earliest open payables.</p>
                @endif
            </section>
        </aside>
    </div>
</div>
@endsection
