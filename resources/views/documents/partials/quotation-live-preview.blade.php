@php
    $companyProfile = \App\Models\CompanyProfile::active();
    $isInvoice = in_array($meta['type'], ['customer_invoice', 'supplier_invoice'], true);
    $isPo = in_array($meta['type'], ['customer_po', 'supplier_po'], true);
    $isQuotation = in_array($meta['type'], ['customer_quotation', 'supplier_quotation'], true);
    $documentTitle = match ($meta['type']) {
        'customer_quotation' => 'QUOTATION',
        'supplier_quotation' => 'SUPPLIER QUOTATION',
        'customer_po' => 'PO RECEIVED',
        'supplier_po' => 'PURCHASE ORDER',
        'customer_invoice', 'supplier_invoice' => 'INVOICE',
        'purchase_request' => 'PURCHASE REQUEST',
        'goods_receipt' => 'RECEIVING RECORD',
        default => strtoupper($meta['singular'] ?? 'DOCUMENT'),
    };
    $documentKicker = match ($meta['type']) {
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
    $draftLabel = match (true) {
        $isQuotation => 'Draft quotation',
        $isPo => 'Draft PO',
        $isInvoice => 'Draft invoice',
        default => 'Draft document',
    };
    $partyLabel = match ($meta['type']) {
        'customer_quotation' => 'Prepared for',
        'customer_po' => 'Customer',
        'customer_invoice' => 'Bill to',
        'supplier_po' => 'Vendor',
        'supplier_quotation', 'supplier_invoice', 'goods_receipt' => 'Supplier',
        default => $meta['party'] === 'customer' ? 'Customer' : 'Supplier',
    };
    $partyFallback = $meta['party'] === 'customer' ? 'Select customer' : 'Select supplier';
    $primaryDateLabel = match (true) {
        $meta['type'] === 'customer_po' => 'Date received',
        $meta['type'] === 'supplier_po' => 'PO date',
        $isInvoice => 'Invoice date',
        $isQuotation => 'Quote date',
        default => 'Issue date',
    };
    $secondaryDateLabel = $isQuotation ? 'Valid until' : ($meta['type'] === 'supplier_po' ? 'Delivery date' : ($meta['type'] === 'customer_po' ? 'Completion target' : ($isInvoice ? 'Due date' : 'Required by')));
    $secondaryDateFallback = $isQuotation ? 'Valid until' : 'Not set';
    $referenceFallback = match (true) {
        $isQuotation => 'Inquiry / RFQ reference',
        $meta['type'] === 'customer_po' => 'PO number received from customer',
        $meta['type'] === 'supplier_po' => 'Supplier quote / purchase request reference',
        $isInvoice => 'PO / delivery / billing reference',
        default => 'Reference',
    };
    $scopeLabel = $isQuotation ? 'Project Scope Summary' : ($isPo ? 'Order Scope / Delivery Notes' : ($isInvoice ? 'Billing Summary' : 'Notes'));
    $scopeFallback = $isQuotation ? 'Project scope summary will appear here.' : ($isPo ? 'Order notes or delivery instructions will appear here.' : 'Billing notes will appear here.');
    $lineDescriptionLabel = $isPo ? 'Ordered item / scope' : ($isInvoice ? 'Invoice description' : 'Description');
    $emptyLineCopy = $isQuotation ? 'Add line items to preview the quotation value.' : ($isPo ? 'Add ordered items to preview the PO value.' : 'Add billable items to preview the invoice value.');
    $scheduleTitle = $isInvoice ? 'Progress Billing Summary' : 'Payment Schedule';
    $totalLabel = $isQuotation ? 'Quotation Total' : ($isPo ? 'PO Total' : ($isInvoice ? 'Invoice Total' : 'Document Total'));
@endphp

<article
    class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
    data-live-document-preview
    data-live-quotation-preview
>
    @include('documents.partials.business-document-header', [
        'companyProfile' => $companyProfile,
        'documentKicker' => $documentKicker,
        'documentTitle' => $documentTitle,
        'documentNumber' => $document->document_number ?: $draftLabel,
    ])

    <div class="space-y-4 p-5 text-sm text-slate-900">
        <div class="grid gap-3">
            <div class="border-l-4 border-blue-600 bg-slate-50 p-3">
                <p class="text-[11px] font-black uppercase tracking-wide text-slate-500">{{ $partyLabel }}</p>
                <p class="mt-2 font-black" data-preview-party>{{ $document->exists ? $document->partyName() : $partyFallback }}</p>
                <p class="mt-2 whitespace-pre-line text-xs font-medium leading-5 text-slate-600" data-preview-delivery>{{ $document->delivery_to ?: 'Delivery / service location' }}</p>
            </div>
            <div class="divide-y divide-slate-200 border border-slate-200 bg-slate-50">
                <div class="flex justify-between gap-3 px-3 py-2">
                    <span class="font-semibold text-slate-500">{{ $primaryDateLabel }}</span>
                    <span class="text-right font-black" data-preview-date>{{ optional($document->issue_date)->format('d M Y') ?? 'Issue date' }}</span>
                </div>
                <div class="flex justify-between gap-3 px-3 py-2">
                    <span class="font-semibold text-slate-500">{{ $secondaryDateLabel }}</span>
                    <span class="text-right font-black" data-preview-valid>{{ optional($document->due_date)->format('d M Y') ?? $secondaryDateFallback }}</span>
                </div>
                <div class="flex justify-between gap-3 px-3 py-2">
                    <span class="font-semibold text-slate-500">Reference</span>
                    <span class="text-right font-black" data-preview-reference>{{ $document->external_reference ?: $referenceFallback }}</span>
                </div>
            </div>
        </div>

        <div class="grid gap-2 sm:grid-cols-3">
            <div class="border border-slate-200 bg-white p-3">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Currency</p>
                <p class="mt-1 font-black" data-preview-currency>{{ $document->currency ?: 'MYR' }}</p>
            </div>
            <div class="border border-slate-200 bg-white p-3">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Payment terms</p>
                <p class="mt-1 font-black" data-preview-payment>{{ $document->payment_terms_label ?: 'Not specified' }}</p>
            </div>
            <div class="border border-slate-200 bg-white p-3">
                <p class="text-[10px] font-black uppercase tracking-wide text-slate-500">Project / site</p>
                <p class="mt-1 font-black" data-preview-site>{{ $document->project_name ?: 'Project / site' }}</p>
            </div>
        </div>

        <section class="border-t-4 border-blue-600 bg-slate-50 p-3">
            <p class="text-[11px] font-black uppercase tracking-wide text-slate-700">{{ $scopeLabel }}</p>
            <p class="mt-2 whitespace-pre-line text-xs font-medium leading-5 text-slate-700" data-preview-scope>{{ $document->notes ?: $scopeFallback }}</p>
        </section>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-xs">
                <thead class="bg-[#0a345f] text-white">
                    <tr>
                        <th class="px-3 py-2 font-black">No.</th>
                        <th class="px-3 py-2 font-black">{{ $lineDescriptionLabel }}</th>
                        <th class="px-3 py-2 text-right font-black">Qty</th>
                        <th class="px-3 py-2 font-black">Unit</th>
                        <th class="px-3 py-2 text-right font-black">Unit Price</th>
                        <th class="px-3 py-2 text-right font-black">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200" data-preview-lines>
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center font-semibold text-slate-500">{{ $emptyLineCopy }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <section class="hidden overflow-hidden border border-slate-200" data-preview-schedule>
            <div class="bg-slate-50 px-3 py-2">
                <p class="text-[11px] font-black uppercase tracking-wide text-slate-700">{{ $scheduleTitle }}</p>
            </div>
            <table class="min-w-full text-left text-xs">
                <thead class="bg-slate-100 text-slate-700">
                    <tr>
                        <th class="px-3 py-2 font-black">Billing stage</th>
                        <th class="px-3 py-2 font-black">{{ $isInvoice ? 'Invoice condition' : ($meta['type'] === 'supplier_po' ? 'Supplier may invoice when' : ($meta['type'] === 'customer_po' ? 'Customer may be invoiced when' : 'Billing condition')) }}</th>
                        <th class="px-3 py-2 text-right font-black">%</th>
                        <th class="px-3 py-2 text-right font-black">Amount</th>
                        <th class="px-3 py-2 font-black">Payment term</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200" data-preview-schedule-lines></tbody>
            </table>
        </section>

        <div class="grid gap-4">
            <section class="border-t-4 border-blue-600 bg-slate-50 p-3">
                <p class="text-[11px] font-black uppercase tracking-wide text-slate-700">Terms</p>
                <p class="mt-2 whitespace-pre-line text-xs font-medium leading-5 text-slate-700" data-preview-terms>{{ $document->terms ?: 'Terms will appear here.' }}</p>
            </section>
            <div class="divide-y divide-slate-200 border border-slate-200">
                <div class="flex justify-between gap-3 px-3 py-2">
                    <span class="font-semibold">Subtotal</span>
                    <span class="font-black" data-preview-subtotal>MYR 0.00</span>
                </div>
                <div class="flex justify-between gap-3 px-3 py-2">
                    <span class="font-semibold">Tax</span>
                    <span class="font-black" data-preview-tax>MYR 0.00</span>
                </div>
                <div class="flex justify-between gap-3 bg-[#0a345f] px-3 py-3 text-white">
                    <span class="font-black">{{ $totalLabel }}</span>
                    <span class="font-black" data-preview-total>MYR 0.00</span>
                </div>
            </div>
        </div>
    </div>
</article>
