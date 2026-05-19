@php
    $companyProfile = \App\Models\CompanyProfile::active();
    $partyFallback = 'Select supplier';
@endphp

<article
    class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
    data-live-document-preview
    data-live-receipt-preview
>
    @include('documents.partials.business-document-header', [
        'companyProfile' => $companyProfile,
        'documentKicker' => 'Goods receipt record',
        'documentTitle' => 'GOODS RECEIPT NOTE',
        'documentNumber' => $document->document_number ?: 'Draft receipt',
        'kickerAttributes' => 'data-receipt-label="subtitle"',
        'titleAttributes' => 'data-receipt-label="documentTitle"',
    ])

    <div class="space-y-4 p-5 text-sm text-slate-900">
        <div class="grid gap-3">
            <div class="border-l-4 border-blue-600 bg-slate-50 p-3">
                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-500" data-receipt-label="partyLabel">Supplier</p>
                <p class="mt-2 font-bold" data-preview-party>{{ $document->exists ? $document->partyName() : $partyFallback }}</p>
                <p class="mt-2 whitespace-pre-line text-xs font-medium leading-5 text-slate-600" data-preview-delivery>{{ $document->delivery_to ?: 'Receiving location' }}</p>
            </div>
            <div class="divide-y divide-slate-200 border border-slate-200 bg-slate-50">
                <div class="flex justify-between gap-3 px-3 py-2">
                    <span class="font-semibold text-slate-500" data-receipt-label="dateLabel">Received date</span>
                    <span class="text-right font-bold" data-preview-date>{{ optional($document->issue_date)->format('d M Y') ?? 'Received date' }}</span>
                </div>
                <div class="flex justify-between gap-3 px-3 py-2">
                    <span class="font-semibold text-slate-500" data-receipt-label="sourceLabel">Issued PO</span>
                    <span class="text-right font-bold" data-preview-related>{{ $document->relatedDocument?->document_number ?? 'Select source PO' }}</span>
                </div>
                <div class="flex justify-between gap-3 px-3 py-2">
                    <span class="font-semibold text-slate-500" data-receipt-label="referenceLabel">Delivery order reference</span>
                    <span class="text-right font-bold" data-preview-reference>{{ $document->external_reference ?: 'Delivery order reference' }}</span>
                </div>
            </div>
        </div>

        <div class="grid gap-2 sm:grid-cols-3">
            <div class="border border-slate-200 bg-white p-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Project / site</p>
                <p class="mt-1 font-bold" data-preview-site>{{ $document->project_name ?: 'Project / site' }}</p>
            </div>
            <div class="border border-slate-200 bg-white p-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Matching status</p>
                <p class="mt-1 font-bold" data-receipt-label="matchingStatus">Ready after goods are marked received</p>
            </div>
            <div class="border border-slate-200 bg-white p-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-slate-500">Evidence</p>
                <p class="mt-1 font-bold">Upload after save</p>
            </div>
        </div>

        <section class="border-t-4 border-blue-600 bg-slate-50 p-3">
            <p class="text-[11px] font-bold uppercase tracking-wide text-slate-700" data-receipt-label="remarksTitle">Receiving remarks</p>
            <p class="mt-2 whitespace-pre-line text-xs font-medium leading-5 text-slate-700" data-preview-scope>{{ $document->notes ?: 'Receiving, inspection, shortage, rejection, or damage remarks will appear here.' }}</p>
        </section>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-xs">
                <thead class="bg-[#0a345f] text-white">
                    <tr>
                        <th class="px-3 py-2 font-bold">No.</th>
                        <th class="px-3 py-2 font-bold" data-receipt-label="previewDescriptionHeader">Material / item received</th>
                        <th class="px-3 py-2 text-right font-bold">PO qty</th>
                        <th class="px-3 py-2 text-right font-bold" data-receipt-label="previewReceivedHeader">Received qty</th>
                        <th class="px-3 py-2 text-right font-bold" data-receipt-label="previewExceptionHeader">Short / rejected</th>
                        <th class="px-3 py-2 font-bold">Unit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200" data-preview-lines>
                    <tr>
                        <td colspan="6" class="px-3 py-6 text-center font-semibold text-slate-500" data-receipt-label="emptyLineCopy">Add received material lines to preview the goods receipt note.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <section class="rounded-lg border border-amber-200 bg-amber-50 p-3">
            <p class="text-[11px] font-bold uppercase tracking-wide text-amber-800" data-receipt-label="evidenceTitle">Evidence to attach after save</p>
            <p class="mt-2 text-xs font-semibold leading-5 text-slate-700" data-receipt-label="evidenceCopy">Delivery order, packing list, delivery photos, inspection notes, damage report, or handover proof required before invoice matching.</p>
        </section>
    </div>
</article>
