@php
    $attachments = $document->attachments ?? collect();
    $currency = strtoupper($document->currency ?: 'MYR');
    $items = $document->items ?? collect();

    $categoryPriority = match ($document->type) {
        'customer_po' => ['customer_po', 'supporting_document'],
        'supplier_quotation' => ['supplier_quote', 'supporting_document'],
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

    $previewTitle = match ($document->type) {
        'customer_po' => 'Customer PO received',
        'supplier_quotation' => 'Supplier quotation received',
        'purchase_request' => 'Purchase request record',
        'goods_receipt' => 'Receiving evidence record',
        default => 'Received document record',
    };

    $fileInstruction = match ($document->type) {
        'customer_po' => 'Upload the customer PO file as PO received so the team can review the customer-issued document.',
        'supplier_quotation' => 'Upload the supplier quotation PDF or image so procurement can review the supplier-issued offer.',
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
        'goods_receipt' => 'Evidence reference',
        default => 'Reference',
    };

    $dateLabel = match ($document->type) {
        'customer_po' => 'Date received',
        'goods_receipt' => 'Recorded date',
        'supplier_quotation' => 'Quote date',
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
                @if($supportingAttachments->isNotEmpty())
                    <span class="external-document-file-count">{{ $supportingAttachments->count() }} supporting file{{ $supportingAttachments->count() === 1 ? '' : 's' }}</span>
                @endif
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
                            <td class="text-right font-black">{{ $currency }} {{ number_format((float) $item->line_total, 2) }}</td>
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
