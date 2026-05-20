@php
    $companyProfile = \App\Models\CompanyProfile::active();
    $attachments = $document->attachments ?? collect();
    $currency = strtoupper($document->currency ?: $companyProfile->baseCurrency());
    $items = $document->items ?? collect();
    $previewOnly = $previewOnly ?? false;
    $sourcePanelMode = $sourcePanelMode ?? 'full';
    $usesSupplierQuoteCapture = in_array($document->type, ['purchase_request', 'supplier_quotation'], true);
    $extractionPolicy = app(\App\Services\Documents\ExternalDocumentExtractionService::class);

    $categoryPriority = match ($document->type) {
        'customer_po' => ['customer_po', 'supporting_document'],
        'supplier_quotation' => ['supplier_quote', 'supporting_document'],
        'purchase_request' => ['supplier_quote', 'supporting_document'],
        'goods_receipt' => ['delivery_order', 'service_report', 'uat_document', 'installation_report', 'completion_photo', 'delivery_evidence', 'supporting_document'],
        default => ['supporting_document', 'email_approval'],
    };

    $previewableAttachments = $attachments->filter(fn ($attachment) => $attachment->isPreviewable())->values();
    $primaryAttachment = collect($categoryPriority)
        ->map(fn ($category) => $previewableAttachments->firstWhere('category', $category))
        ->filter()
        ->first() ?? $previewableAttachments->first();

    $supportingAttachments = $attachments
        ->filter(fn ($attachment) => $attachment->id !== $primaryAttachment?->id)
        ->values();
    $extraction = $primaryAttachment?->extraction;
    $canVerifyExtraction = in_array(auth()->user()?->role, ['admin', 'manager', 'procurement', 'accounts'], true);
    $extractedFields = $extraction?->extracted_fields ?? [];
    $verifiedFields = $extraction?->verified_fields ?? [];
    $quoteFieldLabels = $usesSupplierQuoteCapture ? $extractionPolicy->fieldLabels($document) : [];
    $manualFields = $usesSupplierQuoteCapture ? $extractionPolicy->manualFields($document) : [];
    $manualItems = $usesSupplierQuoteCapture ? $extractionPolicy->manualItems($document) : [];
    $capturePaymentTerms = $verifiedFields['payment_terms'] ?? $extractedFields['payment_terms'] ?? $manualFields['payment_terms'] ?? null;
    $captureCommercialTerms = $verifiedFields['commercial_terms'] ?? $extractedFields['commercial_terms'] ?? $manualFields['commercial_terms'] ?? null;
    $quoteExtractionItems = collect(old('items', $verifiedFields['items'] ?? $extractedFields['items'] ?? $manualItems))
        ->filter(fn ($item) => is_array($item) && filled($item['description'] ?? null))
        ->values();
    $verificationMethod = $extraction?->verification_method ?: ($extraction?->status === 'failed' ? 'manual' : 'ocr_assisted');
    $verificationMethodLabel = match ($verificationMethod) {
        'ocr_assisted' => 'Assisted review',
        'external' => 'Uploaded file review',
        default => 'Manual review',
    };

    $previewTitle = match ($document->type) {
        'customer_po' => 'Customer PO received',
        'supplier_quotation' => 'Supplier quotation received',
        'purchase_request' => $primaryAttachment?->category === 'supplier_quote' ? 'Supplier quotation preview' : 'Purchase request record',
        'goods_receipt' => 'Goods receipt evidence',
        default => 'Uploaded document',
    };

    $fileInstruction = match ($document->type) {
        'customer_po' => 'Upload the customer PO file so the team can review the customer-issued document.',
        'supplier_quotation' => 'Upload the supplier quotation PDF or image so procurement can review the supplier-issued offer.',
        'purchase_request' => 'Upload the supplier quotation PDF or image before approval, or record a quotation exception reason on the request.',
        'goods_receipt' => 'Upload delivery order for material receipts, or service report/UAT evidence for service acceptance.',
        default => 'Upload supporting evidence so the request can be reviewed with the document.',
    };

    $partyLabel = match ($document->type) {
        'customer_po' => 'Customer',
        'purchase_request' => 'Requested supplier',
        default => 'Supplier',
    };

    $referenceLabel = match ($document->type) {
        'customer_po' => 'Customer PO no.',
        'supplier_quotation' => 'Supplier quotation no.',
        'purchase_request' => $primaryAttachment?->category === 'supplier_quote' ? 'Supplier quotation no.' : 'Request ref',
        'goods_receipt' => 'Evidence reference',
        default => 'Reference',
    };

    $dateLabel = match ($document->type) {
        'customer_po' => 'Date received',
        'goods_receipt' => 'Recorded date',
        'supplier_quotation' => 'Quote date',
        'purchase_request' => $primaryAttachment?->category === 'supplier_quote' ? 'Quote date' : 'Request date',
        default => 'Request date',
    };

    $renderCapture = $usesSupplierQuoteCapture && ! $previewOnly && in_array($sourcePanelMode, ['capture', 'full'], true);
    $renderPreview = in_array($sourcePanelMode, ['preview', 'full'], true);
    $captureFormId = 'supplier-quote-verification-form-'.$document->id;
    $lineItemsFirst = $document->type === 'purchase_request';
    $confirmQuoteButtonLabel = $document->type === 'purchase_request' ? 'Verify supplier quotation evidence' : 'Verify supplier quotation';
    $captureKicker = $document->type === 'purchase_request' ? 'Next action' : 'Supplier quotation review';
    $captureTitle = $document->type === 'purchase_request' ? 'Verify supplier quotation evidence' : 'Verify supplier quotation';
    $captureCopy = $lineItemsFirst
        ? 'QuoteFlow extracted these details from the uploaded supplier quotation. Correct the supplier, terms, and quotation lines before they become purchase request items.'
        : 'QuoteFlow extracted these details from the uploaded supplier quotation. Correct the quotation fields, terms, and line items before verification.';
    $previewLabel = match ($document->type) {
        'purchase_request', 'supplier_quotation' => 'Supplier quotation file',
        'customer_po' => 'Customer PO file',
        'goods_receipt' => 'Receiving evidence file',
        default => 'Uploaded file',
    };
