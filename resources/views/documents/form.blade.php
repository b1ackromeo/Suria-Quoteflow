@extends('layouts.app', [
    'title' => ($document->exists ? 'Edit ' : ($meta['type'] === 'customer_po' ? 'Record ' : ($meta['type'] === 'supplier_po' ? 'Create ' : 'New '))).$meta['singular'],
    'contentMode' => 'fullscreen',
])

@php
    $rows = old('items');
    if (! $rows) {
        $rows = ($document->exists || ($document->relationLoaded('items') && $document->items->isNotEmpty()))
            ? $document->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'wbs_item_id' => $item->wbs_item_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'tax_rate' => $item->tax_rate,
            ])->toArray()
            : [[
                'product_id' => '',
                'wbs_item_id' => '',
                'description' => '',
                'quantity' => $meta['type'] === 'goods_receipt' ? 0 : 1,
                'unit' => 'unit',
                'unit_price' => 0,
                'tax_rate' => 0,
            ]];
    }

    $billingRows = old('billing_stages');
    if (! $billingRows) {
        $billingRows = ($document->exists || ($document->relationLoaded('billingStages') && $document->billingStages->isNotEmpty()))
            ? $document->billingStages->map(fn ($stage) => [
                'stage_name' => $stage->stage_name,
                'condition_label' => $stage->condition_label,
                'percentage' => $stage->percentage,
                'amount' => $stage->amount,
                'payment_term' => $stage->payment_term,
                'previously_invoiced' => $stage->previously_invoiced,
                'current_invoice' => $stage->current_invoice,
                'remaining_amount' => $stage->remaining_amount,
                'is_current' => $stage->is_current ? '1' : '0',
            ])->toArray()
            : [['stage_name' => '', 'condition_label' => '', 'percentage' => '', 'amount' => '', 'payment_term' => '', 'previously_invoiced' => '', 'current_invoice' => '', 'remaining_amount' => '', 'is_current' => '0']];
    }

    $isInvoiceDocument = in_array($meta['type'], ['customer_invoice', 'supplier_invoice'], true);
    $isPoDocument = in_array($meta['type'], ['customer_po', 'supplier_po'], true);
    $isSupplierPo = $meta['type'] === 'supplier_po';
    $isGoodsReceipt = $meta['type'] === 'goods_receipt';
    $isPurchaseRequest = $meta['type'] === 'purchase_request';
    $isCustomerQuotation = $meta['type'] === 'customer_quotation';
    $isSupplierQuotation = $meta['type'] === 'supplier_quotation';
    $isQuotationDocument = in_array($meta['type'], ['customer_quotation', 'supplier_quotation'], true);
    $usesDocumentStudio = true;
    $companyProfile = \App\Models\CompanyProfile::active();
    $documentCurrency = strtoupper((string) old('currency', $document->currency ?: $companyProfile->baseCurrency()));
    $currencySymbols = \App\Models\CompanyProfile::currencySymbols();
    $sourceOptions = match ($meta['type']) {
        'customer_po' => [
            'quotation' => ['title' => 'Approved quotation', 'copy' => 'PO follows approved quotation.'],
            'direct_customer_po' => ['title' => 'Direct PO', 'copy' => 'No quotation required. Add a reason.'],
        ],
        'customer_invoice' => [
            'customer_po' => ['title' => 'Customer PO', 'copy' => 'Bill against recorded customer PO.'],
            'progress_claim' => ['title' => 'Progress claim', 'copy' => 'Current billing stage.'],
            'direct_invoice' => ['title' => 'Direct invoice', 'copy' => 'No recorded PO. Add a reason.'],
        ],
        'supplier_po' => [
            'supplier_quote' => ['title' => 'Supplier quotation', 'copy' => 'Order from accepted supplier quotation.'],
            'purchase_request' => ['title' => 'Purchase request', 'copy' => 'Order from approved internal request.'],
            'direct_supplier_po' => ['title' => 'Direct purchase order', 'copy' => 'No quotation or request. Add a reason.'],
        ],
        'supplier_quotation' => [
            'purchase_request' => ['title' => 'Purchase request', 'copy' => 'For an approved purchase request.'],
            'supplier_quote' => ['title' => 'Previous quotation', 'copy' => 'Record supplier revision.'],
        ],
        'purchase_request' => [
            'supplier_quote' => ['title' => 'Supplier quotation upload', 'copy' => 'Upload the quotation. Review extracted details after save.'],
            'quote_exception' => ['title' => 'Quotation exception', 'copy' => 'No supplier quotation. Add a reason and enter lines.'],
        ],
        'goods_receipt' => [
            'supplier_po' => ['title' => 'Issued purchase order', 'copy' => 'Normal receiving path.'],
            'direct_receipt' => ['title' => 'Direct receipt', 'copy' => 'No PO yet. Add a reason.'],
        ],
        'supplier_invoice' => [
            'goods_receipt' => ['title' => 'Receipt', 'copy' => 'Match accepted goods or service.'],
            'supplier_po' => ['title' => 'Purchase order', 'copy' => 'Match directly to the PO.'],
            'direct_supplier_invoice' => ['title' => 'Direct supplier invoice', 'copy' => 'No PO or receipt required. Add a reason.'],
        ],
        default => [],
    };
    $selectedSourceType = old('source_type', $document->source_type ?: array_key_first($sourceOptions));
    $conditionHeading = $isPoDocument
        ? ($isSupplierPo ? 'Supplier may invoice when' : 'Customer may be invoiced when')
        : ($isQuotationDocument ? 'Billing condition' : 'Invoice condition');
    $notesLabel = match (true) {
        $isCustomerQuotation => 'Project scope summary',
        $isSupplierQuotation => 'Supplier quotation summary',
        default => 'Notes',
    };
    $referenceLabel = match (true) {
        $isCustomerQuotation => 'Customer inquiry / reference',
        $isSupplierQuotation => 'Supplier quotation no. / reference',
        $isGoodsReceipt => 'Delivery order reference',
        default => 'Reference',
    };
    $referencePlaceholder = match ($meta['type']) {
        'customer_quotation' => 'Inquiry number, RFQ, email subject',
        'supplier_quotation' => 'Supplier quotation number, RFQ, or email subject',
        'customer_po' => 'PO number received from customer',
        'customer_invoice' => 'PO, delivery, or billing reference',
        'supplier_po' => 'Supplier quotation, purchase request, or supplier reference',
        'goods_receipt' => 'Delivery order no., packing list no., or receiving reference',
        'supplier_invoice' => 'Supplier invoice number or matching reference',
        default => 'Reference number or email subject',
    };
    $relatedLabel = match ($meta['type']) {
        'customer_quotation' => 'Previous quotation / revision',
        'customer_po' => 'Related quotation',
        'customer_invoice' => 'Related PO / quotation',
        'supplier_quotation' => 'Purchase request / previous supplier quotation',
        'supplier_po' => 'Purchase request / supplier quotation',
        'goods_receipt' => 'Issued purchase order',
        'supplier_invoice' => 'Receipt / purchase order',
        default => 'Related document',
    };
    $validityLabel = match (true) {
        $isQuotationDocument => 'Valid until',
        $meta['type'] === 'customer_po' => 'Completion target',
        $isSupplierPo => 'Delivery date',
        $isGoodsReceipt => 'Received date',
        default => 'Due date',
    };
    $issueDateLabel = match (true) {
        $meta['type'] === 'customer_po' => 'Date received',
        $meta['type'] === 'supplier_po' => 'PO date',
        $isInvoiceDocument => 'Invoice date',
        $isQuotationDocument => 'Quotation date',
        $isGoodsReceipt => 'Received date',
        default => 'Issue date',
    };
    $paymentPanelTitle = $isQuotationDocument ? 'Commercial terms' : ($isGoodsReceipt ? 'Receipt controls' : 'Payment and billing');
    $paymentPanelSubtitle = match (true) {
        $isCustomerQuotation => 'Use simple terms. Add milestones for staged billing.',
        $isSupplierQuotation => 'Record supplier payment terms. Add milestones for staged invoices.',
        $isPoDocument => 'Use fixed terms or milestones for staged delivery.',
        $isGoodsReceipt => 'Receipt records quantity only. Attach evidence after save.',
        $meta['type'] === 'supplier_invoice' => 'Record supplier terms. Use milestones when billed by stage.',
        $isInvoiceDocument => 'Use fixed days or milestones for staged billing.',
        default => 'Use fixed terms or milestones for staged work.',
    };
    $summaryTotalLabel = $isQuotationDocument ? 'Quotation total' : ($isSupplierPo ? 'PO total' : ($isGoodsReceipt ? 'Received quantity' : ($meta['type'] === 'supplier_invoice' ? 'Recorded total' : ($isInvoiceDocument ? 'Invoice total' : 'Document total'))));
    $showBillingStages = old('payment_terms_type', $document->payment_terms_type ?? 'standard') === 'milestone' || ($isInvoiceDocument && $document->billingStages->isNotEmpty());
    $documentTaxRate = old('document_tax_rate');
    if ($documentTaxRate === null) {
        $documentTaxRate = $document->exists ? ($document->items->first()?->tax_rate ?? 0) : 0;
    }
    $documentNoun = match (true) {
        $isQuotationDocument => 'quotation',
        $meta['type'] === 'customer_po' => 'customer PO',
        $meta['type'] === 'supplier_po' => 'purchase order',
        $isGoodsReceipt => 'receipt record',
        $isPurchaseRequest => 'purchase request',
        $meta['type'] === 'supplier_invoice' => 'supplier invoice record',
        $isInvoiceDocument => 'invoice',
        default => 'document',
    };
    $studioAction = match (true) {
        $document->exists => 'Edit '.$documentNoun,
        $isSupplierQuotation => 'New supplier quotation',
        $meta['type'] === 'customer_po' => 'Record customer PO',
        $meta['type'] === 'supplier_po' => 'Create purchase order',
        $isGoodsReceipt => 'Record goods receipt',
        default => 'New '.$documentNoun,
    };
    $studioHeroCopy = match (true) {
        $isCustomerQuotation => 'Prepare pricing, validity, items, and scope.',
        $isSupplierQuotation => 'Record supplier pricing, validity, and terms before PO.',
        $meta['type'] === 'customer_po' => 'Record PO details, linked quotation, dates, and site.',
        $meta['type'] === 'supplier_po' => 'Create from an approved record or a direct exception.',
        $isGoodsReceipt => 'Record received goods or accepted services from the PO.',
        $isPurchaseRequest => 'Upload supplier quotation, then review extracted details.',
        $meta['type'] === 'supplier_invoice' => 'Record invoice details. Upload the supplier file after saving.',
        $isInvoiceDocument => 'Enter billing details, terms, items, and progress.',
        default => 'Enter details and check the preview before saving.',
    };
    $sourceStepTitle = match ($meta['type']) {
        'customer_quotation' => 'Customer request',
        'supplier_quotation' => 'Quotation basis',
        'purchase_request' => 'Supplier quotation',
        'customer_po' => 'Customer PO',
        'supplier_po' => 'Order basis',
        'customer_invoice' => 'Billing basis',
        'goods_receipt' => 'Purchase order',
        'supplier_invoice' => 'Matching basis',
        default => 'Linked record',
    };
    $stepOneTitle = match ($meta['type']) {
        'customer_quotation' => 'Customer and quotation details',
        'supplier_quotation' => 'Supplier and quotation details',
        'purchase_request' => 'Supplier and request details',
        'customer_po' => 'Customer PO details',
        'supplier_po' => 'Supplier and PO details',
        'goods_receipt' => 'Supplier and receipt details',
        'customer_invoice' => 'Customer and invoice details',
        'supplier_invoice' => 'Supplier and invoice details',
        default => 'Document details',
    };
    $stepOneCopy = match (true) {
        $isCustomerQuotation => 'Customer, reference, validity, site, and delivery.',
        $isSupplierQuotation => 'Supplier, quotation reference, validity, site, and delivery.',
        $meta['type'] === 'customer_po' => 'Customer, PO reference, dates, site, and service location.',
        $meta['type'] === 'supplier_po' => 'Supplier, PO dates, site, and ship-to details.',
        $isGoodsReceipt => 'Supplier, PO, evidence reference, date, and location.',
        $isPurchaseRequest => 'Supplier if known, reference, required date, site, and delivery.',
        $meta['type'] === 'supplier_invoice' => 'Supplier, invoice number, dates, site, and matching basis.',
        $isInvoiceDocument => 'Billing party, reference, invoice date, due date, and site.',
        default => 'Party, reference, dates, site, and location.',
    };
    $stepTwoTitle = match ($meta['type']) {
        'customer_quotation', 'supplier_quotation' => 'Pricing and terms',
        'purchase_request' => 'Budget and terms',
        'customer_po' => 'Billing terms',
        'supplier_po' => 'Order value and terms',
        'goods_receipt' => 'Receipt evidence',
        'customer_invoice', 'supplier_invoice' => 'Payment terms',
        default => 'Value and terms',
    };
    $stepThreeTitle = match ($meta['type']) {
        'customer_quotation', 'supplier_quotation' => 'Quoted items',
        'purchase_request' => 'Requested items',
        'customer_po' => 'Customer PO items',
        'supplier_po' => 'Order items',
        'goods_receipt' => 'Received items',
        'customer_invoice', 'supplier_invoice' => 'Invoice lines',
        default => 'Line items',
    };
    $stepThreeCopy = match (true) {
        $isCustomerQuotation => 'Add quoted items. Tax is set at document level.',
        $isSupplierQuotation => 'Record supplier quoted items. Tax is set at document level.',
        $isPoDocument => 'Add ordered items. Tax is set at document level.',
        $isGoodsReceipt => 'Confirm received, short, or rejected quantities. No pricing here.',
        $meta['type'] === 'supplier_invoice' => 'Record billed lines for verification and matching.',
        $isInvoiceDocument => 'Add billable items and current progress stage if needed.',
        default => 'Add products or services.',
    };
    $stepFourTitle = match ($meta['type']) {
        'customer_quotation' => 'Scope and terms',
        'supplier_quotation' => 'Supplier notes and terms',
        'purchase_request' => 'Justification and notes',
        'customer_po' => 'Delivery notes',
        'supplier_po' => 'Delivery terms',
        'goods_receipt' => 'Receiving notes',
        'customer_invoice', 'supplier_invoice' => 'Invoice notes',
        default => 'Notes',
    };
    $stepFourCopy = match (true) {
        $isCustomerQuotation => 'Add scope, assumptions, exclusions, and terms.',
        $isSupplierQuotation => 'Record supplier remarks, exclusions, and terms.',
        $meta['type'] === 'customer_po' => 'Add delivery instructions, service notes, and terms.',
        $meta['type'] === 'supplier_po' => 'Add delivery instructions, documents, and PO terms.',
        $isGoodsReceipt => 'Add receiving remarks. Upload evidence after saving.',
        $meta['type'] === 'supplier_invoice' => 'Add matching notes, disputes, payment instructions, or terms.',
        $isInvoiceDocument => 'Add billing notes, references, payment instructions, and terms.',
        default => 'Add notes, instructions, and terms.',
    };
    $livePreviewTitle = match (true) {
        $isCustomerQuotation => 'Customer quotation preview',
        $isSupplierQuotation => 'Supplier quotation preview',
        $meta['type'] === 'customer_po' => 'Customer PO preview',
        $meta['type'] === 'supplier_po' => 'Purchase order preview',
        $isGoodsReceipt => 'Goods receipt preview',
        $meta['type'] === 'supplier_invoice' => 'Supplier invoice file',
        $meta['type'] === 'customer_invoice' => 'Customer invoice preview',
        default => 'Document preview',
    };
    $previewReferenceFallback = match (true) {
        $isCustomerQuotation => 'Inquiry / RFQ reference',
        $isSupplierQuotation => 'Supplier quotation reference',
        $meta['type'] === 'customer_po' => 'PO number received from customer',
        $meta['type'] === 'supplier_po' => 'Supplier quotation / purchase request reference',
        $isGoodsReceipt => 'Delivery order reference',
        $meta['type'] === 'supplier_invoice' => 'Supplier invoice no. / delivery reference',
        $isInvoiceDocument => 'PO / delivery / billing reference',
        default => 'Reference',
    };
    $previewScopeFallback = match (true) {
        $isCustomerQuotation => 'Project scope summary will appear here.',
        $isSupplierQuotation => 'Supplier quotation summary will appear here.',
        $meta['type'] === 'customer_po' => 'Received PO notes, delivery instructions, or service requirements will appear here.',
        $meta['type'] === 'supplier_po' => 'Order notes or delivery instructions will appear here.',
        $isGoodsReceipt => 'Receiving, inspection, shortage, rejection, or damage remarks will appear here.',
        $meta['type'] === 'supplier_invoice' => 'Supplier invoice notes or matching remarks will appear here.',
        $isInvoiceDocument => 'Billing notes will appear here.',
        default => 'Notes will appear here.',
    };
    $previewSecondaryDateFallback = $isQuotationDocument ? 'Valid until' : 'Not set';
    $sourceQuestion = match ($meta['type']) {
        'customer_po' => 'How was this customer PO received?',
        'customer_quotation' => 'Link customer request',
        'supplier_quotation' => 'What is this supplier quotation for?',
        'supplier_po' => 'Select order basis',
        'purchase_request' => 'Upload supplier quotation',
        'customer_invoice' => 'Select billing basis',
        'goods_receipt' => 'Select issued purchase order',
        'supplier_invoice' => 'Select matching basis',
        default => 'Select linked record',
    };
    $sourceNoteLabel = match ($meta['type']) {
        'customer_po', 'supplier_po', 'purchase_request', 'customer_invoice', 'goods_receipt', 'supplier_invoice' => 'Reason / note',
        'supplier_quotation' => 'Optional note',
        default => 'Supporting note',
    };
    $sourceNotePlaceholder = match ($meta['type']) {
        'customer_po' => 'Example: Customer sent PO directly under existing rate card; quotation not required.',
        'supplier_po' => 'Example: Urgent replacement part approved by manager before supplier quotation was received.',
        'purchase_request' => 'Required only for quotation exception. Example: urgent purchase approved before a supplier quotation was available.',
        'customer_invoice' => 'Example: Customer approved billing by email; no PO is required for this job.',
        'goods_receipt' => 'Example: Delivery arrived before PO was available; manager approved direct receipt.',
        'supplier_invoice' => 'Example: Utility bill does not require PO or receipt matching; approved by finance lead.',
        default => 'Add any note that helps the next user understand this record.',
    };
    $sourceNoteHelper = match ($meta['type']) {
        'supplier_quotation' => 'Optional. Add context for the purchase request or revised supplier quotation.',
        'customer_po' => 'Required for direct customer PO. Explain why no quotation is used.',
        'supplier_po' => 'Required for direct purchase order. Explain why no supplier quotation or purchase request is used.',
        'purchase_request' => 'Required for quotation exception. Normal purchase requests should upload the supplier quotation first.',
        'customer_invoice' => 'Required for direct invoice. Explain why billing is allowed without a recorded PO.',
        'goods_receipt' => 'Required for direct receipt. Normal receiving must use an issued purchase order.',
        'supplier_invoice' => 'Required for direct supplier invoice. Explain why no PO or receipt is required.',
        default => 'Add a note when this record does not follow the normal chain.',
    };
    $isPrQuoteUploadCreate = $isPurchaseRequest && ! $document->exists && $selectedSourceType === 'supplier_quote';
    $exceptionSourceTypes = ['direct_customer_po', 'direct_invoice', 'direct_supplier_po', 'direct_receipt', 'direct_supplier_invoice', 'quote_exception'];
    $sourceNoteValue = old('source_note', $document->source_note);
    $needsSourceReason = in_array($selectedSourceType, $exceptionSourceTypes, true);
    $sourceReasonReady = ! $needsSourceReason || filled($sourceNoteValue);
    $sourceReadiness = $sourceOptions === []
        ? 'Standard document'
        : ($sourceOptions[$selectedSourceType]['title'] ?? 'Choose path');
    $partyReady = match ($meta['party']) {
        'customer' => filled(old('customer_id', $document->customer_id)),
        'supplier' => filled(old('supplier_id', $document->supplier_id)),
        default => true,
    };
    $partyReadiness = match ($meta['party']) {
        'customer' => $partyReady ? 'Customer selected' : 'Select customer',
        'supplier' => $partyReady ? 'Supplier selected' : 'Select supplier',
        default => filled(old('supplier_id', $document->supplier_id)) ? 'Supplier selected' : 'Supplier optional',
    };
    $formLineCount = collect($rows)
        ->filter(fn ($row) => filled($row['description'] ?? null) || filled($row['product_id'] ?? null))
        ->count();
    $lineReadiness = $isPrQuoteUploadCreate
        ? 'Lines after OCR review'
        : ($formLineCount > 0 ? $formLineCount.' line'.($formLineCount === 1 ? '' : 's').' ready' : 'Add line items');
    $evidenceReadiness = match (true) {
        $isPrQuoteUploadCreate => 'Quotation file required',
        $isPurchaseRequest => $needsSourceReason ? 'Reason required' : 'Supplier quotation route',
        $isGoodsReceipt => 'Evidence after save',
        $meta['type'] === 'supplier_invoice' => 'Supplier file after save',
        default => 'Preview updates live',
    };
    $formReadinessItems = [
        ['label' => 'Path', 'value' => $sourceReadiness, 'state' => $sourceOptions === [] || filled($selectedSourceType) ? 'ready' : 'waiting'],
        ['label' => 'Reason', 'value' => $needsSourceReason ? ($sourceReasonReady ? 'Reason added' : 'Reason required') : 'Normal path', 'state' => $sourceReasonReady ? 'ready' : 'attention'],
        ['label' => 'Party', 'value' => $partyReadiness, 'state' => $partyReady ? 'ready' : 'attention'],
        ['label' => 'Items', 'value' => $lineReadiness, 'state' => ($formLineCount > 0 || $isPrQuoteUploadCreate) ? 'ready' : 'attention'],
        ['label' => 'Evidence', 'value' => $evidenceReadiness, 'state' => $isPrQuoteUploadCreate ? 'attention' : 'ready'],
    ];
