@php
    $steps = $meta['direction'] === 'outgoing'
        ? [
            ['key' => 'customer_quotation', 'label' => 'Quotation', 'slug' => 'customer-quotations'],
            ['key' => 'approval', 'label' => 'Approval'],
            ['key' => 'customer_po', 'label' => 'PO Received', 'slug' => 'customer-pos'],
            ['key' => 'fulfillment', 'label' => 'Service Completion'],
            ['key' => 'customer_invoice', 'label' => 'Invoice', 'slug' => 'customer-invoices'],
            ['key' => 'payment', 'label' => 'Payment'],
            ['key' => 'closed', 'label' => 'Closed'],
        ]
        : [
            ['key' => 'purchase_request', 'label' => 'Purchase Request', 'slug' => 'purchase-requests'],
            ['key' => 'supplier_quotation', 'label' => 'Supplier Quote', 'slug' => 'supplier-quotations'],
            ['key' => 'supplier_po', 'label' => 'Purchase Order', 'slug' => 'supplier-pos'],
            ['key' => 'goods_receipt', 'label' => 'Receiving', 'slug' => 'goods-receipts'],
            ['key' => 'supplier_invoice', 'label' => 'Supplier Invoice', 'slug' => 'supplier-invoices'],
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

<div class="workflow-timeline-card" aria-label="{{ $meta['direction'] === 'outgoing' ? 'Customer sales workflow' : 'Supplier procurement workflow' }}">
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
