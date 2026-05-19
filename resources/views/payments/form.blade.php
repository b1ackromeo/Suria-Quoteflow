@extends('layouts.app', [
    'title' => $document->direction === 'outgoing' ? 'Record customer payment' : 'Record supplier payment',
])

@php
    $paymentTitle = $document->direction === 'outgoing'
        ? 'Record customer payment'
        : 'Record supplier payment';
@endphp

@section('content')
<form method="post" action="{{ route('payments.store', $document) }}" class="panel max-w-3xl space-y-5">
    @csrf
    <div class="rounded-md bg-zinc-50 p-4 text-sm">
        <p class="font-semibold">{{ $document->document_number }} · {{ $document->partyName() }}</p>
        <p class="mt-1 text-zinc-600">Total {{ $document->currency }} {{ number_format($document->total, 2) }} · Balance {{ $document->currency }} {{ number_format($document->balanceDue(), 2) }}</p>
    </div>
    <div class="grid gap-4 md:grid-cols-2">
        <label class="form-label">Payment date
            <input class="form-input" type="date" name="payment_date" value="{{ old('payment_date', optional($payment->payment_date)->format('Y-m-d') ?? now()->toDateString()) }}" required>
        </label>
        <label class="form-label">Amount
            <input class="form-input" type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $document->balanceDue()) }}" required>
        </label>
        <label class="form-label">Method
            <input class="form-input" name="method" value="{{ old('method') }}" placeholder="Bank transfer, cheque, cash">
        </label>
        <label class="form-label">Reference
            <input class="form-input" name="reference" value="{{ old('reference') }}">
        </label>
    </div>
    <label class="form-label">Notes
        <textarea class="form-input min-h-28" name="notes">{{ old('notes') }}</textarea>
    </label>
    <div class="flex gap-3">
        <button class="btn btn-primary" type="submit">{{ $paymentTitle }}</button>
        <a class="btn btn-secondary" href="{{ route('documents.show', $document) }}">Cancel</a>
    </div>
</form>
@endsection