@endphp

@section('content')
@if($usesDocumentStudio)
    <div
        class="fullscreen-workspace quotation-form-workspace @if($isGoodsReceipt) receiving-form-workspace @endif"
        data-document-form-workspace
        @if($isQuotationDocument) data-quotation-form-workspace @endif
    >
@endif

<form
    method="post"
    action="{{ $document->exists ? route('documents.update', $document) : route('documents.store', $meta['slug']) }}"
    class="{{ $usesDocumentStudio ? 'workspace-pane document-studio-form min-w-0 space-y-4' : 'min-w-0 space-y-6' }} @if($isGoodsReceipt) receiving-record-form @endif"
    @if($isPurchaseRequest && ! $document->exists) enctype="multipart/form-data" data-pr-create-form @endif
>
    <input type="hidden" name="_token" value="{{ csrf_token() }}" hidden>
    @if($document->exists)
        <input type="hidden" name="_method" value="put" hidden>
    @endif

    @if($usesDocumentStudio)
        <div class="workspace-pane-header">
            <div class="studio-hero">
                <div class="flex min-w-0 flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wide text-[#0a4f93]">{{ $studioAction }}</p>
                        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950">{{ $meta['singular'] }}</h2>
                        <p class="mt-2 max-w-2xl text-sm font-semibold leading-6 text-slate-500">{{ $studioHeroCopy }}</p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <a class="btn btn-secondary" href="{{ $document->exists ? route('documents.show', $document) : route('documents.index', $meta['slug']) }}">Cancel</a>
                        @unless($isPurchaseRequest && ! $document->exists)
                            <button class="btn btn-primary" type="submit">Save {{ $documentNoun }}</button>
                        @endunless
                    </div>
                </div>
                <nav class="studio-stepper" aria-label="Document form sections">
                    @foreach([$sourceStepTitle, $stepOneTitle, $stepTwoTitle, $stepThreeTitle, $stepFourTitle] as $stepLabel)
                        <span class="studio-step {{ $loop->first ? 'studio-step-active' : '' }}">
                            <span class="studio-step-number">Step {{ $loop->iteration }}</span>
                            <span class="studio-step-label">{{ $stepLabel }}</span>
                        </span>
                    @endforeach
                </nav>
                <div class="studio-readiness-strip" aria-label="Document readiness" data-form-readiness-summary>
                    <div class="studio-readiness-heading">
                        <span>Document readiness</span>
                        <strong>{{ $document->exists ? 'Editing' : 'Before save' }}</strong>
                    </div>
                    @foreach($formReadinessItems as $item)
                        <div class="studio-readiness-item studio-readiness-{{ $item['state'] }}">
                            <span>{{ $item['label'] }}</span>
                            <strong>{{ $item['value'] }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if($sourceOptions !== [])
        <section class="studio-source-panel @if($isGoodsReceipt) receiving-source-panel @endif" aria-labelledby="source-workflow-heading">
            <div>
                <p class="studio-section-kicker">{{ $sourceStepTitle }}</p>
                <h2 id="source-workflow-heading" class="studio-section-title">{{ $isGoodsReceipt ? 'Start with the issued purchase order' : $sourceQuestion }}</h2>
                <p class="studio-section-copy">
                    @if($isGoodsReceipt)
                        Normal receiving uses an issued supplier PO. Direct receipt needs a reason.
                    @elseif($meta['type'] === 'supplier_po')
                        Choose the approved record. Direct purchase order needs a reason.
                    @elseif($meta['type'] === 'supplier_quotation')
                        Link to a purchase request or earlier supplier quotation.
                    @elseif($meta['type'] === 'supplier_invoice')
                        Match to receipt, PO, or direct exception with reason.
                    @elseif($meta['type'] === 'customer_po')
                        Link to quotation, or record direct PO with reason.
                    @elseif($meta['type'] === 'customer_invoice')
                        Bill from PO, progress claim, or direct exception with reason.
                    @else
                        Choose the record or exception that explains this document.
                    @endif
                </p>
            </div>
            <div class="@if($isGoodsReceipt) mt-4 grid gap-3 lg:grid-cols-[minmax(0,1fr)_18rem] @else source-choice-grid @endif">
                <div class="@if($isGoodsReceipt) grid gap-3 sm:grid-cols-2 @else contents @endif">
                    @foreach($sourceOptions as $value => $option)
                        <label class="source-choice-card @if($isGoodsReceipt) min-h-0 @endif">
                            <input class="source-choice-input" type="radio" name="source_type" value="{{ $value }}" @checked($selectedSourceType === $value) @if($isPurchaseRequest) data-pr-source-mode @endif>
                            <span>
                                <span class="source-choice-title">{{ $option['title'] }}</span>
                                <span class="source-choice-copy">{{ $option['copy'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
            @if($isPurchaseRequest && ! $document->exists)
                <div class="purchase-request-ocr-panel" data-pr-quote-upload-panel>
                    <div class="min-w-0">
                        <p class="studio-section-kicker">Supplier quotation upload</p>
                        <h3>Create draft from supplier quotation</h3>
                        <p>Upload PDF or image. QuoteFlow creates the draft, runs OCR, then opens verification.</p>
                    </div>
                    <label class="form-label" data-pr-quote-upload-wrap>Supplier quotation PDF or image
                        <input class="form-input file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-bold file:text-slate-700 hover:file:bg-slate-200" type="file" name="source_attachment" accept=".pdf,.jpg,.jpeg,.png,.webp,.bmp,.tif,.tiff" data-pr-source-attachment @required($isPrQuoteUploadCreate) @disabled(! $isPrQuoteUploadCreate)>
                        <span class="mt-1 block text-xs font-semibold text-slate-500">Lines are created after quotation evidence is verified.</span>
                    </label>
                    <div class="flex flex-wrap items-center gap-3">
                        <button class="btn btn-primary" type="submit" data-pr-primary-submit @disabled($isPrQuoteUploadCreate)>Create draft & run OCR</button>
                        <span class="text-xs font-semibold text-slate-500" data-pr-submit-help>Choose a supplier quotation file to enable OCR.</span>
                    </div>
                </div>
            @endif
            <label class="form-label">{{ $sourceNoteLabel }}
                <textarea class="form-input {{ $isGoodsReceipt ? 'min-h-16' : 'min-h-24' }}" name="source_note" placeholder="{{ $sourceNotePlaceholder }}" @if($isPurchaseRequest) data-pr-source-note @endif>{{ old('source_note', $document->source_note) }}</textarea>
                <span class="mt-1 block text-xs font-semibold text-slate-500">{{ $sourceNoteHelper }}</span>
            </label>
        </section>
    @endif

    <section class="{{ $usesDocumentStudio ? 'studio-section' : 'panel space-y-5' }}">
        <div class="{{ $usesDocumentStudio ? 'studio-section-header' : '' }}">
                <div>
                @if($usesDocumentStudio)
                    @unless($isGoodsReceipt)
                        <p class="studio-section-kicker">Step 1</p>
                    @endunless
                    <h2 class="studio-section-title">{{ $stepOneTitle }}</h2>
                    <p class="studio-section-copy">{{ $stepOneCopy }}</p>
                @else
                    <h2 class="panel-title">Document Details</h2>
                    <p class="panel-subtitle">Enter the commercial details that will appear on the customer or supplier document.</p>
                @endif
                </div>
        </div>

        <div class="grid gap-4 {{ $isGoodsReceipt ? 'md:grid-cols-2 xl:grid-cols-3' : 'md:grid-cols-3' }}">
            @if($meta['party'] === 'customer')
                <label class="form-label">Customer
                    <select class="form-input" name="customer_id" data-party-select required>
                        <option value="">Select customer</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('customer_id', $document->customer_id) === $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </label>
            @elseif(str_starts_with($meta['party'], 'supplier'))
                <label class="form-label">Supplier
                    <select class="form-input" name="supplier_id" data-party-select @required($meta['party'] === 'supplier')>
                        <option value="">Select supplier</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $document->supplier_id) === $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="form-label"><span @if($isGoodsReceipt) data-receipt-label="referenceInputLabel" @endif>{{ $referenceLabel }}</span>
                <input class="form-input" name="external_reference" value="{{ old('external_reference', $document->external_reference) }}" placeholder="{{ $referencePlaceholder }}">
            </label>
            <label class="form-label">{{ $relatedLabel }}
                <select class="form-input" name="related_document_id" data-related-document-select data-party-label="{{ $meta['party'] === 'customer' ? 'customer' : 'supplier' }}">
                    <option value="" data-empty-option>None</option>
                    @foreach($relatedDocuments as $related)
                        <option
                            value="{{ $related->id }}"
                            data-party-id="{{ $related->customer_id ?? $related->supplier_id }}"
                            data-project-id="{{ $related->project_id }}"
                            @selected((int) old('related_document_id', $document->related_document_id) === $related->id)
                        >{{ $related->document_number }} · {{ $related->partyName() }}</option>
                    @endforeach
                </select>
            </label>
            <label class="form-label">Project / job
                <select class="form-input" name="project_id" data-project-select>
                    <option value="">No project</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected((int) old('project_id', $document->project_id) === $project->id)>{{ $project->project_code }} - {{ $project->name }}</option>
                    @endforeach
                </select>
                <span class="mt-1 block text-xs font-semibold text-slate-500">Optional for jobs that need budget, margin, or document grouping.</span>
                <span class="mt-1 block text-xs font-semibold text-slate-500">Work item choices appear after a project is selected.</span>
            </label>
            @if($isGoodsReceipt)
                <label class="form-label">Record type
                    <select class="form-input" data-receipt-mode>
                        <option value="goods">Material / goods receipt</option>
                        <option value="service">Service acceptance</option>
                        <option value="mixed">Mixed goods and service</option>
                    </select>
                </label>
            @endif
            <label class="form-label"><span @if($isGoodsReceipt) data-receipt-label="dateInputLabel" @endif>{{ $issueDateLabel }}</span>
                <input class="form-input" type="date" name="issue_date" data-issue-date value="{{ old('issue_date', optional($document->issue_date)->format('Y-m-d') ?? now()->toDateString()) }}" required>
            </label>
            @if($isGoodsReceipt)
                <input type="hidden" name="due_date" data-due-date value="{{ old('due_date', optional($document->due_date)->format('Y-m-d')) }}">
                <input type="hidden" name="currency" value="{{ $documentCurrency }}">
            @else
                <label class="form-label">{{ $validityLabel }}
                    <input class="form-input" type="date" name="due_date" data-due-date value="{{ old('due_date', optional($document->due_date)->format('Y-m-d')) }}">
                </label>
                <label class="form-label">Currency
                    <input class="form-input uppercase" name="currency" maxlength="3" value="{{ $documentCurrency }}" required>
                </label>
            @endif
            <label class="form-label">Project / site note
                <input class="form-input" name="project_name" value="{{ old('project_name', $document->project_name) }}" placeholder="Cyberjaya Site">
            </label>
        </div>

        <label class="form-label"><span @if($isGoodsReceipt) data-receipt-label="locationInputLabel" @endif>{{ $isGoodsReceipt ? 'Receiving location' : 'Delivery / service location' }}</span>
            <textarea class="form-input min-h-28" name="delivery_to" placeholder="{{ $isGoodsReceipt ? 'Warehouse, project site, store, or handover location' : 'Site address, delivery contact, handover location' }}">{{ old('delivery_to', $document->delivery_to) }}</textarea>
        </label>
    </section>

    @if($isGoodsReceipt)
        <input type="hidden" name="payment_terms_type" value="standard">
        <input type="hidden" name="payment_due_days" value="">
        <input type="hidden" name="payment_terms_label" value="">
        <input type="hidden" name="document_tax_rate" value="0">
        <section class="receiving-evidence-note">
            <div>
                <p class="studio-section-kicker">Evidence after save</p>
                <p class="mt-1 text-sm font-semibold text-slate-700">Upload delivery order, packing list, service report, UAT sign-off, photos, or handover proof on the saved goods receipt.</p>
            </div>
            <span class="status-chip status-draft">Required before invoice matching</span>
        </section>
    @else
    <section class="{{ $usesDocumentStudio ? 'studio-section' : 'panel space-y-5' }}">
        <div class="{{ $usesDocumentStudio ? 'studio-section-header' : '' }}">
            <div>
                @if($usesDocumentStudio)
                    <p class="studio-section-kicker">Step 2</p>
                    <h2 class="studio-section-title">{{ $stepTwoTitle }}</h2>
                    <p class="studio-section-copy">{{ $paymentPanelSubtitle }}</p>
                @else
                    <h2 class="panel-title">{{ $paymentPanelTitle }}</h2>
                    <p class="panel-subtitle">{{ $paymentPanelSubtitle }}</p>
                @endif
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <label class="form-label">Payment structure
                <select class="form-input" name="payment_terms_type" data-payment-structure>
                    <option value="standard" @selected(old('payment_terms_type', $document->payment_terms_type ?? 'standard') === 'standard')>{{ $isQuotationDocument ? 'Simple payment terms' : ($isPoDocument ? 'Fixed payment terms' : 'Fixed days after invoice') }}</option>
                    <option value="milestone" @selected(old('payment_terms_type', $document->payment_terms_type ?? 'standard') === 'milestone')>Milestone / progress billing</option>
                </select>
            </label>
            <label class="form-label">Payment term days
                <input class="form-input" type="number" min="0" max="365" name="payment_due_days" data-payment-days value="{{ old('payment_due_days', $document->payment_due_days) }}" placeholder="7">
            </label>
            <label class="form-label">Payment terms text
                <input class="form-input" name="payment_terms_label" data-payment-label value="{{ old('payment_terms_label', $document->payment_terms_label) }}" placeholder="7 days from invoice date">
            </label>
            <label class="form-label">Tax rate for totals (%)
                <input class="form-input" type="number" step="0.01" min="0" max="100" name="document_tax_rate" value="{{ $documentTaxRate }}" placeholder="8">
            </label>
        </div>

        @if($isInvoiceDocument)
            <div class="grid gap-4 md:grid-cols-3">
                <label class="form-label">Progress invoice no.
                    <input class="form-input" type="number" min="1" max="99" name="progress_invoice_number" value="{{ old('progress_invoice_number', $document->progress_invoice_number) }}" placeholder="2">
                </label>
                <label class="form-label">Total progress invoices
                    <input class="form-input" type="number" min="1" max="99" name="progress_invoice_total" value="{{ old('progress_invoice_total', $document->progress_invoice_total) }}" placeholder="3">
                </label>
                <label class="form-label">Current billing stage
                    <input class="form-input" name="billing_stage_name" value="{{ old('billing_stage_name', $document->billing_stage_name) }}" placeholder="Progress Claim 1">
                </label>
            </div>
        @endif

        <div class="{{ $showBillingStages ? '' : 'hidden' }} rounded-lg border border-slate-200" data-billing-stage-panel>
            <div class="flex flex-col gap-3 border-b border-slate-100 p-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-950">Billing stages</h3>
                    <p class="mt-1 text-sm font-medium text-slate-500">{{ $isInvoiceDocument ? 'For invoices, show what was already billed, what this invoice charges, and what remains.' : 'For quotations and POs, show the agreed billing stages and payment conditions.' }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button class="btn btn-secondary" type="button" data-apply-progress-schedule>Apply 40 / 40 / 20</button>
                    <button class="btn btn-secondary" type="button" data-add-billing-stage>Add stage</button>
                </div>
            </div>
            <div class="space-y-3 p-4" id="billing-stages">
                @foreach($billingRows as $index => $stage)
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                            <p class="text-sm font-bold text-slate-700">Billing stage {{ $index + 1 }}</p>
                            @if($isInvoiceDocument)
                                <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-600">
                                    <input type="hidden" name="billing_stages[{{ $index }}][is_current]" value="0">
                                    <input type="checkbox" name="billing_stages[{{ $index }}][is_current]" value="1" @checked(($stage['is_current'] ?? '0') === '1' || ($stage['is_current'] ?? false) === true)>
                                    Current invoice stage
                                </label>
                            @endif
                        </div>
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                            <label class="form-label">Billing stage
                                <input class="form-input" name="billing_stages[{{ $index }}][stage_name]" value="{{ $stage['stage_name'] ?? '' }}" placeholder="Progress Claim 1">
                            </label>
                            <label class="form-label xl:col-span-2">{{ $conditionHeading }}
                                <input class="form-input" name="billing_stages[{{ $index }}][condition_label]" value="{{ $stage['condition_label'] ?? '' }}" placeholder="{{ $isSupplierPo ? 'Accepted by '.$companyProfile->displayName() : 'Upon customer PO / written acceptance' }}">
                            </label>
                            <label class="form-label">%
                                <input class="form-input" type="number" step="0.01" min="0" max="100" name="billing_stages[{{ $index }}][percentage]" value="{{ $stage['percentage'] ?? '' }}">
                            </label>
                            <label class="form-label">Amount
                                <input class="form-input" type="number" step="0.01" min="0" name="billing_stages[{{ $index }}][amount]" value="{{ $stage['amount'] ?? '' }}">
                            </label>
                            <label class="form-label md:col-span-2 xl:col-span-5">Payment term
                                <input class="form-input" name="billing_stages[{{ $index }}][payment_term]" value="{{ $stage['payment_term'] ?? '' }}" placeholder="7 days from invoice date">
                            </label>
                            @if($isInvoiceDocument)
                                <label class="form-label">Previously invoiced
                                    <input class="form-input" type="number" step="0.01" min="0" name="billing_stages[{{ $index }}][previously_invoiced]" value="{{ $stage['previously_invoiced'] ?? '' }}">
                                </label>
                                <label class="form-label">This invoice
                                    <input class="form-input" type="number" step="0.01" min="0" name="billing_stages[{{ $index }}][current_invoice]" value="{{ $stage['current_invoice'] ?? '' }}">
                                </label>
                                <label class="form-label">Remaining to invoice
                                    <input class="form-input" type="number" step="0.01" min="0" name="billing_stages[{{ $index }}][remaining_amount]" value="{{ $stage['remaining_amount'] ?? '' }}">
                                </label>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <section class="{{ $usesDocumentStudio ? 'studio-section' : 'panel' }} @if($isPrQuoteUploadCreate) hidden @endif" @if($isPurchaseRequest && ! $document->exists) data-pr-manual-lines @endif>
        <div class="{{ $usesDocumentStudio ? 'studio-section-header' : 'panel-header' }}">
            <div>
                @if($usesDocumentStudio)
                    @unless($isGoodsReceipt)
                        <p class="studio-section-kicker">Step 3</p>
                    @endunless
                    <h2 class="studio-section-title">{{ $stepThreeTitle }}</h2>
                    <p class="studio-section-copy">{{ $stepThreeCopy }}</p>
                @else
                    <h2 class="panel-title">Line Items</h2>
                    <p class="panel-subtitle">Select a product or service to fill the description, unit, price and tax rate automatically.</p>
                @endif
            </div>
            <button class="btn btn-secondary whitespace-nowrap" type="button" data-add-line>Add line</button>
        </div>
        @if($isGoodsReceipt)
            <div class="receiving-line-list" id="line-items">
                @foreach($rows as $index => $row)
                    <div class="receiving-line-card" data-receipt-line-row data-ordered-qty="{{ (float) ($row['quantity'] ?? 1) }}">
                        <div class="receiving-line-main">
                            <label class="form-label mt-0"><span data-receipt-label="sourceItemHeader">PO material / item</span>
                                <select class="form-input" name="items[{{ $index }}][product_id]" @disabled($isPrQuoteUploadCreate)>
                                    <option value="">Manual line</option>
                                    @foreach($products as $product)
                                        <option
                                            value="{{ $product->id }}"
                                            data-description="{{ $product->description ?: $product->name }}"
                                            data-product-type="{{ $product->type }}"
                                            data-unit="{{ $product->unit }}"
                                            data-price="{{ $meta['direction'] === 'outgoing' ? $product->selling_price : $product->cost_price }}"
                                            data-tax-rate="{{ $product->tax_rate }}"
                                            @selected((string) ($row['product_id'] ?? '') === (string) $product->id)
                                        >{{ $product->name }}@if($product->sku) · {{ $product->sku }}@endif</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="form-label mt-0"><span data-receipt-label="lineDescriptionHeader">Received material description</span>
                                <input class="form-input" name="items[{{ $index }}][description]" value="{{ $row['description'] ?? '' }}" required>
                            </label>
                            <label class="form-label mt-0">Work item / cost code
                                <select class="form-input" name="items[{{ $index }}][wbs_item_id]" data-work-item-select>
                                    <option value="">Select project first</option>
                                    @foreach($workItems as $workItem)
                                        <option
                                            value="{{ $workItem->id }}"
                                            data-project-id="{{ $workItem->project_id }}"
                                            @selected((string) ($row['wbs_item_id'] ?? '') === (string) $workItem->id)
                                        >{{ $workItem->project?->project_code }} - {{ $workItem->code }} {{ $workItem->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <div class="receiving-line-qty-grid">
                            <div class="receiving-line-readonly">
                                <span>PO qty</span>
                                <strong data-ordered-qty-display>{{ rtrim(rtrim(number_format((float) ($row['quantity'] ?? 1), 3), '0'), '.') }}</strong>
                            </div>
                            <label class="form-label mt-0"><span data-receipt-label="receivedQtyHeader">Received qty</span>
                                <input class="form-input" type="number" step="0.001" min="0" name="items[{{ $index }}][quantity]" value="{{ $row['quantity'] ?? 1 }}" data-received-qty>
                            </label>
                            <div class="receiving-line-readonly">
                                <span data-receipt-label="exceptionQtyHeader">Short / rejected</span>
                                <strong data-exception-qty>0</strong>
                            </div>
                            <label class="form-label mt-0">Unit
                                <input class="form-input" name="items[{{ $index }}][unit]" value="{{ $row['unit'] ?? 'unit' }}">
                            </label>
                        </div>
                        <input type="hidden" name="items[{{ $index }}][unit_price]" value="{{ $row['unit_price'] ?? 0 }}">
                    </div>
                @endforeach
            </div>
        @else
            <div class="table-wrap">
                <table class="data-table" id="line-items">
                    <thead>
                    <tr><th class="min-w-56">Product / Service</th><th class="min-w-72">Description</th><th class="min-w-56">Work item / cost code</th><th>Qty</th><th>Unit</th><th>Price</th></tr>
                    </thead>
                    <tbody>
                    @foreach($rows as $index => $row)
                    <tr>
                        <td>
                            <select class="form-input" name="items[{{ $index }}][product_id]">
                                <option value="">Manual line</option>
                                @foreach($products as $product)
                                    <option
                                        value="{{ $product->id }}"
                                        data-description="{{ $product->description ?: $product->name }}"
                                        data-product-type="{{ $product->type }}"
                                        data-unit="{{ $product->unit }}"
                                        data-price="{{ $meta['direction'] === 'outgoing' ? $product->selling_price : $product->cost_price }}"
                                        data-tax-rate="{{ $product->tax_rate }}"
                                        @selected((string) ($row['product_id'] ?? '') === (string) $product->id)
                                    >{{ $product->name }}@if($product->sku) · {{ $product->sku }}@endif</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input class="form-input" name="items[{{ $index }}][description]" value="{{ $row['description'] ?? '' }}" @required(! $isPrQuoteUploadCreate) @disabled($isPrQuoteUploadCreate)></td>
                        <td>
                            <select class="form-input min-w-56" name="items[{{ $index }}][wbs_item_id]" data-work-item-select @disabled($isPrQuoteUploadCreate) @if($isPrQuoteUploadCreate) data-work-item-force-disabled @endif>
                                <option value="">Select project first</option>
                                @foreach($workItems as $workItem)
                                    <option
                                        value="{{ $workItem->id }}"
                                        data-project-id="{{ $workItem->project_id }}"
                                        @selected((string) ($row['wbs_item_id'] ?? '') === (string) $workItem->id)
                                    >{{ $workItem->project?->project_code }} - {{ $workItem->code }} {{ $workItem->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input class="form-input w-28" type="number" step="0.001" min="{{ $isPrQuoteUploadCreate ? '0' : '0.001' }}" name="items[{{ $index }}][quantity]" value="{{ $row['quantity'] ?? 1 }}" @disabled($isPrQuoteUploadCreate)></td>
                        <td><input class="form-input w-24" name="items[{{ $index }}][unit]" value="{{ $row['unit'] ?? 'unit' }}" @disabled($isPrQuoteUploadCreate)></td>
                        <td><input class="form-input w-32" type="number" step="0.01" min="0" name="items[{{ $index }}][unit_price]" value="{{ $row['unit_price'] ?? 0 }}" @disabled($isPrQuoteUploadCreate)></td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($isGoodsReceipt)
            <div class="mt-4 grid gap-3 md:grid-cols-3" aria-live="polite">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Lines checked</p>
                    <p class="mt-2 text-lg font-bold text-slate-950" data-summary-receipt-lines>0</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500" data-receipt-label="summaryQtyLabel">Total received</p>
                    <p class="mt-2 text-lg font-bold text-slate-950" data-summary-receipt-qty>0</p>
                </div>
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-amber-800">Exceptions</p>
                    <p class="mt-2 text-lg font-bold text-slate-950" data-summary-receipt-exception>0</p>
                </div>
            </div>
        @else
            <div class="mt-4 grid gap-3 md:grid-cols-3" aria-live="polite">
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Subtotal</p>
                    <p class="mt-2 text-lg font-bold text-slate-950" data-summary-subtotal>{{ $companyProfile->formatMoney(0, $documentCurrency) }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Tax</p>
                    <p class="mt-2 text-lg font-bold text-slate-950" data-summary-tax>{{ $companyProfile->formatMoney(0, $documentCurrency) }}</p>
                </div>
                <div class="rounded-lg border border-blue-900 bg-[#0a345f] p-4 text-white">
                    <p class="text-xs font-bold uppercase tracking-wide text-slate-300">{{ $summaryTotalLabel }}</p>
                    <p class="mt-2 text-lg font-bold" data-summary-total>{{ $companyProfile->formatMoney(0, $documentCurrency) }}</p>
                </div>
            </div>
        @endif
    </section>

    <section class="{{ $usesDocumentStudio ? 'studio-section' : 'panel' }}">
        <div class="{{ $usesDocumentStudio ? 'studio-section-header' : 'mb-4' }}">
            <div>
                @if($usesDocumentStudio)
                    @unless($isGoodsReceipt)
                        <p class="studio-section-kicker">Step 4</p>
                    @endunless
                    <h2 class="studio-section-title">{{ $stepFourTitle }}</h2>
                    <p class="studio-section-copy">{{ $stepFourCopy }}</p>
                @else
                    <h2 class="panel-title">Commercial notes</h2>
                @endif
            </div>
        </div>
        @if($isGoodsReceipt)
            <label class="form-label"><span data-receipt-label="notesInputLabel">Receiving remarks</span>
                <textarea class="form-input min-h-32" name="notes" placeholder="Record shortages, damage, rejected quantity, inspection notes, or receiving confirmation.">{{ old('notes', $document->notes) }}</textarea>
            </label>
            <input type="hidden" name="terms" value="{{ old('terms', $document->terms) }}">
        @else
            <div class="grid gap-4 md:grid-cols-2">
                <label class="form-label">{{ $notesLabel }}
                    <textarea class="form-input min-h-28" name="notes">{{ old('notes', $document->notes) }}</textarea>
                </label>
                <label class="form-label">Terms
                    <textarea class="form-input min-h-28" name="terms">{{ old('terms', $document->terms) }}</textarea>
                </label>
            </div>
        @endif
    </section>

    <div class="{{ $usesDocumentStudio ? 'hidden' : 'flex flex-wrap gap-3' }}">
        <button class="btn btn-primary" type="submit">Save document</button>
        <a class="btn btn-secondary" href="{{ $document->exists ? route('documents.show', $document) : route('documents.index', $meta['slug']) }}">Cancel</a>
    </div>
</form>

@if($usesDocumentStudio)
    <aside
        class="workspace-preview-pane"
        data-document-live-preview-pane
        aria-label="{{ $livePreviewTitle }}"
        @if($isQuotationDocument) data-quotation-live-preview-pane @endif
    >
        <div class="sr-only">
            <h2 @if($isGoodsReceipt) data-receipt-label="paneTitle" @endif>{{ $livePreviewTitle }}</h2>
            @if($isGoodsReceipt)
                <p data-receipt-label="paneCopy"></p>
            @endif
        </div>
        <div class="mx-auto max-w-[46rem]">
            @if($meta['type'] === 'supplier_invoice')
                <article class="supplier-invoice-intake-preview">
                    <p class="document-pane-kicker">Supplier invoice</p>
                    <h3>Upload supplier PDF or image after saving</h3>
                    <p>Record supplier, invoice number, matching basis, and amount. Upload the supplier file after saving for comparison.</p>
                    <dl>
                        <div>
                            <dt>Supplier</dt>
                            <dd data-preview-party>Select supplier</dd>
                        </div>
                        <div>
                            <dt>Supplier invoice no.</dt>
                            <dd data-preview-reference>{{ $previewReferenceFallback }}</dd>
                        </div>
                        <div>
                            <dt>Recorded total</dt>
                            <dd data-summary-total>{{ $companyProfile->formatMoney(0, $documentCurrency) }}</dd>
                        </div>
                        <div>
                            <dt>Matching basis</dt>
                            <dd>{{ $sourceQuestion }}</dd>
                        </div>
                    </dl>
                </article>
            @elseif($isGoodsReceipt)
                @include('documents.partials.receipt-live-preview', ['document' => $document, 'meta' => $meta])
            @else
                @include('documents.partials.quotation-live-preview', ['document' => $document, 'meta' => $meta])
            @endif
        </div>
    </aside>
</div>
@endif

<template id="line-template">
    @if($isGoodsReceipt)
    <div class="receiving-line-card" data-receipt-line-row data-ordered-qty="0">
        <div class="receiving-line-main">
            <label class="form-label mt-0"><span data-receipt-label="sourceItemHeader">PO material / item</span>
                <select class="form-input" data-name="product_id">
                    <option value="">Manual line</option>
                    @foreach($products as $product)
                        <option
                            value="{{ $product->id }}"
                            data-description="{{ $product->description ?: $product->name }}"
                            data-product-type="{{ $product->type }}"
                            data-unit="{{ $product->unit }}"
                            data-price="{{ $meta['direction'] === 'outgoing' ? $product->selling_price : $product->cost_price }}"
                            data-tax-rate="{{ $product->tax_rate }}"
                        >{{ $product->name }}@if($product->sku) · {{ $product->sku }}@endif</option>
                    @endforeach
                </select>
            </label>
            <label class="form-label mt-0"><span data-receipt-label="lineDescriptionHeader">Received material description</span>
                <input class="form-input" data-name="description" required>
            </label>
            <label class="form-label mt-0">Work item / cost code
                <select class="form-input" data-name="wbs_item_id" data-work-item-select>
                    <option value="">Select project first</option>
                    @foreach($workItems as $workItem)
                        <option
                            value="{{ $workItem->id }}"
                            data-project-id="{{ $workItem->project_id }}"
                        >{{ $workItem->project?->project_code }} - {{ $workItem->code }} {{ $workItem->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <div class="receiving-line-qty-grid">
            <div class="receiving-line-readonly">
                <span>PO qty</span>
                <strong data-ordered-qty-display>0</strong>
            </div>
            <label class="form-label mt-0"><span data-receipt-label="receivedQtyHeader">Received qty</span>
                <input class="form-input" data-name="quantity" data-received-qty type="number" step="0.001" min="0" value="0">
            </label>
            <div class="receiving-line-readonly">
                <span data-receipt-label="exceptionQtyHeader">Short / rejected</span>
                <strong data-exception-qty>0</strong>
            </div>
            <label class="form-label mt-0">Unit
                <input class="form-input" data-name="unit" value="unit">
            </label>
        </div>
        <input data-name="unit_price" type="hidden" value="0">
    </div>
    @else
    <tr @if($isGoodsReceipt) data-receipt-line-row data-ordered-qty="0" @endif>
        <td>
            <select class="form-input" data-name="product_id">
                <option value="">Manual line</option>
                @foreach($products as $product)
                    <option
                        value="{{ $product->id }}"
                        data-description="{{ $product->description ?: $product->name }}"
                        data-product-type="{{ $product->type }}"
                        data-unit="{{ $product->unit }}"
                        data-price="{{ $meta['direction'] === 'outgoing' ? $product->selling_price : $product->cost_price }}"
                        data-tax-rate="{{ $product->tax_rate }}"
                    >{{ $product->name }}@if($product->sku) · {{ $product->sku }}@endif</option>
                @endforeach
            </select>
        </td>
        <td><input class="form-input" data-name="description" required></td>
        <td>
            <select class="form-input min-w-56" data-name="wbs_item_id" data-work-item-select @if($isPrQuoteUploadCreate) data-work-item-force-disabled @endif>
                <option value="">Select project first</option>
                @foreach($workItems as $workItem)
                    <option
                        value="{{ $workItem->id }}"
                        data-project-id="{{ $workItem->project_id }}"
                    >{{ $workItem->project?->project_code }} - {{ $workItem->code }} {{ $workItem->name }}</option>
                @endforeach
            </select>
        </td>
        <td><input class="form-input w-28" data-name="quantity" type="number" step="0.001" min="0.001" value="1"></td>
        <td><input class="form-input w-24" data-name="unit" value="unit"></td>
        <td><input class="form-input w-32" data-name="unit_price" type="number" step="0.01" min="0" value="0"></td>
    </tr>
    @endif
</template>

<template id="billing-stage-template">
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm font-bold text-slate-700" data-stage-title>Billing stage</p>
            @if($isInvoiceDocument)
                <label class="inline-flex items-center gap-2 text-sm font-bold text-slate-600">
                    <input type="hidden" data-name="is_current" value="0">
                    <input type="checkbox" data-name="is_current" value="1">
                    Current invoice stage
                </label>
            @endif
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <label class="form-label">Billing stage
                <input class="form-input" data-name="stage_name" placeholder="Progress Claim 1">
            </label>
            <label class="form-label xl:col-span-2">{{ $conditionHeading }}
                <input class="form-input" data-name="condition_label" placeholder="{{ $isSupplierPo ? 'Accepted by '.$companyProfile->displayName() : 'Upon customer PO / written acceptance' }}">
            </label>
            <label class="form-label">%
                <input class="form-input" data-name="percentage" type="number" step="0.01" min="0" max="100">
            </label>
            <label class="form-label">Amount
                <input class="form-input" data-name="amount" type="number" step="0.01" min="0">
            </label>
            <label class="form-label md:col-span-2 xl:col-span-5">Payment term
                <input class="form-input" data-name="payment_term" placeholder="7 days from invoice date">
            </label>
        @if($isInvoiceDocument)
            <label class="form-label">Previously invoiced
                <input class="form-input" data-name="previously_invoiced" type="number" step="0.01" min="0">
            </label>
            <label class="form-label">This invoice
                <input class="form-input" data-name="current_invoice" type="number" step="0.01" min="0">
            </label>
            <label class="form-label">Remaining to invoice
                <input class="form-input" data-name="remaining_amount" type="number" step="0.01" min="0">
            </label>
        @endif
        </div>
    </div>
</template>

<script>
const companyNumberFormat = @json($companyProfile->numberFormat());
const companyCurrencyDisplay = @json($companyProfile->currencyDisplay());
const companyCurrencySymbolOverride = @json($companyProfile->currency_symbol_override);
const companyBaseCurrency = @json($companyProfile->baseCurrency());
const companyCurrencySymbols = @json($currencySymbols);
const documentCurrencyFallback = @json($documentCurrency);

function wireNamedTemplate(template, index, prefix) {
    template.querySelectorAll('[data-name]').forEach((field) => {
        field.name = `${prefix}[${index}][${field.dataset.name}]`;
    });
}

function lineField(row, key) {
    return row.querySelector(`[name$="[${key}]"]`);
}

function currencySymbol(currency) {
    if (currency === companyBaseCurrency && companyCurrencySymbolOverride) {
        return companyCurrencySymbolOverride;
    }

    return companyCurrencySymbols[currency] || currency;
}

function formatMoney(amount) {
    const currency = (document.querySelector('[name="currency"]')?.value || documentCurrencyFallback).toUpperCase();
    const formatted = formatNumber(amount, 2);

    if (companyCurrencyDisplay === 'symbol') {
        return `${currencySymbol(currency)} ${formatted}`.trim();
    }

    if (companyCurrencyDisplay === 'symbol_with_code') {
        return `${currencySymbol(currency)} ${formatted} (${currency})`.trim();
    }

    return `${currency} ${formatted}`.trim();
}

function updateDocumentTotals() {
    let subtotal = 0;
    document.querySelectorAll('#line-items tbody tr').forEach((row) => {
        const quantity = Number(lineField(row, 'quantity')?.value || 0);
        const unitPrice = Number(lineField(row, 'unit_price')?.value || 0);
        subtotal += quantity * unitPrice;
    });
    const taxRate = Number(document.querySelector('[name="document_tax_rate"]')?.value || 0);
    const tax = subtotal * (taxRate / 100);
    document.querySelector('[data-summary-subtotal]') && (document.querySelector('[data-summary-subtotal]').textContent = formatMoney(subtotal));
    document.querySelector('[data-summary-tax]') && (document.querySelector('[data-summary-tax]').textContent = formatMoney(tax));
    document.querySelector('[data-summary-total]') && (document.querySelector('[data-summary-total]').textContent = formatMoney(subtotal + tax));
    document.querySelector('[data-preview-subtotal]') && (document.querySelector('[data-preview-subtotal]').textContent = formatMoney(subtotal));
    document.querySelector('[data-preview-tax]') && (document.querySelector('[data-preview-tax]').textContent = formatMoney(tax));
    document.querySelector('[data-preview-total]') && (document.querySelector('[data-preview-total]').textContent = formatMoney(subtotal + tax));

    return { subtotal, tax, total: subtotal + tax };
}

function applyProductDefaults(select) {
    const option = select.selectedOptions?.[0];
    if (!option || !option.value) return;

    const row = select.closest('tr');
    lineField(row, 'description').value = option.dataset.description || option.textContent.trim();
    lineField(row, 'unit').value = option.dataset.unit || 'unit';
    lineField(row, 'unit_price').value = option.dataset.price || '0';

    const taxField = document.querySelector('[name="document_tax_rate"]');
    if (taxField && (taxField.value === '' || Number(taxField.value) === 0)) {
        taxField.value = option.dataset.taxRate || '0';
    }

    updateDocumentTotals();
}

function toggleBillingStages() {
    const structure = document.querySelector('[data-payment-structure]');
    const panel = document.querySelector('[data-billing-stage-panel]');
    if (!structure || !panel) return;

    const show = structure.value === 'milestone';
    panel.classList.toggle('hidden', !show);
    panel.querySelectorAll('input, select, textarea, button').forEach((field) => {
        field.disabled = !show;
    });
}

function filterRelatedDocuments() {
    const partySelect = document.querySelector('[data-party-select]');
    const relatedSelect = document.querySelector('[data-related-document-select]');
    if (!partySelect || !relatedSelect) return;

    const partyId = partySelect.value;
    const partyLabel = relatedSelect.dataset.partyLabel || 'party';
    const emptyOption = relatedSelect.querySelector('[data-empty-option]');
    let visibleCount = 0;

    relatedSelect.querySelectorAll('option').forEach((option) => {
        if (option === emptyOption) return;

        const matchesParty = partyId !== '' && option.dataset.partyId === partyId;
        option.hidden = !matchesParty;
        option.disabled = !matchesParty;
        if (matchesParty) visibleCount += 1;
    });

    if (emptyOption) {
        emptyOption.textContent = partyId === ''
            ? `Select ${partyLabel} first`
            : (visibleCount > 0 ? 'None' : `No previous documents for this ${partyLabel}`);
    }

    const selectedOption = relatedSelect.selectedOptions[0];
    if (!partyId || selectedOption?.disabled || selectedOption?.hidden) {
        relatedSelect.value = '';
    }
}

function syncProjectFromRelatedDocument() {
    const relatedSelect = document.querySelector('[data-related-document-select]');
    const projectSelect = document.querySelector('[data-project-select]');
    if (!relatedSelect || !projectSelect) return;

    const projectId = relatedSelect.selectedOptions[0]?.dataset.projectId || '';
    const hasProjectOption = Array.from(projectSelect.options).some((option) => option.value === projectId);
    if (projectId && hasProjectOption) {
        projectSelect.value = projectId;
    }

    filterWorkItemOptions();
}

function filterWorkItemOptions() {
    const projectSelect = document.querySelector('[data-project-select]');
    const projectId = projectSelect?.value || '';

    document.querySelectorAll('[data-work-item-select]').forEach((select) => {
        const emptyOption = select.querySelector('option[value=""]');
        let availableCount = 0;

        select.querySelectorAll('option').forEach((option) => {
            if (!option.value) {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            const matchesProject = projectId !== '' && option.dataset.projectId === projectId;
            option.hidden = !matchesProject;
            option.disabled = !matchesProject;

            if (matchesProject) {
                availableCount += 1;
            }
        });

        const selectedOption = select.selectedOptions[0];
        if (!projectId || selectedOption?.disabled || selectedOption?.hidden) {
            select.value = '';
        }

        if (emptyOption) {
            emptyOption.textContent = projectId === ''
                ? 'Select project first'
                : (availableCount > 0 ? 'No work item' : 'No work items for this project');
        }

        const hiddenManualLinePanel = select.closest('[data-pr-manual-lines]')?.classList.contains('hidden') || false;
        select.disabled = projectId === '' || select.hasAttribute('data-work-item-force-disabled') || hiddenManualLinePanel;
    });
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[character]));
}

function formatPreviewDate(value, fallback) {
    if (!value) return fallback;
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return fallback;

    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function selectedPartyText() {
    const select = document.querySelector('[data-party-select]');
    const option = select?.selectedOptions?.[0];

    return option?.value ? option.textContent.trim() : '{{ $meta['party'] === 'customer' ? 'Select customer' : 'Select supplier' }}';
}

function formValue(selector, fallback = '') {
    const value = document.querySelector(selector)?.value;

    return value && value.trim() !== '' ? value.trim() : fallback;
}

function formatNumber(amount, decimals = 2) {
    return Number(amount || 0).toLocaleString(companyNumberFormat, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    });
}

function formatQuantity(amount) {
    return Number(amount || 0).toLocaleString(companyNumberFormat, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3,
    });
}

function receiptLineProductTypes() {
    return Array.from(document.querySelectorAll('[data-receipt-line-row]'))
        .map((row) => {
            const option = lineField(row, 'product_id')?.selectedOptions?.[0];
            const type = String(option?.dataset?.productType || '').toLowerCase();
            if (!option?.value || !type) return '';

            return type.includes('service') ? 'service' : 'goods';
        })
        .filter(Boolean);
}

function receiptModeFromLines() {
    const selectedMode = document.querySelector('[data-receipt-mode]')?.value || 'goods';
    const types = receiptLineProductTypes();
    if (!types.length) return selectedMode;

    const hasGoods = types.includes('goods');
    const hasService = types.includes('service');

    if (hasGoods && hasService) return 'mixed';
    return hasService ? 'service' : 'goods';
}

function receiptLabels(mode = 'goods') {
    const labels = {
        goods: {
            paneTitle: 'Goods Receipt Preview',
            paneCopy: 'This goods receipt is used later for supplier invoice matching. It does not show pricing, tax, or payment terms.',
            subtitle: 'Goods receipt record',
            documentTitle: 'GOODS RECEIPT NOTE',
            partyLabel: 'Supplier',
            referenceInputLabel: 'Delivery order reference',
            referenceLabel: 'Delivery order reference',
            referenceFallback: 'Delivery order reference',
            dateInputLabel: 'Received date',
            dateLabel: 'Received date',
            dateFallback: 'Received date',
            locationInputLabel: 'Receiving location',
            locationFallback: 'Receiving location',
            matchingStatus: 'Ready for invoice matching',
            remarksTitle: 'Receiving remarks',
            remarksFallback: 'Receiving, inspection, shortage, rejection, or damage remarks will appear here.',
            sourceItemHeader: 'PO material / item',
            lineDescriptionHeader: 'Received material description',
            receivedQtyHeader: 'Received qty',
            exceptionQtyHeader: 'Short / rejected',
            summaryQtyLabel: 'Total received',
            notesInputLabel: 'Receiving remarks',
            previewDescriptionHeader: 'Material / item received',
            previewReceivedHeader: 'Received qty',
            previewExceptionHeader: 'Short / rejected',
            emptyLineCopy: 'Add received material lines to preview the goods receipt note.',
            lineFallback: 'Material / item',
            evidenceTitle: 'Evidence to attach after save',
            evidenceCopy: 'Delivery order, packing list, delivery photos, inspection notes, damage report, or handover proof required before invoice matching.',
        },
        service: {
            paneTitle: 'Service Acceptance Preview',
            paneCopy: 'This is the service acceptance record used later for supplier invoice matching. It does not show pricing, tax, or payment terms.',
            subtitle: 'Service completion acceptance record',
            documentTitle: 'SERVICE ACCEPTANCE RECORD',
            partyLabel: 'Service provider',
            referenceInputLabel: 'Service report / UAT reference',
            referenceLabel: 'Service report / UAT reference',
            referenceFallback: 'Service report / UAT reference',
            dateInputLabel: 'Accepted date',
            dateLabel: 'Accepted date',
            dateFallback: 'Accepted date',
            locationInputLabel: 'Service location',
            locationFallback: 'Service location',
            matchingStatus: 'Ready for invoice matching',
            remarksTitle: 'Acceptance remarks',
            remarksFallback: 'Service completion, UAT result, pending item, or acceptance remarks will appear here.',
            sourceItemHeader: 'PO service / milestone',
            lineDescriptionHeader: 'Accepted service description',
            receivedQtyHeader: 'Accepted qty',
            exceptionQtyHeader: 'Pending / rejected',
            summaryQtyLabel: 'Total accepted',
            notesInputLabel: 'Acceptance remarks',
            previewDescriptionHeader: 'Service / milestone accepted',
            previewReceivedHeader: 'Accepted qty',
            previewExceptionHeader: 'Pending / rejected',
            emptyLineCopy: 'Add accepted service lines to preview the service acceptance record.',
            lineFallback: 'Service / deliverable',
            evidenceTitle: 'Evidence to attach after save',
            evidenceCopy: 'Service report, UAT sign-off, completion report, installation photos, handover notes, or other acceptance proof required before invoice matching.',
        },
        mixed: {
            paneTitle: 'Goods receipt and acceptance preview',
            paneCopy: 'This goods receipt and service acceptance record is used later for supplier invoice matching. It does not show pricing, tax, or payment terms.',
            subtitle: 'Goods receipt and service acceptance record',
            documentTitle: 'GOODS RECEIPT & ACCEPTANCE RECORD',
            partyLabel: 'Supplier / service provider',
            referenceInputLabel: 'Delivery / service evidence reference',
            referenceLabel: 'Evidence reference',
            referenceFallback: 'Delivery order / service report reference',
            dateInputLabel: 'Received / accepted date',
            dateLabel: 'Received / accepted date',
            dateFallback: 'Received date',
            locationInputLabel: 'Receiving / service location',
            locationFallback: 'Receiving / service location',
            matchingStatus: 'Ready for invoice matching',
            remarksTitle: 'Receiving / acceptance remarks',
            remarksFallback: 'Receiving, inspection, service completion, or acceptance remarks will appear here.',
            sourceItemHeader: 'PO item / service',
            lineDescriptionHeader: 'Received / accepted description',
            receivedQtyHeader: 'Received / accepted qty',
            exceptionQtyHeader: 'Exception',
            summaryQtyLabel: 'Total received / accepted',
            notesInputLabel: 'Receiving / acceptance remarks',
            previewDescriptionHeader: 'Received / accepted item or service',
            previewReceivedHeader: 'Received / accepted',
            previewExceptionHeader: 'Exception',
            emptyLineCopy: 'Add received or accepted lines to preview the goods receipt.',
            lineFallback: 'Receipt line',
            evidenceTitle: 'Evidence to attach after save',
            evidenceCopy: 'Delivery order, service report, UAT sign-off, installation report, completion photos, handover notes, or other proof required before invoice matching.',
        },
    };

    return labels[mode] || labels.goods;
}

function syncReceiptModeLabels() {
    const mode = receiptModeFromLines();
    const labels = receiptLabels(mode);
    const selector = document.querySelector('[data-receipt-mode]');

    if (selector && receiptLineProductTypes().length && selector.value !== mode) {
        selector.value = mode;
    }

    document.querySelectorAll('[data-receipt-label]').forEach((target) => {
        const key = target.dataset.receiptLabel;
        if (labels[key]) target.textContent = labels[key];
    });

    return { mode, labels };
}

function syncQuotationPreview() {
    const preview = document.querySelector('[data-live-quotation-preview]');
    if (!preview) return;

    const totals = updateDocumentTotals();
    const setText = (selector, value, fallback = '-') => {
        const target = preview.querySelector(selector);
        if (!target) return;
        target.textContent = value && String(value).trim() !== '' ? value : fallback;
    };

    const structure = document.querySelector('[data-payment-structure]')?.value || 'standard';
    const paymentDays = formValue('[name="payment_due_days"]');
    const paymentFallback = structure === 'milestone'
        ? 'Milestone based'
        : (paymentDays ? `${paymentDays} days from invoice date` : 'Not specified');

    setText('[data-preview-party]', selectedPartyText());
    setText('[data-preview-reference]', formValue('[name="external_reference"]'), '{{ $previewReferenceFallback }}');
    setText('[data-preview-date]', formatPreviewDate(formValue('[name="issue_date"]'), 'Issue date'));
    setText('[data-preview-valid]', formatPreviewDate(formValue('[name="due_date"]'), '{{ $previewSecondaryDateFallback }}'));
    setText('[data-preview-currency]', formValue('[name="currency"]', documentCurrencyFallback).toUpperCase());
    setText('[data-preview-payment]', formValue('[name="payment_terms_label"]', paymentFallback));
    setText('[data-preview-site]', formValue('[name="project_name"]'), 'Project / site');
    setText('[data-preview-delivery]', formValue('[name="delivery_to"]'), 'Delivery / service location');
    setText('[data-preview-scope]', formValue('[name="notes"]'), '{{ $previewScopeFallback }}');
    setText('[data-preview-terms]', formValue('[name="terms"]'), 'Terms will appear here.');

    const lineTarget = preview.querySelector('[data-preview-lines]');
    if (lineTarget) {
        const lineRows = Array.from(document.querySelectorAll('#line-items tbody tr'))
            .map((row, index) => {
                const productSelect = lineField(row, 'product_id');
                const productName = productSelect?.selectedOptions?.[0]?.value ? productSelect.selectedOptions[0].textContent.trim() : '';
                const description = formValue(`[name="${lineField(row, 'description')?.name}"]`, productName);
                const quantity = Number(lineField(row, 'quantity')?.value || 0);
                const unit = lineField(row, 'unit')?.value || 'unit';
                const unitPrice = Number(lineField(row, 'unit_price')?.value || 0);
                const amount = quantity * unitPrice;

                if (!description && amount <= 0) return '';

                return `
                    <tr>
                        <td class="px-3 py-3 align-top">${index + 1}</td>
                        <td class="px-3 py-3 align-top"><p class="font-bold">${escapeHtml(description || 'Line item')}</p></td>
                        <td class="px-3 py-3 text-right align-top">${formatNumber(quantity, 3)}</td>
                        <td class="px-3 py-3 align-top">${escapeHtml(unit)}</td>
                        <td class="px-3 py-3 text-right align-top">${formatNumber(unitPrice)}</td>
                        <td class="px-3 py-3 text-right align-top font-bold">${formatNumber(amount)}</td>
                    </tr>
                `;
            })
            .filter(Boolean)
            .join('');

        lineTarget.innerHTML = lineRows || '<tr><td colspan="6" class="px-3 py-6 text-center font-semibold text-slate-500">Add line items to preview the {{ $documentNoun }} value.</td></tr>';
    }

    const schedule = preview.querySelector('[data-preview-schedule]');
    const scheduleLines = preview.querySelector('[data-preview-schedule-lines]');
    if (schedule && scheduleLines) {
        const scheduleRows = structure === 'milestone'
            ? Array.from(document.querySelectorAll('#billing-stages > div')).map((row) => {
                const stage = formValue(`[name="${row.querySelector('[name$="[stage_name]"]')?.name}"]`);
                const condition = formValue(`[name="${row.querySelector('[name$="[condition_label]"]')?.name}"]`);
                const percentageValue = formValue(`[name="${row.querySelector('[name$="[percentage]"]')?.name}"]`);
                const amountField = formValue(`[name="${row.querySelector('[name$="[amount]"]')?.name}"]`);
                const paymentTerm = formValue(`[name="${row.querySelector('[name$="[payment_term]"]')?.name}"]`);
                const calculatedAmount = percentageValue !== '' ? totals.total * (Number(percentageValue) / 100) : 0;
                const amount = amountField !== '' ? Number(amountField) : calculatedAmount;

                if (!stage && !condition && !percentageValue && !amountField && !paymentTerm) return '';

                return `
                    <tr>
                        <td class="px-3 py-2 font-semibold">${escapeHtml(stage || 'Billing stage')}</td>
                        <td class="px-3 py-2">${escapeHtml(condition || '-')}</td>
                        <td class="px-3 py-2 text-right">${percentageValue !== '' ? formatNumber(percentageValue) : '-'}</td>
                        <td class="px-3 py-2 text-right">${amount > 0 ? escapeHtml(formatMoney(amount)) : '-'}</td>
                        <td class="px-3 py-2">${escapeHtml(paymentTerm || '-')}</td>
                    </tr>
                `;
            }).filter(Boolean).join('')
            : '';

        schedule.classList.toggle('hidden', !scheduleRows);
        scheduleLines.innerHTML = scheduleRows;
    }
}

function updateReceiptQuantities() {
    let lineCount = 0;
    let receivedTotal = 0;
    let exceptionTotal = 0;

    document.querySelectorAll('[data-receipt-line-row]').forEach((row) => {
        const description = lineField(row, 'description')?.value?.trim() || '';
        const received = Number(lineField(row, 'quantity')?.value || 0);
        const ordered = Number(row.dataset.orderedQty || received || 0);
        const exception = Math.max(0, ordered - received);

        const orderedDisplay = row.querySelector('[data-ordered-qty-display]');
        const exceptionDisplay = row.querySelector('[data-exception-qty]');
        if (orderedDisplay) orderedDisplay.textContent = formatQuantity(ordered);
        if (exceptionDisplay) {
            exceptionDisplay.textContent = formatQuantity(exception);
            exceptionDisplay.classList.toggle('border-amber-300', exception > 0);
            exceptionDisplay.classList.toggle('bg-amber-50', exception > 0);
            exceptionDisplay.classList.toggle('text-amber-800', exception > 0);
        }

        if (description || received > 0) {
            lineCount += 1;
        }
        receivedTotal += received;
        exceptionTotal += exception;
    });

    document.querySelector('[data-summary-receipt-lines]') && (document.querySelector('[data-summary-receipt-lines]').textContent = String(lineCount));
    document.querySelector('[data-summary-receipt-qty]') && (document.querySelector('[data-summary-receipt-qty]').textContent = formatQuantity(receivedTotal));
    document.querySelector('[data-summary-receipt-exception]') && (document.querySelector('[data-summary-receipt-exception]').textContent = formatQuantity(exceptionTotal));

    return { lineCount, receivedTotal, exceptionTotal };
}

function syncReceiptPreview() {
    const preview = document.querySelector('[data-live-receipt-preview]');
    const { labels } = syncReceiptModeLabels();
    if (!preview) return;

    const setText = (selector, value, fallback = '-') => {
        const target = preview.querySelector(selector);
        if (!target) return;
        target.textContent = value && String(value).trim() !== '' ? value : fallback;
    };

    const relatedSelect = document.querySelector('[data-related-document-select]');
    const relatedOption = relatedSelect?.selectedOptions?.[0];
    const relatedText = relatedOption?.value ? relatedOption.textContent.trim().split('·')[0].trim() : '';

    setText('[data-preview-party]', selectedPartyText());
    setText('[data-preview-reference]', formValue('[name="external_reference"]'), labels.referenceFallback);
    setText('[data-preview-date]', formatPreviewDate(formValue('[name="issue_date"]'), labels.dateFallback));
    setText('[data-preview-related]', relatedText, 'Select issued PO');
    setText('[data-preview-site]', formValue('[name="project_name"]'), 'Project / site');
    setText('[data-preview-delivery]', formValue('[name="delivery_to"]'), labels.locationFallback);
    setText('[data-preview-scope]', formValue('[name="notes"]'), labels.remarksFallback);

    const lineTarget = preview.querySelector('[data-preview-lines]');
    if (!lineTarget) return;

    let previewLineNumber = 0;
    const lineRows = Array.from(document.querySelectorAll('[data-receipt-line-row]'))
        .map((row) => {
            const productSelect = lineField(row, 'product_id');
            const productName = productSelect?.selectedOptions?.[0]?.value ? productSelect.selectedOptions[0].textContent.trim() : '';
            const description = lineField(row, 'description')?.value?.trim() || productName;
            const ordered = Number(row.dataset.orderedQty || lineField(row, 'quantity')?.value || 0);
            const received = Number(lineField(row, 'quantity')?.value || 0);
            const exception = Math.max(0, ordered - received);
            const unit = lineField(row, 'unit')?.value || 'unit';

            if (!description && received <= 0) return '';
            previewLineNumber += 1;

            return `
                <tr>
                    <td class="px-3 py-3 align-top">${previewLineNumber}</td>
                    <td class="px-3 py-3 align-top"><p class="font-bold">${escapeHtml(description || labels.lineFallback)}</p></td>
                    <td class="px-3 py-3 text-right align-top">${formatQuantity(ordered)}</td>
                    <td class="px-3 py-3 text-right align-top">${formatQuantity(received)}</td>
                    <td class="px-3 py-3 text-right align-top ${exception > 0 ? 'font-bold text-amber-800' : ''}">${formatQuantity(exception)}</td>
                    <td class="px-3 py-3 align-top">${escapeHtml(unit)}</td>
                </tr>
            `;
        })
        .filter(Boolean)
        .join('');

    lineTarget.innerHTML = lineRows || `<tr><td colspan="6" class="px-3 py-6 text-center font-semibold text-slate-500">${escapeHtml(labels.emptyLineCopy)}</td></tr>`;
}

function refreshDocumentPreview() {
    updateDocumentTotals();
    updateReceiptQuantities();
    syncReceiptModeLabels();
    syncQuotationPreview();
    syncReceiptPreview();
}

document.querySelector('[data-add-line]')?.addEventListener('click', function () {
    const tbody = document.querySelector('#line-items tbody') || document.querySelector('#line-items');
    const template = document.querySelector('#line-template').content.cloneNode(true);
    wireNamedTemplate(template, tbody.children.length, 'items');
    tbody.appendChild(template);
    filterWorkItemOptions();
    refreshDocumentPreview();
});

document.querySelector('#line-items')?.addEventListener('change', function (event) {
    if (event.target.matches('select[name$="[product_id]"]')) {
        applyProductDefaults(event.target);
    }
    refreshDocumentPreview();
});

document.querySelector('#line-items')?.addEventListener('input', refreshDocumentPreview);
document.querySelector('[name="document_tax_rate"]')?.addEventListener('input', refreshDocumentPreview);
document.querySelector('[name="currency"]')?.addEventListener('input', refreshDocumentPreview);
document.querySelector('[data-party-select]')?.addEventListener('change', function () {
    filterRelatedDocuments();
    syncProjectFromRelatedDocument();
    refreshDocumentPreview();
});
document.querySelector('[data-related-document-select]')?.addEventListener('change', function () {
    syncProjectFromRelatedDocument();
    refreshDocumentPreview();
});
document.querySelector('[data-project-select]')?.addEventListener('change', function () {
    filterWorkItemOptions();
    refreshDocumentPreview();
});
document.querySelector('[data-receipt-mode]')?.addEventListener('change', refreshDocumentPreview);

function defaultProgressStages() {
    return [
        { stage_name: 'Deposit', condition_label: '{{ $isSupplierPo ? 'PO acknowledged by supplier' : 'Upon customer PO / written acceptance' }}', percentage: '40', payment_term: '{{ $isSupplierPo ? 'Due upon receipt of valid invoice' : 'Due upon invoice' }}' },
        { stage_name: 'Progress Claim 1', condition_label: '{{ $isSupplierPo ? 'Accepted delivery readiness / site mobilization' : 'Upon delivery or installation start' }}', percentage: '40', payment_term: '{{ $isSupplierPo ? '7 days from receipt of valid invoice' : '7 days from invoice date' }}' },
        { stage_name: 'Final Claim', condition_label: '{{ $isSupplierPo ? 'Accepted goods/services' : 'Upon completion and handover acceptance' }}', percentage: '20', payment_term: '{{ $isSupplierPo ? '14 days from receipt of valid invoice' : '14 days from invoice date' }}' },
    ];
}

function billingStagesHaveValues() {
    return Array.from(document.querySelectorAll('#billing-stages > div')).some((row) => {
        return Array.from(row.querySelectorAll('input:not([type="hidden"]), select, textarea')).some((field) => {
            if (field.type === 'checkbox') return field.checked;
            return String(field.value || '').trim() !== '';
        });
    });
}

function addBillingStage(values = {}) {
    const tbody = document.querySelector('#billing-stages');
    const template = document.querySelector('#billing-stage-template').content.cloneNode(true);
    const index = tbody.children.length;
    wireNamedTemplate(template, index, 'billing_stages');
    const title = template.querySelector('[data-stage-title]');
    if (title) title.textContent = `Billing stage ${index + 1}`;
    Object.entries(values).forEach(([name, value]) => {
        template.querySelectorAll(`[data-name="${name}"]`).forEach((field) => {
            if (field.type === 'checkbox') {
                field.checked = value === true || value === '1';
            } else {
                field.value = value;
            }
        });
    });
    tbody.appendChild(template);
    syncQuotationPreview();
}

document.querySelector('[data-add-billing-stage]')?.addEventListener('click', () => addBillingStage());

function applyProgressSchedule() {
    const tbody = document.querySelector('#billing-stages');
    tbody.innerHTML = '';
    document.querySelector('[data-payment-structure]').value = 'milestone';
    toggleBillingStages();
    defaultProgressStages().forEach(addBillingStage);
    syncQuotationPreview();
}

document.querySelector('[data-apply-progress-schedule]')?.addEventListener('click', applyProgressSchedule);

function updatePaymentLabel() {
    const daysField = document.querySelector('[data-payment-days]');
    const labelField = document.querySelector('[data-payment-label]');
    const issueField = document.querySelector('[data-issue-date]');
    const dueField = document.querySelector('[data-due-date]');
    if (!daysField || !labelField) return;

    const days = Number(daysField.value);
    if (daysField.value !== '' && labelField.value.trim() === '') {
        labelField.value = days === 0 ? 'Due upon invoice' : `${days} days from invoice date`;
    }

    if (daysField.value !== '' && issueField?.value && dueField && dueField.value === '') {
        const due = new Date(issueField.value + 'T00:00:00');
        due.setDate(due.getDate() + days);
        const year = due.getFullYear();
        const month = String(due.getMonth() + 1).padStart(2, '0');
        const day = String(due.getDate()).padStart(2, '0');
        dueField.value = `${year}-${month}-${day}`;
    }
}

document.querySelector('[data-payment-days]')?.addEventListener('change', updatePaymentLabel);
document.querySelector('[data-payment-structure]')?.addEventListener('change', function () {
    toggleBillingStages();
    if (this.value === 'milestone' && !billingStagesHaveValues()) {
        applyProgressSchedule();
        return;
    }
    syncQuotationPreview();
});
document.querySelector('form')?.addEventListener('input', refreshDocumentPreview);
document.querySelector('form')?.addEventListener('change', refreshDocumentPreview);

function syncPurchaseRequestSourceMode() {
    const prForm = document.querySelector('[data-pr-create-form]');
    if (!prForm) return;

    const selectedMode = prForm.querySelector('[data-pr-source-mode]:checked')?.value || 'supplier_quote';
    const isQuoteUpload = selectedMode === 'supplier_quote';
    const fileInput = prForm.querySelector('[data-pr-source-attachment]');
    const uploadWrap = prForm.querySelector('[data-pr-quote-upload-wrap]');
    const manualLines = prForm.querySelector('[data-pr-manual-lines]');
    const sourceNote = prForm.querySelector('[data-pr-source-note]');
    const submitButton = prForm.querySelector('[data-pr-primary-submit]');
    const submitHelp = prForm.querySelector('[data-pr-submit-help]');
    const hasFile = !!fileInput?.files?.length;

    if (uploadWrap) uploadWrap.classList.toggle('hidden', !isQuoteUpload);
    if (fileInput) {
        fileInput.disabled = !isQuoteUpload;
        fileInput.required = isQuoteUpload;
    }

    if (sourceNote) {
        sourceNote.required = !isQuoteUpload;
    }

    if (manualLines) {
        manualLines.classList.toggle('hidden', isQuoteUpload);
        manualLines.querySelectorAll('input, select, textarea, button').forEach((field) => {
            field.disabled = isQuoteUpload;

            if (field.name?.endsWith('[description]')) {
                field.required = !isQuoteUpload;
            }

            if (field.name?.endsWith('[quantity]')) {
                field.min = isQuoteUpload ? '0' : '0.001';
            }
        });
    }

    if (submitButton) {
        submitButton.textContent = isQuoteUpload ? 'Create draft & run OCR' : 'Save quotation exception';
        submitButton.disabled = isQuoteUpload && !hasFile;
    }

    if (submitHelp) {
        submitHelp.textContent = isQuoteUpload
            ? (hasFile ? 'Draft will open for quotation review.' : 'Choose a supplier quotation file to enable OCR.')
            : 'Enter lines and add the exception reason.';
    }

    filterWorkItemOptions();
}

document.querySelector('[data-pr-create-form]')?.addEventListener('change', function (event) {
    if (event.target.matches('[data-pr-source-mode], [data-pr-source-attachment]')) {
        syncPurchaseRequestSourceMode();
    }
});

toggleBillingStages();
filterRelatedDocuments();
syncProjectFromRelatedDocument();
filterWorkItemOptions();
syncPurchaseRequestSourceMode();
refreshDocumentPreview();
</script>
@endsection
