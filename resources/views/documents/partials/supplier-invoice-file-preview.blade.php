@php
    $attachments = $document->attachments ?? collect();
    $previewOnly = $previewOnly ?? false;
    $previewableAttachments = $attachments->filter(fn ($attachment) => $attachment->isPreviewable())->values();
    $invoiceAttachment = $attachments->firstWhere('category', 'invoice_copy') ?? $previewableAttachments->first();
    $extraction = $invoiceAttachment?->extraction;
    $companyProfile = \App\Models\CompanyProfile::active();
    $currency = strtoupper($document->currency ?: $companyProfile->baseCurrency());
    $canVerifyExtraction = in_array(auth()->user()?->role, ['admin', 'manager', 'procurement', 'accounts'], true);
    $extractedFields = $extraction?->extracted_fields ?? [];
    $verifiedFields = $extraction?->verified_fields ?? [];
    $supplierInvoiceVerification = $supplierInvoiceVerification ?? null;
    $verificationMethod = $extraction?->verification_method ?: ($extraction?->status === 'verified' ? 'ocr_assisted' : 'manual');
    $verificationMethodLabel = match ($verificationMethod) {
        'ocr_assisted' => 'Assisted review',
        'external' => 'Uploaded file review',
        default => 'Manual review',
    };
    $fieldLabels = [
        'supplier_name' => 'Supplier name',
        'invoice_number' => 'Supplier invoice no.',
        'invoice_date' => 'Invoice date',
        'po_number' => 'PO / reference no.',
        'subtotal' => 'Subtotal',
        'tax_total' => $companyProfile->taxLabel().' amount',
        'total' => 'Invoice total',
        'payment_terms' => 'Payment terms',
    ];
    $supportingAttachments = $attachments
        ->filter(fn ($attachment) => $attachment->id !== $invoiceAttachment?->id)
        ->values();
    $manualFields = [
        'supplier_name' => $document->supplier?->name,
        'invoice_number' => $document->external_reference,
        'invoice_date' => $companyProfile->formatDate($document->issue_date),
        'po_number' => $document->relatedDocument?->document_number,
        'subtotal' => $companyProfile->formatNumber($document->subtotal),
        'tax_total' => $companyProfile->formatNumber($document->tax_total),
        'total' => $companyProfile->formatNumber($document->total),
        'payment_terms' => $document->paymentTermsDisplay(),
    ];
@endphp

