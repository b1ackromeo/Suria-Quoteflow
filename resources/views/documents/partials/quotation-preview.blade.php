@php
    $party = $document->customer ?? $document->supplier;
    $items = $document->items ?? collect();
    $billingStages = $document->billingStages ?? collect();
    $currency = strtoupper($document->currency ?: 'MYR');
    $companyProfile = \App\Models\CompanyProfile::active();
    $isInvoice = $document->isInvoice();
    $isPo = $document->isPurchaseOrder();
    $isQuotation = $document->isQuotation();

    $documentTitle = match ($document->type) {
        'customer_quotation' => 'QUOTATION',
        'supplier_quotation' => 'SUPPLIER QUOTATION',
        'customer_po' => 'PO RECEIVED',
        'supplier_po' => 'PURCHASE ORDER',
        'customer_invoice', 'supplier_invoice' => 'INVOICE',
        'purchase_request' => 'PURCHASE REQUEST',
        'goods_receipt' => 'RECEIVING RECORD',
        default => strtoupper($meta['singular'] ?? 'DOCUMENT'),
    };
    $documentKicker = match ($document->type) {
        'customer_quotation' => 'Customer sales document',
        'customer_po' => 'Customer purchase order record',
        'customer_invoice' => 'Customer billing document',
        'purchase_request' => 'Procurement request',
        'supplier_quotation' => 'Supplier quotation record',
        'supplier_po' => 'Supplier order document',
        'goods_receipt' => 'Receiving and acceptance document',
        'supplier_invoice' => 'Supplier invoice record',
        default => 'Business document',
    };
    $partyLabel = match ($document->type) {
        'customer_quotation' => 'Prepared for',
        'customer_po' => 'Customer',
        'customer_invoice' => 'Bill to',
        'supplier_po' => 'Vendor',
        'supplier_quotation', 'supplier_invoice', 'goods_receipt' => 'Supplier',
        default => 'Party',
    };
    $primaryDateLabel = match (true) {
        $document->type === 'customer_po' => 'Date received',
        $document->type === 'supplier_po' => 'PO date',
        $isInvoice => 'Invoice date',
        $isQuotation => 'Quote date',
        default => 'Issue date',
    };
    $secondaryDateLabel = $isQuotation ? 'Valid until' : ($document->type === 'supplier_po' ? 'Delivery date' : ($document->type === 'customer_po' ? 'Completion target' : ($isInvoice ? 'Due date' : 'Required by')));
    $lineDescriptionLabel = $isPo ? 'Ordered item / scope' : ($isInvoice ? 'Invoice description' : 'Description');
    $totalLabel = $isQuotation ? 'Quotation Total' : ($isPo ? 'PO Total' : ($isInvoice ? 'Invoice Total' : 'Document Total'));
    $scopeLabel = $isQuotation ? 'Project Scope Summary' : ($isPo ? 'Order Scope / Delivery Notes' : ($isInvoice ? 'Billing Summary' : 'Notes'));
    $paymentLabel = $document->payment_terms_label ?: ($document->payment_terms_type === 'milestone' ? 'Milestone-Based' : 'Not specified');
@endphp

<article
    id="quotation-preview-card-{{ $document->id }}"
    class="scroll-mt-4 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
    data-document-preview-card
    data-quotation-preview-card
