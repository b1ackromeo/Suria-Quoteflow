@php
    $isActive = $isActive ?? true;
    $receiptLineTypes = $document->type === 'goods_receipt'
        ? $document->items->map(function ($item) {
            $type = strtolower((string) ($item->product?->type ?? ''));

            if (in_array($type, ['product', 'service'], true)) {
                return $type;
            }

            $unit = strtolower((string) $item->unit);

            return in_array($unit, ['job', 'lot', 'hour', 'day', 'month'], true) ? 'service' : 'product';
        })
        : collect();
    $receiptKind = $receiptLineTypes->isNotEmpty() && $receiptLineTypes->every(fn ($type) => $type === 'service')
        ? 'service'
        : ($receiptLineTypes->isNotEmpty() && $receiptLineTypes->every(fn ($type) => $type === 'product') ? 'goods' : 'mixed');
    $receiptOutputLabel = match ($receiptKind) {
        'service' => 'Service acceptance record PDF',
        'goods' => 'Goods receipt note PDF',
        default => 'Goods receipt and acceptance record PDF',
    };
    $receiptOutputNote = match ($receiptKind) {
        'service' => 'This is the accepted service record used to support supplier invoice matching.',
        'goods' => 'This goods receipt supports supplier invoice matching.',
        default => 'This goods receipt and acceptance record supports supplier invoice matching.',
    };
    $quotationOutputLabel = match ($document->status) {
        'draft' => 'Draft quotation preview',
        'pending_approval' => 'Quotation for approval',
        'approved' => 'Approved quotation PDF',
        default => 'Issued quotation PDF',
    };
    $invoiceOutputLabel = match ($document->status) {
        'draft' => 'Draft invoice preview',
        'pending_approval' => 'Invoice for approval',
        'approved' => 'Approved invoice PDF',
        default => 'Issued invoice PDF',
    };
    $supplierPoOutputLabel = match ($document->status) {
        'draft' => 'Draft purchase order preview',
        'pending_approval' => 'Purchase order for approval',
        'approved' => 'Approved purchase order ready to issue',
        'issued', 'fulfilled', 'closed' => 'Issued purchase order PDF',
        default => 'Purchase order preview',
    };
    $supplierPoOutputNote = match ($document->status) {
        'draft' => 'This is the purchase order being prepared before approval.',
        'pending_approval' => 'This purchase order is waiting for approval before it can be issued to the supplier.',
        'approved' => 'This purchase order is approved and ready to be issued to the supplier.',
        'issued', 'fulfilled', 'closed' => 'This is the issued purchase order PDF sent to the supplier.',
        default => 'This purchase order PDF reflects the current document status.',
    };
    $outputLabel = match ($document->type) {
        'purchase_request' => 'Approved purchase request PDF',
        'supplier_po' => $supplierPoOutputLabel,
        'goods_receipt' => $receiptOutputLabel,
        'customer_quotation' => $quotationOutputLabel,
        'customer_invoice' => $invoiceOutputLabel,
        default => 'Document PDF',
    };
    $outputNote = match ($document->type) {
        'purchase_request' => 'This is the approved purchase request record used to continue procurement.',
        'supplier_po' => $supplierPoOutputNote,
        'goods_receipt' => $receiptOutputNote,
        'customer_quotation' => 'This customer quotation PDF is prepared from this record.',
        'customer_invoice' => 'This customer invoice PDF is prepared from this record.',
        default => 'This document PDF reflects the current document status.',
    };
    $outputKicker = match ($document->type) {
        'customer_quotation' => 'Customer quotation PDF',
        'customer_invoice' => 'Customer invoice PDF',
        'supplier_po' => 'Purchase order PDF',
        'purchase_request' => 'Purchase request PDF',
        'goods_receipt' => $receiptOutputLabel,
        default => 'PDF preview',
    };
@endphp

<article class="generated-pdf-preview-card" data-generated-pdf-preview>
    <div class="generated-pdf-preview-header">
        <div>
            <p class="document-pane-kicker">{{ $outputKicker }}</p>
            <h3>{{ $outputLabel }}</h3>
            <p>{{ $outputNote }}</p>
        </div>
        <span
            class="status-chip status-{{ $document->status }}"
            aria-label="{{ $document->statusAriaLabel() }}"
            data-status-group="{{ $document->statusSemanticGroupDisplay() }}"
        >{{ $document->statusDisplay() }}</span>
    </div>
    <section class="generated-pdf-preview-frame">
        <div class="generated-pdf-loading" data-pdf-loading>Loading PDF preview...</div>
        <iframe
            class="generated-pdf-viewer"
            loading="lazy"
            onload="this.closest('.generated-pdf-preview-frame')?.querySelector('[data-pdf-loading]')?.classList.add('hidden')"
            @if($isActive) src="{{ route('documents.pdf', $document) }}#toolbar=1&navpanes=0" @endif
            data-pdf-src="{{ route('documents.pdf', $document) }}#toolbar=1&navpanes=0"
            title="{{ $outputLabel }}: {{ $document->document_number }}"
        ></iframe>
    </section>
</article>
