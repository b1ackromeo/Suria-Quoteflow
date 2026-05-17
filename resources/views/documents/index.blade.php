@extends('layouts.app', [
    'title' => $meta['label'],
    'contentMode' => 'fullscreen',
])

@php
    $canWrite = auth()->user()->hasRole('admin', 'manager')
        || ($meta['direction'] === 'outgoing' && auth()->user()->hasRole('sales', 'accounts'))
        || ($meta['direction'] === 'incoming' && auth()->user()->hasRole('procurement', 'accounts'));
    $isQuotationIndex = in_array($meta['type'], ['customer_quotation', 'supplier_quotation'], true);
    $usesReceivedFilePreview = in_array($meta['type'], ['customer_po', 'supplier_quotation', 'purchase_request', 'goods_receipt'], true);
    $moduleKicker = match ($meta['type']) {
        'customer_quotation' => 'Customer sales',
        'customer_po' => 'Customer order intake',
        'customer_invoice' => 'Customer billing',
        'purchase_request' => 'Internal procurement',
        'supplier_quotation' => 'Supplier sourcing',
        'supplier_po' => 'Supplier ordering',
        'goods_receipt' => 'Receiving',
        'supplier_invoice' => 'Supplier billing',
        default => $meta['direction'] === 'outgoing' ? 'Customer workflow' : 'Supplier workflow',
    };
    $moduleSubtitle = match ($meta['type']) {
        'customer_quotation' => 'Prepare, approve, issue, and reuse quotations for customer sales work.',
        'customer_po' => 'Record PO files received from customers before delivery, invoicing, and payment follow-up.',
        'customer_invoice' => 'Issue invoices to customers and track billing stage, payment, and balance.',
        'purchase_request' => 'Capture internal procurement requests before supplier sourcing or purchase order creation.',
        'supplier_quotation' => 'Store supplier quote files and compare them before creating a purchase order.',
        'supplier_po' => 'Create, approve, issue, and preview purchase orders that will be sent to suppliers.',
        'goods_receipt' => 'Record delivered materials or accepted services before supplier invoice matching.',
        'supplier_invoice' => 'Preview supplier invoice files, verify OCR draft details, match, approve, and pay.',
        default => 'Review records and preview the selected document without leaving this page.',
    };
    $previewTitle = match ($meta['type']) {
        'customer_quotation' => 'Quotation Preview',
        'supplier_quotation' => 'Supplier Quote File Preview',
        'customer_po' => 'Customer PO File Preview',
        'supplier_po' => 'Purchase Order Output',
        'customer_invoice' => 'Invoice Preview',
        'supplier_invoice' => 'Supplier Invoice Preview',
        'purchase_request' => 'Purchase Request Preview',
        'goods_receipt' => 'Receiving Preview',
        default => 'Document Preview',
    };
    $previewKicker = match ($meta['type']) {
        'customer_quotation', 'customer_invoice' => 'Customer-facing output',
        'supplier_po' => 'Supplier-facing output',
        'purchase_request', 'goods_receipt' => 'Internal record output',
        'customer_po', 'supplier_quotation', 'supplier_invoice' => 'Uploaded file preview',
        default => 'Preview',
    };
    $previewCaption = match ($meta['type']) {
        'customer_quotation' => 'Select a quotation to review the customer-facing PDF before opening the full workflow record.',
        'customer_po' => 'Select a customer PO to preview the uploaded customer-issued file and recorded summary.',
        'customer_invoice' => 'Select an invoice to review the customer-facing billing PDF.',
        'purchase_request' => 'Select a request to review the approved procurement record used for sourcing or PO creation.',
        'supplier_quotation' => 'Select a supplier quotation to preview the uploaded supplier file before creating a purchase order.',
        'supplier_po' => 'Drafts show the PO being prepared. Issued records show the final PDF sent to the supplier.',
        'goods_receipt' => 'Select a receiving record to review the goods receipt or service acceptance evidence.',
        'supplier_invoice' => 'Select a supplier invoice to preview the uploaded supplier PDF or image for verification and matching.',
        default => 'Select a record to preview it here.',
    };
    $createActionLabel = match ($meta['type']) {
        'customer_po' => 'Record PO Received',
        'supplier_po' => 'Create Purchase Order',
        default => 'New '.$meta['singular'],
    };
    $issueDateLabel = match ($meta['type']) {
        'customer_po' => 'Date Received',
        'supplier_po' => 'PO Date',
        'customer_invoice', 'supplier_invoice' => 'Invoice Date',
        'supplier_quotation' => 'Quote Date',
        'purchase_request' => 'Request Date',
        'goods_receipt' => 'Received Date',
        default => 'Issue Date',
    };
    $rowReferenceLabel = match ($meta['type']) {
        'customer_quotation' => 'Customer ref',
        'customer_po' => 'Customer PO no.',
        'customer_invoice' => 'Billing ref',
        'purchase_request' => 'Request ref',
        'supplier_quotation' => 'Supplier quote no.',
        'supplier_po' => 'Source',
        'goods_receipt' => 'Evidence ref',
        'supplier_invoice' => 'Supplier invoice no.',
        default => 'Reference',
    };
    $emptyListCopy = match ($meta['type']) {
        'supplier_po' => 'Create a purchase order from an approved request, accepted supplier quotation, or approved direct procurement.',
        'goods_receipt' => 'Record a receiving record after goods arrive or service work is accepted.',
        'supplier_invoice' => 'Record the supplier invoice, upload the invoice file, then verify and match it before payment.',
        default => 'Create the first record to start this workflow.',
    };
    $filterStatuses = $statuses;
    if ($meta['type'] === 'customer_po') {
        $filterStatuses['issued'] = 'Accepted';
    }