@endphp

<article class="external-document-preview-card external-document-mode-{{ $sourcePanelMode }}" data-external-document-preview>
    @if($renderCapture)
        <section id="supplier-quote-verification-panel" class="supplier-invoice-extraction-panel external-document-extraction-panel external-document-capture-card @if($lineItemsFirst) external-document-capture-card-lines-first @endif">
            <div class="supplier-invoice-extraction-heading">
                <div>
                    <p class="document-pane-kicker">{{ $captureKicker }}</p>
                    <h4>{{ $captureTitle }}</h4>
                    <p class="external-document-capture-copy">{{ $captureCopy }}</p>
                </div>
                <div class="external-document-capture-actions">
                    @if($extraction?->verified_at)
                        <span class="supplier-invoice-extraction-state is-verified">{{ $verificationMethodLabel }} · {{ $companyProfile->formatDate($extraction->verified_at) }}</span>
                    @elseif($extraction?->status === 'processed')
                        <span class="supplier-invoice-extraction-state is-ready">Details ready for review</span>
                    @elseif($extraction?->status === 'failed')
                        <span class="supplier-invoice-extraction-state is-failed">Manual review needed</span>
                    @else
                        <span class="supplier-invoice-extraction-state">Details not read yet</span>
                    @endif
                    @if($canVerifyExtraction && $extraction && $extraction->status !== 'verified')
                        <button type="submit" form="{{ $captureFormId }}" class="btn btn-primary">{{ $confirmQuoteButtonLabel }}</button>
                    @endif
                </div>
            </div>

            @if(! $primaryAttachment)
                <p class="supplier-invoice-extraction-note">{{ $fileInstruction }}</p>
            @elseif(! $extraction)
                <div class="external-document-capture-toolbar">
                        <p>Run OCR to prepare editable quotation fields, terms, and line items from the uploaded supplier quotation.</p>
                    @if($primaryAttachment->canBeExtracted() && $canVerifyExtraction)
                        <form method="post" action="{{ route('attachments.extract', $primaryAttachment) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">Run OCR</button>
                        </form>
                    @endif
                </div>
            @else
                @if($extraction->status === 'failed')
                    <div class="supplier-invoice-extraction-error">
                        <strong>OCR could not read this supplier quotation.</strong>
                        <span>{{ $extraction->error_message ?: 'Verify the quotation details manually from the uploaded file.' }}</span>
                    </div>
                @endif

                <form id="{{ $captureFormId }}" method="post" action="{{ route('attachment-extractions.verify', $extraction) }}" class="supplier-invoice-extraction-form external-document-capture-form">
                    @csrf
                    @method('PUT')

                    @if($lineItemsFirst)
                        <div class="external-document-capture-facts" aria-label="Extracted supplier quotation fields">
                            <span>Supplier <strong>{{ $verifiedFields['supplier_name'] ?? $extractedFields['supplier_name'] ?? $document->supplier?->name ?? '-' }}</strong></span>
                            <span>Quote <strong>{{ $verifiedFields['quote_number'] ?? $extractedFields['quote_number'] ?? $document->external_reference ?? '-' }}</strong></span>
                            <span>Total <strong>{{ filled($verifiedFields['total'] ?? $extractedFields['total'] ?? null) ? $companyProfile->formatMoney((float) ($verifiedFields['total'] ?? $extractedFields['total']), $currency) : '-' }}</strong></span>
                        </div>
                    @endif

                    @if($lineItemsFirst)
                        @include('documents.partials.external-document-capture-lines')
                    @endif

                    <div class="external-document-capture-fields">
                    @foreach($quoteFieldLabels as $key => $label)
                            <label @class([
                                'external-document-terms-field' => $key === 'commercial_terms',
                                'external-document-wide-field' => $key === 'supplier_name',
                            ])>
                                <span>{{ $label }}</span>
                                @if($key === 'commercial_terms')
                                    <textarea
                                        class="form-input"
                                        name="fields[{{ $key }}]"
                                        rows="4"
                                        @if($extraction->status === 'verified') readonly @endif
                                    >{{ old('fields.'.$key, $verifiedFields[$key] ?? $extractedFields[$key] ?? $manualFields[$key] ?? '') }}</textarea>
                                @else
                                    <input
                                        class="form-input"
                                        name="fields[{{ $key }}]"
                                        value="{{ old('fields.'.$key, $verifiedFields[$key] ?? $extractedFields[$key] ?? $manualFields[$key] ?? '') }}"
                                        @if($extraction->status === 'verified') readonly @endif
                                    >
                                @endif
                            </label>
                        @endforeach
                    </div>

                    @unless($lineItemsFirst)
                        @include('documents.partials.external-document-capture-lines')
                    @endunless

                    <label class="external-document-confirmation">
                        <input type="checkbox" name="supplier_confirmed" value="1" @checked(old('supplier_confirmed', $extraction->supplier_confirmed)) @disabled($extraction->status === 'verified')>
                        <span>{{ $document->type === 'purchase_request' ? 'Supplier matches PR' : 'Supplier matches record' }}</span>
                    </label>
                    <label class="external-document-confirmation">
                        <input type="checkbox" name="recorded_total_confirmed" value="1" @checked(old('recorded_total_confirmed', $extraction->recorded_total_confirmed)) @disabled($extraction->status === 'verified')>
                        <span>Total and terms checked</span>
                    </label>
                    <label class="supplier-invoice-verify-button">
                        <span>Verification notes</span>
                        <textarea class="form-input" name="verification_notes" @if($extraction->status === 'verified') readonly @endif>{{ old('verification_notes', $extraction->verification_notes) }}</textarea>
                    </label>

                </form>

                @if(filled($extraction->raw_text))
                    <details class="supplier-invoice-ocr-text">
                        <summary>Extracted text from file</summary>
                        <pre>{{ \Illuminate\Support\Str::limit($extraction->raw_text, 2500) }}</pre>
                    </details>
                @endif
            @endif
        </section>
    @endif

    @if($renderPreview)
        @if($primaryAttachment)
            <section class="external-document-file-frame">
                <div class="external-document-file-toolbar">
                    <div class="min-w-0">
                        <strong>{{ $previewLabel }}</strong>
                        <span>{{ $primaryAttachment->original_name }}</span>
                    </div>
                    <div class="external-document-toolbar-actions">
                        @if($supportingAttachments->isNotEmpty())
                            <span class="external-document-file-count">{{ $supportingAttachments->count() }} supporting file{{ $supportingAttachments->count() === 1 ? '' : 's' }}</span>
                        @endif

                        @if($usesSupplierQuoteCapture && ! $previewOnly && $primaryAttachment->canBeExtracted() && $canVerifyExtraction && $extraction?->status !== 'verified')
                            <form method="post" action="{{ route('attachments.extract', $primaryAttachment) }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary min-h-9 px-3 py-1.5">{{ $extraction ? 'Re-run OCR' : 'Run OCR' }}</button>
                            </form>
                        @else
                            <a class="btn btn-secondary min-h-9 px-3 py-1.5" href="{{ route('attachments.download', $primaryAttachment) }}">Download</a>
                        @endif
                    </div>
                </div>

                @if($primaryAttachment->isPdf())
                    <iframe
                        class="external-document-pdf-viewer"
                        src="{{ route('attachments.preview', $primaryAttachment) }}#toolbar=1&navpanes=0"
                        title="{{ $previewTitle }} PDF preview: {{ $primaryAttachment->original_name }}"
                    ></iframe>
                @elseif($primaryAttachment->isImage())
                    <div class="external-document-image-viewer">
                        <img src="{{ route('attachments.preview', $primaryAttachment) }}" alt="{{ $previewTitle }} image preview: {{ $primaryAttachment->original_name }}">
                    </div>
                @endif
            </section>
        @else
            <section class="external-document-record-only">
                <h4>No supporting file uploaded yet</h4>
                <p>{{ $fileInstruction }}</p>
            </section>
        @endif

        @if($sourcePanelMode === 'full')
            <div class="external-document-summary-strip">
                <div>
                    <span>{{ $partyLabel }}</span>
                    <strong>{{ $document->partyName() }}</strong>
                </div>
                <div>
                    <span>{{ $referenceLabel }}</span>
                    <strong>{{ $document->external_reference ?: '-' }}</strong>
                </div>
                <div>
                    <span>{{ $dateLabel }}</span>
                    <strong>{{ $document->issue_date ? $companyProfile->formatDate($document->issue_date) : '-' }}</strong>
                </div>
                <div>
                    <span>Recorded total</span>
                    <strong>{{ $companyProfile->formatMoney((float) $document->total, $currency) }}</strong>
                </div>
            </div>

            @if($items->isNotEmpty())
                <section class="external-document-lines">
                    <div class="external-document-lines-heading">
                        <span>Recorded items</span>
                        <strong>{{ $items->count() }} line{{ $items->count() === 1 ? '' : 's' }}</strong>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="text-right">Qty</th>
                                <th>Unit</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items->take(4) as $item)
                                <tr>
                                    <td>{{ $item->description }}</td>
                                    <td class="text-right">{{ \App\Models\Document::formatQuantity($item->quantity) }}</td>
                                    <td>{{ $item->unit }}</td>
                                    <td class="text-right font-bold">{{ $companyProfile->formatMoney((float) $item->line_total, $currency) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif
        @endif

        @if($supportingAttachments->isNotEmpty())
            <section class="external-document-supporting-files">
                <p>Supporting documents</p>
                <div>
                    @foreach($supportingAttachments as $attachment)
                        <span>{{ $attachment->original_name }}</span>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</article>

@if($renderCapture)
    <template data-quote-line-template>
        <div class="external-document-capture-line" data-quote-line>
            <label class="external-document-line-include">
                <input type="hidden" data-name="included" value="0">
                <input type="checkbox" data-name="included" value="1" checked data-quote-line-include>
                <span class="external-document-line-checkbox-label">Include in PR</span>
            </label>
            <label>
                <span class="external-document-line-field-label">Description</span>
                <input class="form-input" data-name="description">
            </label>
            <label>
                <span class="external-document-line-field-label">Qty</span>
                <input class="form-input" data-name="quantity" value="1">
            </label>
            <label>
                <span class="external-document-line-field-label">Unit</span>
                <input class="form-input" data-name="unit" value="unit">
            </label>
            <label>
                <span class="external-document-line-field-label">Unit price</span>
                <input class="form-input" data-name="unit_price" value="0.00">
            </label>
            <input type="hidden" data-name="tax_rate" value="0">
        </div>
    </template>
    <script>
    (() => {
        const root = document.currentScript.previousElementSibling?.previousElementSibling;
        const addButton = root?.querySelector('[data-add-quote-line]');
        const list = root?.querySelector('[data-quote-line-list]');
        const template = document.currentScript.previousElementSibling;
        const counter = root?.querySelector('[data-quote-line-count]');

        if (!root || !addButton || !list || !template) return;

        const updateLineState = (line) => {
            const include = line.querySelector('[data-quote-line-include]');
            line.classList.toggle('is-excluded', include && !include.checked);
        };

        const updateCounter = () => {
            if (!counter) return;

            const selectedCount = Array.from(list.querySelectorAll('[data-quote-line]'))
                .filter((line) => {
                    const include = line.querySelector('[data-quote-line-include]');

                    return !include || include.checked;
                })
                .length;

            counter.textContent = `${selectedCount} selected`;
        };

        list.querySelectorAll('[data-quote-line]').forEach(updateLineState);
        updateCounter();

        addButton.addEventListener('click', () => {
            const clone = template.content.cloneNode(true);
            const index = list.querySelectorAll('[data-quote-line]').length;

            clone.querySelectorAll('[data-name]').forEach((field) => {
                field.name = `items[${index}][${field.dataset.name}]`;
            });

            list.appendChild(clone);
            updateCounter();
        });

        list.addEventListener('change', (event) => {
            if (!event.target.matches('[data-quote-line-include]')) return;

            const line = event.target.closest('[data-quote-line]');
            if (line) updateLineState(line);
            updateCounter();
        });
    })();
    </script>
@endif
