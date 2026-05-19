@php
    $isExtractionVerified = $extraction->status === 'verified';
    $selectedQuoteLineCount = $quoteExtractionItems
        ->filter(function (array $item) {
            $included = $item['included'] ?? true;

            return filter_var($included, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false;
        })
        ->count();
@endphp

<div class="external-document-extraction-items supplier-invoice-verify-button">
    <div class="external-document-lines-heading">
        <span>Quote line items</span>
        <div class="flex items-center gap-2">
            <strong data-quote-line-count>{{ $selectedQuoteLineCount }} selected</strong>
            @unless($isExtractionVerified)
                <button type="button" class="btn btn-secondary min-h-9 px-3 py-1.5" data-add-quote-line>Add line</button>
            @endunless
        </div>
    </div>
    <p class="external-document-line-selection-note">Only selected quotation lines become purchase request items.</p>
    <div class="external-document-capture-lines" data-quote-line-list>
        <div class="external-document-capture-line external-document-capture-line-heading" aria-hidden="true">
            <span>Select</span>
            <span>Description</span>
            <span>Qty</span>
            <span>Unit</span>
            <span>Unit price</span>
        </div>
        @forelse($quoteExtractionItems->take(20) as $index => $item)
            @php
                $included = filter_var($item['included'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false;
            @endphp
            <div @class(['external-document-capture-line', 'is-excluded' => ! $included]) data-quote-line>
                <label class="external-document-line-include">
                    <input type="hidden" name="items[{{ $index }}][included]" value="0">
                    <input type="checkbox" name="items[{{ $index }}][included]" value="1" @checked($included) @disabled($isExtractionVerified) data-quote-line-include>
                    <span class="external-document-line-checkbox-label">Include in PR</span>
                </label>
                <label>
                    <span class="external-document-line-field-label">Description</span>
                    <input class="form-input" name="items[{{ $index }}][description]" value="{{ $item['description'] ?? '' }}" @if($isExtractionVerified) readonly @endif>
                </label>
                <label>
                    <span class="external-document-line-field-label">Qty</span>
                    <input class="form-input" name="items[{{ $index }}][quantity]" value="{{ \App\Models\Document::formatQuantity($item['quantity'] ?? null, '1') }}" @if($isExtractionVerified) readonly @endif>
                </label>
                <label>
                    <span class="external-document-line-field-label">Unit</span>
                    <input class="form-input" name="items[{{ $index }}][unit]" value="{{ $item['unit'] ?? 'unit' }}" @if($isExtractionVerified) readonly @endif>
                </label>
                <label>
                    <span class="external-document-line-field-label">Unit price</span>
                    <input class="form-input" name="items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] ?? '0.00' }}" @if($isExtractionVerified) readonly @endif>
                </label>
                <input type="hidden" name="items[{{ $index }}][tax_rate]" value="{{ $item['tax_rate'] ?? '0' }}">
            </div>
        @empty
            <p class="supplier-invoice-extraction-note">No quotation line items were detected. Add the supplier quotation lines here before confirming.</p>
        @endforelse
    </div>
</div>
