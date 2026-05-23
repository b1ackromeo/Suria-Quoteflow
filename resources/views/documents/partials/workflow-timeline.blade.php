@php
    $chainNodes = collect($documentChain['nodes'] ?? []);
    $hasDocumentChain = (bool) ($documentChain['has_chain'] ?? false);
    $chainCurrentIndex = $chainNodes->search(fn ($node) => (int) ($node['id'] ?? 0) === (int) (($document ?? null)?->id ?? 0));
    $chainCurrentIndex = $chainCurrentIndex === false ? 0 : $chainCurrentIndex;

    $steps = $meta['direction'] === 'outgoing'
        ? [
            ['key' => 'customer_quotation', 'label' => 'Quotation', 'slug' => 'customer-quotations'],
            ['key' => 'approval', 'label' => 'Approval'],
            ['key' => 'customer_po', 'label' => 'Customer PO received', 'slug' => 'customer-pos'],
            ['key' => 'fulfillment', 'label' => 'Service completed'],
            ['key' => 'customer_invoice', 'label' => 'Invoice', 'slug' => 'customer-invoices'],
            ['key' => 'payment', 'label' => 'Payment'],
            ['key' => 'closed', 'label' => 'Closed'],
        ]
        : [
            ['key' => 'purchase_request', 'label' => 'Purchase request', 'slug' => 'purchase-requests'],
            ['key' => 'supplier_quotation', 'label' => 'Supplier quotation', 'slug' => 'supplier-quotations'],
            ['key' => 'supplier_po', 'label' => 'Purchase order', 'slug' => 'supplier-pos'],
            ['key' => 'goods_receipt', 'label' => 'Goods receipt', 'slug' => 'goods-receipts'],
            ['key' => 'supplier_invoice', 'label' => 'Supplier invoice', 'slug' => 'supplier-invoices'],
            ['key' => 'matching', 'label' => 'Matching'],
            ['key' => 'payment', 'label' => 'Payment'],
            ['key' => 'closed', 'label' => 'Closed'],
        ];

    $activeKey = $activeKey ?? $meta['type'];
    if (($document ?? null)?->status === 'pending_approval') {
        $activeKey = collect($steps)->contains(fn ($step) => $step['key'] === 'approval') ? 'approval' : $meta['type'];
    } elseif (($document ?? null)?->status === 'fulfilled') {
        $activeKey = 'fulfillment';
    } elseif (($document ?? null)?->status === 'matched') {
        $activeKey = 'matching';
    } elseif (($document ?? null)?->status === 'paid') {
        $activeKey = 'payment';
    } elseif (($document ?? null)?->status === 'closed') {
        $activeKey = 'closed';
    }

    $activeIndex = collect($steps)->search(fn ($step) => $step['key'] === $activeKey);
    $activeIndex = $activeIndex === false ? 0 : $activeIndex;
@endphp

@if($hasDocumentChain)
    <div class="workflow-timeline-card document-chain-timeline" aria-label="Linked document progress" data-document-chain-timeline>
        @foreach($chainNodes as $index => $node)
            @php
                $state = $index < $chainCurrentIndex ? 'complete' : ($index === $chainCurrentIndex ? 'active' : 'pending');
            @endphp
            <a class="workflow-timeline-step workflow-timeline-step-{{ $state }}" href="{{ route('documents.show', $node['id']) }}" @if((int) $node['id'] === (int) $document->id) aria-current="page" @endif>
                <span class="workflow-timeline-dot">{{ $index + 1 }}</span>
                <span class="workflow-timeline-label">
                    <strong>{{ $node['document_number'] }}</strong>
                    <span>{{ $node['type_label'] }} - {{ $node['status_label'] }}</span>
                </span>
            </a>
        @endforeach
    </div>
    @if($documentChain['truncated'] ?? false)
        <p class="document-chain-note" data-document-chain-limited>Only the nearest linked records are shown.</p>
    @endif
@else
    <div class="workflow-timeline-card" aria-label="{{ $meta['direction'] === 'outgoing' ? 'Customer sales document progress' : 'Supplier purchasing document progress' }}">
        @foreach($steps as $index => $step)
            @php
                $state = $index < $activeIndex ? 'complete' : ($index === $activeIndex ? 'active' : 'pending');
            @endphp
            @if(isset($step['slug']))
                <a class="workflow-timeline-step workflow-timeline-step-{{ $state }}" href="{{ route('documents.index', $step['slug']) }}">
                    <span class="workflow-timeline-dot">{{ $index + 1 }}</span>
                    <span class="workflow-timeline-label">{{ $step['label'] }}</span>
                </a>
            @else
                <span class="workflow-timeline-step workflow-timeline-step-{{ $state }}">
                    <span class="workflow-timeline-dot">{{ $index + 1 }}</span>
                    <span class="workflow-timeline-label">{{ $step['label'] }}</span>
                </span>
            @endif
        @endforeach
    </div>
@endif
