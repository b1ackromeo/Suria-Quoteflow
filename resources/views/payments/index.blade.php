@extends('layouts.app', ['title' => 'Payments'])

@section('content')
<section class="panel">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
            <tr><th>Date</th><th>Document</th><th>Party</th><th>Payment type</th><th>Reference</th><th class="text-right">Amount</th></tr>
            </thead>
            <tbody>
            @forelse($payments as $payment)
                <tr>
                    <td>{{ optional($payment->payment_date)->format('Y-m-d') }}</td>
                    <td><a class="link" href="{{ route('documents.show', $payment->document) }}">{{ $payment->document?->document_number }}</a></td>
                    <td>{{ $payment->document?->partyName() }}</td>
                    <td>{{ $payment->typeDisplay() }}</td>
                    <td>{{ $payment->reference }}</td>
                    <td class="text-right">{{ number_format($payment->amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty-cell">No payments recorded.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $payments->links() }}</div>
</section>
@endsection
