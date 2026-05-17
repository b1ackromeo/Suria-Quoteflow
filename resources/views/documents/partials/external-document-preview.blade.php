@php
    $attachments = $document->attachments ?? collect();
    $currency = strtoupper($document->currency ?: 'MYR');
    $items = $document->items ?? collect();
    $previewOnly = $previewOnly ?? false;
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
    $quoteExtractionItems = collect(old('items', $verifiedFields['items'] ?? $extractedFields['items'] ?? $manualItems))->values();
    $verificationMethod = $extraction?->verification_method ?: ($extraction?->status === 'failed' ? 'manual' : 'ocr_assisted');
    $verificationMethodLabel = match ($verificationMethod) {
        'ocr_assisted' => 'OCR-assisted',
        'external' => 'External',
        default => 'Manual',
    };

    $previewTitle = match ($document->type) {
        'customer_po' => 'Customer PO received',
        'supplier_quotation' => 'Supplier quotation received',
        'purchase_request' => $primaryAttachment?->category === 'supplier_quote' ? 'Supplier quote evidence' : 'Purchase request record',
        'goods_receipt' => 'Receiving evidence record',
        default => 'Received document record',
    };

    $fileInstruction = match ($document->type) {
        'customer_po' => 'Upload the customer PO file as PO received so the team can review the customer-issued document.',
        'supplier_quotation' => 'Upload the supplier quotation PDF or image so procurement can review the supplier-issued offer.',
        'purchase_request' => 'Upload the supplier quotation PDF or image before approval, or record a quote exception reason on the request.',
        'goods_receipt' => 'Upload delivery order for material receipts, or service report/UAT evidence for service acceptance.',
        default => 'Upload supporting evidence so the request can be reviewed with the source document.',
    };

    $partyLabel = match ($document->type) {
        'customer_po' => 'Customer',
        'purchase_request' => 'Requested supplier',
        default => 'Supplier',
    };

    $referenceLabel = match ($document->type) {
        'customer_po' => 'Customer PO no.',
        'supplier_quotation' => 'Supplier quote no.',
        'purchase_request' => $primaryAttachment?->category === 'supplier_quote' ? 'Supplier quote no.' : 'Request ref',
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
@endphp

<article class="external-document-preview-card" data-external-document-preview>
    <div class="external-document-preview-header">
        <div>
            <p class="document-pane-kicker">Source document preview</p>
            <h3>{{ $previewTitle }}</h3>
        </div>
        <span class="status-chip status-{{ $document->status }}">{{ $document->statusDisplay() }}</span>
    </div>

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
            <strong>{{ optional($document->issue_date)->format('d M Y') ?? '-' }}</strong>
        </div>
        <div>
            <span>Recorded total</span>
            <strong>{{ $currency }} {{ number_format((float) $document->total, 2) }}</strong>
        </div>
    </div>

    @if($primaryAttachment)
        <section class="external-document-file-frame">
            <div class="external-document-file-toolbar">
                <div class="min-w-0">
                    <strong>{{ $primaryAttachment->original_name }}</strong>
                    <span>{{ strtoupper($primaryAttachment->mime_type ?: 'file') }} · {{ number_format(($primaryAttachment->size ?? 0) / 1024, 1) }} KB</span>
                </div>
                <div class="external-document-toolbar-actions">
                    @if($supportingAttachments->isNotEmpty())
                        <span class="external-document-file-count">{{ $supportingAttachments->count() }} supporting file{{ $supportingAttachments->count() === 1 ? '' : 's' }}</span>
                    @endif

                    @if($usesSupplierQuoteCapture && ! $previewOnly)
                        @if($extraction?->status === 'verified')
                            <span class="supplier-invoice-extraction-state is-verified">Verified</span>
                        @elseif(in_array($extraction?->status, ['processed', 'failed'], true))
                            <button
                                type="button"
                                class="btn btn-primary min-h-9 px-3 py-1.5"
                                onclick="document.getElementById('supplier-quote-verification-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                            >{{ $extraction?->status === 'failed' ? 'Review manually' : 'Verify OCR draft' }}</button>
                        @else
                            <span class="supplier-invoice-extraction-state">Not extracted</span>
                        @endif

                        @if($primaryAttachment->canBeExtracted() && $canVerifyExtraction)
                            <form method="post" action="{{ route('attachments.extract', $primaryAttachment) }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary min-h-9 px-3 py-1.5">{{ $extraction ? 'Re-run OCR' : 'Run OCR' }}</button>
                            </form>
                        @elseif(! $primaryAttachment->canBeExtracted())
                            <a class="btn btn-secondary min-h-9 px-3 py-1.5" href="{{ route('attachments.download', $primaryAttachment) }}">Download file</a>
                        @endif
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

        @if($usesSupplierQuoteCapture && ! $previewOnly)
            <section id="supplier-quote-verification-panel" class="supplier-invoice-extraction-panel external-document-extraction-panel">
                <div class="supplier-invoice-extraction-heading">
                    <div>
                        <p class="document-pane-kicker">Assisted capture</p>
                        <h4>{{ $document->type === 'purchase_request' ? 'Supplier quote evidence' : 'Supplier quote details' }}</h4>
                    </div>
                    @if($extraction?->verified_at)
                        <span>{{ $verificationMethodLabel }} verification · {{ $extraction->verified_at->format('d M Y, g:i A') }}</span>
                    @endif
                </div>

                @if(! $extraction)
                    <p class="supplier-invoice-extraction-note">{{ $document->type === 'purchase_request' ? 'Run OCR to prepare a draft from the supplier quote. Verify the fields before this request is approved.' : 'Run OCR to prepare a draft from this supplier quote. Review and verify the fields before using the quote for purchasing.' }}</p>
                @else
                    @if($extraction->status === 'failed')
                        <div class="supplier-invoice-extraction-error">
                            <strong>OCR could not read this supplier quote.</strong>
                            <span>{{ $extraction->error_message ?: 'Verify the quote details manually from the uploaded source file.' }}</span>
                        </div>
                    @endif

                    <form method="post" action="{{ route('attachment-extractions.verify', $extraction) }}" class="supplier-invoice-extraction-form">
                        @csrf
                        @method('PUT')
                        @foreach($quoteFieldLabels as $key => $label)
                            <label>
                                <span>{{ $label }}</span>
                                <input
                                    class="form-input"
                                    name="fields[{{ $key }}]"
                                    value="{{ old('fields.'.$key, $verifiedFields[$key] ?? $extractedFields[$key] ?? $manualFields[$key] ?? '') }}"
                                    @if($extraction->status === 'verified') readonly @endif
                                >
                            </label>
                        @endforeach

                        <div class="external-document-extraction-items supplier-invoice-verify-button">
                            <div class="external-document-lines-heading">
                                <span>Quote line items</span>
                                <strong>{{ $quoteExtractionItems->count() }} line{{ $quoteExtractionItems->count() === 1 ? '' : 's' }}</strong>
                            </div>
                            @if($quoteExtractionItems->isNotEmpty())
                                <div class="external-document-extraction-item-grid">
                                    @foreach($quoteExtractionItems->take(10) as $index => $item)
                                        <label class="external-document-extraction-description">
                                            <span>Description</span>
                                            <input class="form-input" name="items[{{ $index }}][description]" value="{{ $item['description'] ?? '' }}" @if($extraction->status === 'verified') readonly @endif>
                                        </label>
                                        <label>
                                            <span>Qty</span>
                                            <input class="form-input" name="items[{{ $index }}][quantity]" value="{{ $item['quantity'] ?? '1' }}" @if($extraction->status === 'verified') readonly @endif>
                                        </label>
                                        <label>
                                            <span>Unit</span>
                                            <input class="form-input" name="items[{{ $index }}][unit]" value="{{ $item['unit'] ?? 'unit' }}" @if($extraction->status === 'verified') readonly @endif>
                                        </label>
                                        <label>
                                            <span>Unit price</span>
                                            <input class="form-input" name="items[{{ $index }}][unit_price]" value="{{ $item['unit_price'] ?? '0.00' }}" @if($extraction->status === 'verified') readonly @endif>
                                        </label>
                                        <input type="hidden" name="items[{{ $index }}][tax_rate]" value="{{ $item['tax_rate'] ?? '0' }}">
                                    @endforeach
                                </div>
                            @else
                                <p class="supplier-invoice-extraction-note">No line items were found in the OCR draft. Enter the quote total above, or edit the document lines after verification.</p>
                            @endif
                        </div>

                        <label class="flex items-start gap-2 text-sm font-semibold text-slate-700">
                            <input type="checkbox" name="supplier_confirmed" value="1" class="mt-1" @checked(old('supplier_confirmed', $extraction->supplier_confirmed)) @disabled($extraction->status === 'verified')>
                            <span>{{ $document->type === 'purchase_request' ? 'Supplier on the quote matches the requested supplier.' : 'Supplier on the quote matches this supplier record.' }}</span>
                        </label>
                        <label class="flex items-start gap-2 text-sm font-semibold text-slate-700">
                            <input type="checkbox" name="recorded_total_confirmed" value="1" class="mt-1" @checked(old('recorded_total_confirmed', $extraction->recorded_total_confirmed)) @disabled($extraction->status === 'verified')>
                            <span>Recorded total has been checked against the supplier quote.</span>
                        </label>
                        <label>
                            <span>Verification notes</span>
                            <textarea class="form-input" name="verification_notes" @if($extraction->status === 'verified') readonly @endif>{{ old('verification_notes', $extraction->verification_notes) }}</textarea>
                        </label>

                        @if($canVerifyExtraction && $extraction->status !== 'verified')
                            <button type="submit" class="btn btn-primary supplier-invoice-verify-button">{{ $document->type === 'purchase_request' ? 'Verify supplier quote evidence' : 'Verify and update supplier quote' }}</button>
                        @endif
                    </form>

                    @if(filled($extraction->raw_text))
                        <details class="supplier-invoice-ocr-text">
                            <summary>OCR text used for this draft</summary>
                            <pre>{{ \Illuminate\Support\Str::limit($extraction->raw_text, 2500) }}</pre>
                        </details>
                    @endif
                @endif
            </section>
        @endif
    @else
        <section class="external-document-record-only">
            <h4>No previewable source file uploaded yet</h4>
            <p>{{ $fileInstruction }}</p>
        </section>
    @endif

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
                            <td class="text-right">{{ number_format((float) $item->quantity, 3) }}</td>
                            <td>{{ $item->unit }}</td>
                            <td class="text-right font-bold">{{ $currency }} {{ number_format((float) $item->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
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
</article>