@endphp

@section('content')
<div
    class="fullscreen-workspace document-workbench"
    data-document-workspace
    @if($isQuotationIndex) data-quotation-workspace @endif
>
    <section class="document-browser-header">
        <div>
            <p class="document-pane-kicker">{{ $moduleKicker }}</p>
            <h2 class="document-pane-title">{{ $meta['label'] }}</h2>
            <p class="document-browser-subtitle">{{ $moduleSubtitle }}</p>
        </div>
        <div class="document-browser-actions">
            <span class="status-chip {{ $meta['direction'] === 'outgoing' ? 'status-approved' : 'status-pending_approval' }}">{{ $documents->total() }} records</span>
            <a class="btn btn-secondary" href="{{ route('documents.export', $meta['slug']) }}">Export CSV</a>
            @if($canWrite)
                <a class="btn btn-primary" href="{{ route('documents.create', $meta['slug']) }}">{{ $createActionLabel }}</a>
            @endif
        </div>
    </section>

    <div class="document-workbench-body">
        <section class="document-list-pane">
            <form class="document-filterbar" method="get">
                <input class="form-input mt-0" type="search" name="q" value="{{ request('q') }}" placeholder="Search document no., reference, party, or item">
                <select class="form-input mt-0" name="status">
                    <option value="">All statuses</option>
                    @foreach($filterStatuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <input class="form-input mt-0" type="date" name="from" value="{{ request('from') }}">
                <input class="form-input mt-0" type="date" name="to" value="{{ request('to') }}">
                <button class="btn btn-primary shrink-0" type="submit">Apply</button>
            </form>

            <section
                class="document-list-scroll"
                data-document-list
                @if($isQuotationIndex) data-quotation-list @endif
            >
                @forelse($documents as $document)
                    @php
                        $useGeneratedOutputPreview = in_array($document->type, ['customer_quotation', 'customer_invoice'], true)
                            || $document->shouldPreviewGeneratedPdfOutput();
                        $rowTotalLabel = $document->type === 'goods_receipt'
                            ? rtrim(rtrim(number_format((float) $document->items->sum('quantity'), 3), '0'), '.').' received'
                            : $document->currency.' '.number_format($document->total, 2);
                    @endphp
                    <article
                        class="document-list-row"
                        data-document-row
                        @if($isQuotationIndex) data-quotation-row @endif
                        data-preview-target="document-preview-{{ $document->id }}"
                        data-document-number="{{ $document->document_number }}"
                        data-preview-active="{{ $loop->first ? 'true' : 'false' }}"
                    >
                        <div class="document-list-row-main">
                            <div class="min-w-0">
                                <a class="document-row-title" href="{{ route('documents.show', $document) }}">{{ $document->document_number }}</a>
                                <p class="document-row-party">{{ $document->partyName() }}</p>
                            </div>
                            <span class="status-chip status-{{ $document->status }}">{{ $document->statusDisplay() }}</span>
                        </div>
                        <div class="document-row-footer">
                            <div class="document-row-details">
                                <span><b>{{ $issueDateLabel }}</b> {{ optional($document->issue_date)->format('d M Y') ?? '-' }}</span>
                                <span><b>{{ $rowReferenceLabel }}</b> {{ $document->external_reference ?: '-' }}</span>
                            </div>
                            <div class="document-row-total-action">
                                <strong>{{ $rowTotalLabel }}</strong>
                                <div class="document-row-actions">
                                    <button
                                        class="document-row-preview-button"
                                        type="button"
                                        data-preview-trigger
                                        data-preview-target="document-preview-{{ $document->id }}"
                                        data-document-number="{{ $document->document_number }}"
                                        aria-controls="document-preview-{{ $document->id }}"
                                        aria-pressed="{{ $loop->first ? 'true' : 'false' }}"
                                    >Preview</button>
                                    <a class="btn btn-primary document-row-open-link" href="{{ route('documents.show', $document) }}">Open</a>
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="document-list-empty">
                        <h3>No records yet</h3>
                        <p>{{ $emptyListCopy }}</p>
                    </div>
                @endforelse
            </section>

            <div class="border-t border-slate-200 bg-white px-5 py-3">{{ $documents->links() }}</div>
        </section>

        <aside
            class="document-preview-pane"
            data-document-preview-list
            data-document-preview-panel
            @if($isQuotationIndex) data-quotation-preview-list data-quotation-preview-panel @endif
        >
            <div class="document-preview-toolbar">
                <div>
                    <p class="document-pane-kicker">{{ $previewKicker }}</p>
                    <h2 class="text-base font-black text-slate-950">{{ $previewTitle }}</h2>
                    <p class="document-preview-copy">{{ $previewCaption }}</p>
                </div>
            </div>

            <div class="document-preview-scroll">
                @forelse($documents as $document)
                    @php
                        $useGeneratedOutputPreview = in_array($document->type, ['customer_quotation', 'customer_invoice'], true)
                            || $document->shouldPreviewGeneratedPdfOutput();
                    @endphp
                    <div class="{{ $loop->first ? '' : 'hidden' }}" data-preview-card-wrapper id="document-preview-{{ $document->id }}">
                        @if($useGeneratedOutputPreview)
                            @include('documents.partials.generated-pdf-output-preview', ['document' => $document, 'meta' => $meta, 'isActive' => $loop->first])
                        @elseif($meta['type'] === 'supplier_invoice')
                            @include('documents.partials.supplier-invoice-file-preview', ['document' => $document, 'meta' => $meta, 'previewOnly' => true])
                        @elseif($usesReceivedFilePreview)
                            @include('documents.partials.external-document-preview', ['document' => $document, 'meta' => $meta])
                        @else
                            @include('documents.partials.quotation-preview', ['document' => $document, 'meta' => $meta])
                        @endif
                    </div>
                @empty
                    <div class="document-preview-empty">
                        <p class="document-pane-kicker">Preview</p>
                        <h2>No record selected</h2>
                        <p>{{ $previewCaption }}</p>
                    </div>
                @endforelse
            </div>
        </aside>
    </div>
</div>

<script>
(() => {
    const rows = document.querySelectorAll('[data-document-row]');
    const triggers = document.querySelectorAll('[data-preview-trigger]');
    const wrappers = document.querySelectorAll('[data-preview-card-wrapper]');
    const selectedLabel = document.querySelector('[data-selected-preview-number]');

    function loadOutputPreview(wrapper) {
        if (!wrapper) return;

        wrapper.querySelectorAll('iframe[data-pdf-src]').forEach((frame) => {
            if (!frame.getAttribute('src')) {
                frame.setAttribute('src', frame.dataset.pdfSrc);
            }
        });
    }

    function selectPreview(targetId, documentNumber) {
        wrappers.forEach((wrapper) => wrapper.classList.toggle('hidden', wrapper.id !== targetId));
        rows.forEach((row) => {
            const active = row.dataset.previewTarget === targetId;
            row.dataset.previewActive = active ? 'true' : 'false';
        });
        triggers.forEach((trigger) => {
            trigger.setAttribute('aria-pressed', trigger.dataset.previewTarget === targetId ? 'true' : 'false');
        });

        loadOutputPreview(document.getElementById(targetId));
        if (selectedLabel) selectedLabel.textContent = documentNumber || 'None';
    }

    triggers.forEach((trigger) => {
        trigger.addEventListener('click', () => {
            selectPreview(trigger.dataset.previewTarget, trigger.dataset.documentNumber);
        });
    });

    loadOutputPreview(document.querySelector('[data-preview-card-wrapper]:not(.hidden)'));
})();
</script>
@endsection
