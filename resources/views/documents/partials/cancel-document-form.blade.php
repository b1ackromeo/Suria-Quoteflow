@php
    $cancelFormClass = $cancelFormClass ?? 'space-y-2';
@endphp

<form method="post" action="{{ route('documents.transition', [$document, 'cancel']) }}" class="{{ $cancelFormClass }}" onsubmit="return confirm('Cancel this document?');">
    <input type="hidden" name="_token" value="{{ csrf_token() }}">
    <label class="form-label">Cancellation reason
        <textarea class="form-input" name="cancellation_reason" placeholder="Explain why this document should not continue." required></textarea>
        <span class="mt-1 block text-xs font-semibold text-slate-500">Cancelling stops further document changes and records the reason.</span>
    </label>
    <button type="submit" class="btn btn-danger w-full">Cancel document</button>
</form>