<article class="supplier-invoice-preview-card {{ $previewOnly ? 'supplier-invoice-preview-card-compact' : '' }}" data-supplier-invoice-file-preview>
    @if($previewOnly)
        <div class="supplier-invoice-preview-strip">
            <div class="min-w-0">
                <strong>{{ $document->document_number }}</strong>
                <span>{{ $document->supplier?->name ?? 'Supplier not selected' }} · {{ $document->external_reference ?: 'No supplier invoice no.' }} · {{ $companyProfile->formatDate($document->issue_date) }} · {{ $companyProfile->formatMoney($document->total, $currency) }}@if($invoiceAttachment) · {{ $invoiceAttachment->original_name }}@endif</span>
            </div>
            <span class="status-chip status-{{ $document->status }}">{{ $document->statusDisplay() }}</span>
        </div>
    @else
        <div class="supplier-invoice-preview-header">
            <div>
                <p class="document-pane-kicker">Supplier invoice file</p>
                <h3>Supplier invoice review</h3>
                <p>Check the uploaded invoice against the extracted details before approval.</p>
            </div>
            <span class="status-chip status-{{ $document->status }}">{{ $document->statusDisplay() }}</span>
        </div>

        <div class="supplier-invoice-summary-strip">
            <div>
                <span>Supplier</span>
                <strong>{{ $document->supplier?->name ?? 'Not selected' }}</strong>
            </div>
            <div>
                <span>Supplier invoice no.</span>
                <strong>{{ $document->external_reference ?: 'Not verified yet' }}</strong>
            </div>
            <div>
                <span>Invoice date</span>
                <strong>{{ $companyProfile->formatDate($document->issue_date) }}</strong>
            </div>
            <div>
                <span>Recorded total</span>
                <strong>{{ $companyProfile->formatMoney($document->total, $currency) }}</strong>
            </div>
        </div>
    @endif

    @if($invoiceAttachment)
        <section class="supplier-invoice-file-frame {{ $previewOnly ? 'supplier-invoice-file-frame-compact' : '' }}">
            @unless($previewOnly)
                <div class="supplier-invoice-file-toolbar">
                    <div class="min-w-0">
                        <strong>{{ $invoiceAttachment->original_name }}</strong>
                        <span>{{ strtoupper($invoiceAttachment->mime_type ?: 'file') }} · {{ number_format(($invoiceAttachment->size ?? 0) / 1024, 1) }} KB</span>
                    </div>
                    <div class="supplier-invoice-toolbar-actions">
                        @if($extraction?->status === 'verified')
                            <span class="supplier-invoice-extraction-state is-verified">Verified</span>
                        @elseif($extraction?->status === 'processed')
                            <span class="supplier-invoice-extraction-state is-ready">Ready to verify</span>
                        @elseif($extraction?->status === 'failed')
                            <span class="supplier-invoice-extraction-state is-failed">Review manually</span>
                        @else
                            <span class="supplier-invoice-extraction-state">Details not read yet</span>
                        @endif

                        @if($invoiceAttachment->canBeExtracted() && $canVerifyExtraction)
                            <form method="post" action="{{ route('attachments.extract', $invoiceAttachment) }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary min-h-9 px-3 py-1.5">{{ $extraction ? 'Re-run OCR' : 'Run OCR' }}</button>
                            </form>
                        @elseif(! $invoiceAttachment->canBeExtracted())
                            <a class="btn btn-secondary min-h-9 px-3 py-1.5" href="{{ route('attachments.download', $invoiceAttachment) }}">Download file</a>
                        @endif
                    </div>
                </div>
            @endunless

            @if($invoiceAttachment->isPdf())
                <iframe
                    class="supplier-invoice-pdf-viewer"
                    src="{{ route('attachments.preview', $invoiceAttachment) }}#toolbar=1&navpanes=0"
                    title="Supplier invoice PDF preview: {{ $invoiceAttachment->original_name }}"
                ></iframe>
            @elseif($invoiceAttachment->isImage())
                <div class="supplier-invoice-image-viewer">
                    <img src="{{ route('attachments.preview', $invoiceAttachment) }}" alt="Supplier invoice image preview: {{ $invoiceAttachment->original_name }}">
                </div>
            @else
                <div class="supplier-invoice-empty-preview">
                    <h4>Invoice file is saved</h4>
                    <p>This file cannot be previewed in the browser. Download it, check the supplier invoice details, then verify the fields manually.</p>
                    <a class="btn btn-secondary mt-3" href="{{ route('attachments.download', $invoiceAttachment) }}">Download invoice file</a>
                </div>
            @endif
        </section>

        @unless($previewOnly)
        <section class="supplier-invoice-extraction-panel">
            <div class="supplier-invoice-extraction-heading">
                <div>
                    <p class="document-pane-kicker">Verification</p>
                    <h4>Supplier invoice details</h4>
                </div>
                @if($extraction?->verified_at)
                    <span>{{ $verificationMethodLabel }} · {{ $extraction->verified_at->format($companyProfile->dateFormat()) }}, {{ $extraction->verified_at->format('g:i A') }}</span>
                @endif
            </div>

            @if(! $extraction)
                <p class="supplier-invoice-extraction-note">Run OCR when the file is readable. If OCR is not available or the scan is unclear, verify the invoice details manually below.</p>
            @elseif($extraction->status === 'failed')
                <div class="supplier-invoice-extraction-error">
                    <strong>OCR could not read this file.</strong>
                    <span>{{ $extraction->error_message ?: 'Verify the invoice details manually, or re-run OCR after replacing the file.' }}</span>
                </div>
            @else
                <form method="post" action="{{ route('attachment-extractions.verify', $extraction) }}" class="supplier-invoice-extraction-form">
                    @csrf
                    @method('PUT')
                    @foreach($fieldLabels as $key => $label)
                        <label>
                            <span>{{ $label }}</span>
                            <input
                                class="form-input"
                                name="fields[{{ $key }}]"
                                value="{{ old('fields.'.$key, $verifiedFields[$key] ?? $extractedFields[$key] ?? '') }}"
                                @if($extraction->status === 'verified') readonly @endif
                            >
                        </label>
                    @endforeach
                    <label class="flex items-start gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" name="supplier_confirmed" value="1" class="mt-1" @checked(old('supplier_confirmed', $extraction->supplier_confirmed)) @disabled($extraction->status === 'verified')>
                        <span>Supplier on the invoice matches this supplier record.</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" name="recorded_total_confirmed" value="1" class="mt-1" @checked(old('recorded_total_confirmed', $extraction->recorded_total_confirmed)) @disabled($extraction->status === 'verified')>
                        <span>Recorded total has been checked against the invoice copy.</span>
                    </label>
                    <label>
                        <span>Verification notes</span>
                        <textarea class="form-input" name="verification_notes" @if($extraction->status === 'verified') readonly @endif>{{ old('verification_notes', $extraction->verification_notes) }}</textarea>
                    </label>

                    @if($canVerifyExtraction && $extraction->status !== 'verified')
                        <button type="submit" class="btn btn-primary supplier-invoice-verify-button">Verify invoice details</button>
                    @endif
                </form>

                @if(filled($extraction->raw_text))
                    <details class="supplier-invoice-ocr-text">
                        <summary>Extracted text from file</summary>
                        <pre>{{ \Illuminate\Support\Str::limit($extraction->raw_text, 2500) }}</pre>
                    </details>
                @endif
            @endif

            @if($canVerifyExtraction && $extraction?->status !== 'verified')
                <div class="supplier-invoice-extraction-error">
                    <strong>Manual verification fallback</strong>
                    <span>Use this when OCR is unavailable, failed, or the extracted details are not reliable. Manual review requires notes.</span>
                </div>
                <form method="post" action="{{ route('documents.supplier-invoice-verification.verify', $document) }}" class="supplier-invoice-extraction-form">
                    @csrf
                    @method('PUT')
                    <label>
                        <span>Verification method</span>
                        <select class="form-input" name="verification_method">
                            <option value="manual" @selected(old('verification_method', 'manual') === 'manual')>Manual review</option>
                            <option value="external" @selected(old('verification_method') === 'external')>Uploaded file review</option>
                        </select>
                    </label>
                    @foreach($fieldLabels as $key => $label)
                        <label>
                            <span>{{ $label }}</span>
                            <input
                                class="form-input"
                                name="fields[{{ $key }}]"
                                value="{{ old('fields.'.$key, $manualFields[$key] ?? '') }}"
                            >
                        </label>
                    @endforeach
                    <label class="flex items-start gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" name="supplier_confirmed" value="1" class="mt-1" @checked(old('supplier_confirmed'))>
                        <span>Supplier on the invoice matches this supplier record.</span>
                    </label>
                    <label class="flex items-start gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" name="recorded_total_confirmed" value="1" class="mt-1" @checked(old('recorded_total_confirmed'))>
                        <span>Recorded total has been checked against the invoice copy.</span>
                    </label>
                    <label>
                        <span>Verification notes</span>
                        <textarea class="form-input" name="verification_notes" required>{{ old('verification_notes') }}</textarea>
                    </label>
                    <button type="submit" class="btn btn-primary supplier-invoice-verify-button">Verify invoice details</button>
                </form>
            @endif
        </section>
        @endunless
    @else
        <section class="supplier-invoice-empty-preview">
            <h4>No supplier invoice file uploaded yet</h4>
            <p>Upload the supplier invoice PDF or image as <strong>Invoice copy</strong>. The file will appear here for OCR, human verification, matching, and approval.</p>
            @if($attachments->isNotEmpty())
                <p class="mt-2">This record has attachments, but none can be previewed as PDF or image files.</p>
            @endif
        </section>
    @endif

    @if(! $previewOnly && $supportingAttachments->isNotEmpty())
        <div class="supplier-invoice-attachment-list">
            <p>Supporting documents</p>
            <div>
                @foreach($supportingAttachments as $attachment)
                    @if($attachment->isPreviewable())
                        <a href="{{ route('attachments.preview', $attachment) }}" target="_blank" rel="noopener">{{ $attachment->original_name }}</a>
                    @else
                        <a href="{{ route('attachments.download', $attachment) }}">{{ $attachment->original_name }} · download only</a>
                    @endif
                @endforeach
            </div>
        </div>
    @endif

    @unless($previewOnly)
    <section class="supplier-invoice-match-summary">
        <h4>Matching summary</h4>
        <dl>
            <div>
                <dt>How created</dt>
                <dd>{{ $document->sourceTypeDisplay() }}</dd>
            </div>
            <div>
                <dt>Related document</dt>
                <dd>{{ $document->relatedDocument?->document_number ?? 'None' }}</dd>
            </div>
            <div>
                <dt>Payment terms</dt>
                <dd>{{ $document->paymentTermsDisplay() }}</dd>
            </div>
            <div>
                <dt>Balance</dt>
                <dd>{{ $companyProfile->formatMoney($document->balanceDue(), $currency) }}</dd>
            </div>
        </dl>
    </section>
    @endunless
</article>