>
    @include('documents.partials.business-document-header', [
        'companyProfile' => $companyProfile,
        'documentKicker' => $documentKicker,
        'documentTitle' => $documentTitle,
        'documentNumber' => $document->document_number,
        'statusLabel' => strtoupper($document->statusDisplay()),
    ])

    <div class="space-y-4 p-5 text-sm text-slate-900">
        <div class="grid gap-3 md:grid-cols-2">
            <div class="border-l-4 border-blue-600 bg-slate-50 p-3">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500">{{ $partyLabel }}</p>
                <p class="mt-2 font-bold">{{ $party?->name ?? 'Not selected' }}</p>
                @if($party?->email)
                    <p class="mt-1 text-xs font-semibold text-slate-600">{{ $party->email }}</p>
                @endif
                @if($document->delivery_to)
                    <p class="mt-2 whitespace-pre-line text-xs font-medium leading-5 text-slate-600">{{ $document->delivery_to }}</p>
                @endif
            </div>
            <div class="divide-y divide-slate-200 border border-slate-200 bg-slate-50">
                <div class="flex justify-between gap-3 px-3 py-2">
                    <span class="font-semibold text-slate-500">{{ $primaryDateLabel }}</span>
                    <span class="font-bold">{{ optional($document->issue_date)->format('d M Y') ?? '-' }}</span>
                </div>
                <div class="flex justify-between gap-3 px-3 py-2">
                    <span class="font-semibold text-slate-500">{{ $secondaryDateLabel }}</span>
                    <span class="font-bold">{{ optional($document->due_date)->format('d M Y') ?? '-' }}</span>
                </div>
                <div class="flex justify-between gap-3 px-3 py-2">
                    <span class="font-semibold text-slate-500">Reference</span>
                    <span class="text-right font-bold">{{ $document->external_reference ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="grid gap-2 sm:grid-cols-3">
            <div class="border border-slate-200 bg-white p-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Currency</p>
                <p class="mt-1 font-bold">{{ $currency }}</p>
            </div>
            <div class="border border-slate-200 bg-white p-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Payment terms</p>
                <p class="mt-1 font-bold">{{ $paymentLabel }}</p>
            </div>
            <div class="border border-slate-200 bg-white p-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Project / site</p>
                <p class="mt-1 font-bold">{{ $document->project_name ?: '-' }}</p>
            </div>
        </div>

        @if($document->notes)
            <section class="border-t-4 border-blue-600 bg-slate-50 p-3">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-700">{{ $scopeLabel }}</p>
                <p class="mt-2 whitespace-pre-line text-xs font-medium leading-5 text-slate-700">{{ $document->notes }}</p>
            </section>
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-xs">
                <thead class="bg-[#0a345f] text-white">
                    <tr>
                        <th class="px-3 py-2 font-bold">No.</th>
                        <th class="px-3 py-2 font-bold">{{ $lineDescriptionLabel }}</th>
                        <th class="px-3 py-2 text-right font-bold">Qty</th>
                        <th class="px-3 py-2 font-bold">Unit</th>
                        <th class="px-3 py-2 text-right font-bold">Unit Price</th>
                        <th class="px-3 py-2 text-right font-bold">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($items as $item)
                        <tr>
                            <td class="px-3 py-3 align-top">{{ $loop->iteration }}</td>
                            <td class="px-3 py-3 align-top">
                                <p class="font-bold">{{ $item->description }}</p>
                                @if($item->product?->name && $item->product->name !== $item->description)
                                    <p class="mt-1 text-[11px] font-medium text-slate-500">{{ $item->product->name }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right align-top">{{ number_format((float) $item->quantity, 3) }}</td>
                            <td class="px-3 py-3 align-top">{{ $item->unit }}</td>
                            <td class="px-3 py-3 text-right align-top">{{ number_format((float) $item->unit_price, 2) }}</td>
                            <td class="px-3 py-3 text-right align-top font-bold">{{ number_format((float) $item->line_total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-6 text-center font-semibold text-slate-500">No line items yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($billingStages->isNotEmpty())
            <section class="overflow-hidden border border-slate-200">
                <div class="bg-slate-50 px-3 py-2">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-slate-700">{{ $isInvoice ? 'Progress Billing Summary' : 'Payment Schedule' }}</p>
                </div>
                <table class="min-w-full text-left text-xs">
                    <thead class="bg-slate-100 text-slate-700">
                        <tr>
                            <th class="px-3 py-2 font-bold">Billing stage</th>
                            @unless($isInvoice)
                                <th class="px-3 py-2 font-bold">{{ $document->type === 'supplier_po' ? 'Supplier may invoice when' : ($document->type === 'customer_po' ? 'Customer may be invoiced when' : 'Billing condition') }}</th>
                            @endunless
                            <th class="px-3 py-2 text-right font-bold">%</th>
                            <th class="px-3 py-2 text-right font-bold">Amount</th>
                            @unless($isInvoice)
                                <th class="px-3 py-2 font-bold">Payment term</th>
                            @else
                                <th class="px-3 py-2 text-right font-bold">Previously invoiced</th>
                                <th class="px-3 py-2 text-right font-bold">This invoice</th>
                                <th class="px-3 py-2 text-right font-bold">Remaining</th>
                            @endunless
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($billingStages as $stage)
                            <tr class="{{ $stage->is_current ? 'bg-slate-50 font-bold' : '' }}">
                                <td class="px-3 py-2 font-semibold">{{ $stage->stage_name }}</td>
                                @unless($isInvoice)
                                    <td class="px-3 py-2">{{ $stage->condition_label ?: '-' }}</td>
                                @endunless
                                <td class="px-3 py-2 text-right">{{ $stage->percentage ? number_format((float) $stage->percentage, 2) : '-' }}</td>
                                <td class="px-3 py-2 text-right">{{ $stage->amount ? $currency.' '.number_format((float) $stage->amount, 2) : '-' }}</td>
                                @unless($isInvoice)
                                    <td class="px-3 py-2">{{ $stage->payment_term ?: '-' }}</td>
                                @else
                                    <td class="px-3 py-2 text-right">{{ $currency }} {{ number_format((float) $stage->previously_invoiced, 2) }}</td>
                                    <td class="px-3 py-2 text-right">{{ $currency }} {{ number_format((float) $stage->current_invoice, 2) }}</td>
                                    <td class="px-3 py-2 text-right">{{ $currency }} {{ number_format((float) $stage->remaining_amount, 2) }}</td>
                                @endunless
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        <div class="grid gap-4 md:grid-cols-[1fr_20rem]">
            <section class="border-t-4 border-blue-600 bg-slate-50 p-3">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-700">Terms</p>
                <p class="mt-2 whitespace-pre-line text-xs font-medium leading-5 text-slate-700">{{ $document->terms ?: 'Terms will be confirmed in the issued document.' }}</p>
            </section>
            <div class="divide-y divide-slate-200 border border-slate-200">
                <div class="flex justify-between gap-3 px-3 py-2">
                    <span class="font-semibold">Subtotal</span>
                    <span class="font-bold">{{ $currency }} {{ number_format((float) $document->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between gap-3 px-3 py-2">
                    <span class="font-semibold">Tax</span>
                    <span class="font-bold">{{ $currency }} {{ number_format((float) $document->tax_total, 2) }}</span>
                </div>
                <div class="flex justify-between gap-3 bg-[#0a345f] px-3 py-3 text-white">
                    <span class="font-bold">{{ $totalLabel }}</span>
                    <span class="font-bold">{{ $currency }} {{ number_format((float) $document->total, 2) }}</span>
                </div>
            </div>
        </div>
    </div>
</article>
