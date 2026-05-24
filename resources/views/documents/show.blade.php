@extends('layouts.app', [
    'title' => $document->document_number,
    'contentMode' => 'fullscreen',
])

@php
    $companyProfile = \App\Models\CompanyProfile::active();
    $money = fn ($value, ?string $currency = null) => $companyProfile->formatMoney($value, $currency ?: $document->currency);
    $date = fn ($value) => $value ? $companyProfile->formatDate($value) : 'Not set';

    $canWrite = auth()->user()->hasRole('admin', 'manager')
        || ($document->direction === 'outgoing' && auth()->user()->hasRole('sales', 'accounts'))
        || ($document->direction === 'incoming' && auth()->user()->hasRole('procurement', 'accounts'));
    $paymentEligible = $paymentEligible ?? false;
    $paymentBlockedReason = $paymentBlockedReason ?? null;
    $isPaymentDocument = in_array($document->type, ['customer_invoice', 'supplier_invoice'], true);
    $canManagePayments = auth()->user()->hasRole('admin', 'manager', 'accounts');
    $canRecordPayment = $canManagePayments && $paymentEligible;
    $showPaymentBlockedReason = $canManagePayments && $isPaymentDocument && ! $paymentEligible && filled($paymentBlockedReason);
    $primaryDateLabel = match ($document->type) {
        'customer_po' => 'Date received',
        'supplier_po' => 'PO date',
        'customer_invoice', 'supplier_invoice' => 'Invoice date',
        default => 'Issue date',
    };
    $secondaryDateLabel = $document->isQuotation() ? 'Valid until' : ($document->type === 'supplier_po' ? 'Delivery date' : ($document->type === 'customer_po' ? 'Completion target' : 'Due date'));
    $canRecordDeliveryComplete = in_array($document->status, ['issued', 'approved'], true) && $document->type === 'customer_po';
    $canCloseDocument = match (true) {
        $document->isInvoice() => $document->status === 'paid',
        $document->type === 'customer_po' => $document->status === 'fulfilled',
        default => false,
    };
    $supplierInvoiceMatching = $supplierInvoiceMatching ?? null;
    $supplierInvoiceCanMatch = (bool) ($supplierInvoiceMatching['passes'] ?? false);
    $supplierInvoiceMatchingBlockers = $supplierInvoiceMatching['blocking_messages'] ?? [];
    $baseCanSubmitForApproval = in_array($document->status, ['draft', 'rejected'], true);
    $supplierQuoteAttachment = $document->attachments->firstWhere('category', 'supplier_quote');
    $supplierQuoteExtraction = $supplierQuoteAttachment?->extraction;
    $hasSupplierQuotePreview = $document->type === 'purchase_request'
        && $document->attachments
            ->where('category', 'supplier_quote')
            ->contains(fn ($attachment) => $attachment->isPreviewable());
    $hasSupplierQuoteException = $document->type === 'purchase_request'
        && $document->source_type === 'quote_exception'
        && filled($document->source_note);
    $hasVerifiedSupplierQuoteEvidence = $document->type === 'purchase_request'
        && $document->attachments
            ->where('category', 'supplier_quote')
            ->contains(fn ($attachment) => $attachment->extraction?->status === 'verified');
    $purchaseRequestApprovalReady = $document->type !== 'purchase_request'
        || $hasSupplierQuoteException
        || $hasVerifiedSupplierQuoteEvidence;
    $canSubmitForApproval = $baseCanSubmitForApproval && $purchaseRequestApprovalReady;
    $canDecideDocument = auth()->user()->canApprove() && $document->status === 'pending_approval';
    $canApproveDocument = $canDecideDocument && $purchaseRequestApprovalReady;
    $canRejectDocument = $canDecideDocument;
    $canMarkIssued = $document->status === 'approved'
        && in_array($document->type, ['customer_quotation', 'customer_po', 'customer_invoice', 'supplier_po'], true);
    $canMarkReceived = in_array($document->status, ['issued', 'approved'], true) && $document->type === 'goods_receipt';
    $canTrySupplierInvoiceMatch = in_array($document->status, ['received', 'issued', 'approved'], true) && $document->type === 'supplier_invoice';
    $canMarkMatched = $canTrySupplierInvoiceMatch && $supplierInvoiceCanMatch;
    $canOverrideSupplierInvoiceMatch = $canTrySupplierInvoiceMatch && ! $supplierInvoiceCanMatch && auth()->user()->hasRole('admin', 'manager');
    $canCancelDocument = $canWrite && auth()->user()->hasRole('admin', 'manager') && $document->canBeCancelled();
    $hasWorkflowActions = ($canWrite && (
        $canSubmitForApproval
        || $canApproveDocument
        || $canRejectDocument
        || $canMarkIssued
        || $canRecordDeliveryComplete
        || $canMarkReceived
        || $canMarkMatched
        || $canOverrideSupplierInvoiceMatch
        || $canCloseDocument
        || $canCancelDocument
    )) || $canRecordPayment;
    $issueActionLabel = match ($document->type) {
        'customer_po' => 'Accept customer PO',
        'supplier_po' => 'Issue purchase order',
        'customer_invoice' => 'Issue invoice',
        'customer_quotation' => 'Issue quotation',
        default => 'Mark issued',
    };
    $canGenerateCompanyPdf = match ($document->type) {
        'supplier_po' => $document->shouldPreviewGeneratedPdfOutput(),
        'purchase_request', 'goods_receipt' => $document->shouldPreviewGeneratedPdfOutput(),
        'customer_quotation', 'customer_invoice' => true,
        default => false,
    };
    $usesReceivedFilePreview = in_array($document->type, ['customer_po', 'supplier_quotation', 'purchase_request', 'goods_receipt'], true);
    $nextDocumentLinks = $canWrite
        ? collect($documentConversionOptions ?? [])
            ->map(fn ($option) => [
                'label' => $option['label'],
                'route' => route('documents.convert', [$document, $option['target_module']]),
            ])
            ->all()
        : [];
@endphp

@section('header_actions')
<div class="flex flex-wrap gap-2">
    <a class="btn btn-secondary" href="{{ route('documents.index', $meta['slug']) }}">Back</a>
    @if($canGenerateCompanyPdf)
        <a class="btn btn-secondary" href="{{ route('documents.pdf', $document) }}" target="_blank" rel="noopener">Preview PDF</a>
        <a class="btn btn-secondary" href="{{ route('documents.pdf.download', $document) }}">Download PDF</a>
    @endif
    @if($canWrite && ! in_array($document->status, ['paid', 'closed', 'cancelled'], true))
        <a class="btn btn-secondary" href="{{ route('documents.edit', $document) }}">Edit</a>
    @endif
    @if($canRecordPayment)
        <a class="btn btn-primary" href="{{ route('payments.create', $document) }}">Record payment</a>
    @endif
</div>
@endsection

@section('content')
@php
    $previewMode = match (true) {
        $hasSupplierQuotePreview => 'external',
        $canGenerateCompanyPdf => 'generated',
        $document->type === 'supplier_invoice' => 'supplier_invoice',
        $usesReceivedFilePreview => 'external',
        default => 'record',
    };
    $processLabel = $document->direction === 'outgoing'
        ? 'Customer sales'
        : 'Supplier purchasing';
    $paidAmount = (float) $document->payments->sum('amount');
    $balanceDue = max(0, (float) $document->total - $paidAmount);
    $hasActiveSupplierQuoteCapture = $document->type === 'purchase_request'
        && $supplierQuoteAttachment
        && $supplierQuoteExtraction
        && $supplierQuoteExtraction->status !== 'verified';
    $hasVerifiedSupplierQuoteWorkspace = $document->type === 'purchase_request'
        && $supplierQuoteAttachment
        && $supplierQuoteExtraction?->status === 'verified';
    $canUseInlineSubmitAction = $hasVerifiedSupplierQuoteWorkspace && $canSubmitForApproval;
    $isQuotationApprovalReview = $document->type === 'customer_quotation'
        && $document->status === 'pending_approval'
        && $canDecideDocument;
    $showGenericCommandSections = ! $hasActiveSupplierQuoteCapture
        && ! $hasVerifiedSupplierQuoteWorkspace
        && ! $isQuotationApprovalReview;
    $hasCommercialText = filled($document->notes) || filled($document->terms);
    $supplierQuoteReviewFields = $supplierQuoteExtraction?->verified_fields ?? $supplierQuoteExtraction?->extracted_fields ?? [];
    $supplierQuoteReviewTotal = $supplierQuoteReviewFields['total'] ?? null;
    $supplierQuoteReviewAmount = null;
    if (filled($supplierQuoteReviewTotal)) {
        $supplierQuoteReviewNumber = preg_replace('/[^\d.\-]/', '', (string) $supplierQuoteReviewTotal);
        if ($supplierQuoteReviewNumber !== '' && is_numeric($supplierQuoteReviewNumber)) {
            $supplierQuoteReviewAmount = $money((float) $supplierQuoteReviewNumber);
        }
    }
    $nextAction = match (true) {
        $document->type === 'purchase_request' && in_array($document->status, ['draft', 'rejected'], true) && $supplierQuoteExtraction?->status === 'processed' => 'Confirm the supplier quotation details before submitting this purchase request for approval.',
        $document->type === 'purchase_request' && in_array($document->status, ['draft', 'rejected'], true) && $supplierQuoteExtraction?->status === 'failed' => 'Enter the supplier quotation details from the uploaded file, then confirm the quotation details.',
        $document->type === 'purchase_request' && in_array($document->status, ['draft', 'rejected'], true) && $hasVerifiedSupplierQuoteEvidence => 'Submit this purchase request for approval.',
        $document->type === 'purchase_request' && in_array($document->status, ['draft', 'rejected'], true) => 'Upload and verify supplier quotation evidence, or record a quotation exception reason, before requesting approval.',
        $document->type === 'purchase_request' && $document->status === 'pending_approval' && $hasSupplierQuoteException => 'Manager must confirm this quotation exception before approving the purchase request.',
        $document->type === 'purchase_request' && $document->status === 'pending_approval' && ! $hasVerifiedSupplierQuoteEvidence => 'Verify the supplier quotation evidence before approving this purchase request.',
        $isQuotationApprovalReview => 'Review the quotation PDF, then approve it for issue or reject it with a reason.',
        in_array($document->status, ['draft', 'rejected'], true) => 'Submit this '.$meta['singular'].' for approval when details and attachments are ready.',
        $document->status === 'pending_approval' => 'Manager approval is required before this document can continue.',
        $document->type === 'customer_po' && $document->status === 'approved' => 'Accept the customer PO when the team has confirmed scope, price, delivery, and required attachments.',
        $document->type === 'purchase_request' && $document->status === 'approved' => 'Use this approved purchase request to request supplier quotations or prepare a purchase order.',
        $document->type === 'supplier_quotation' && $document->status === 'approved' => 'Use the accepted supplier quotation to prepare the purchase order.',
        $document->type === 'supplier_po' && $document->status === 'approved' => 'Issue this purchase order to the supplier.',
        $document->type === 'goods_receipt' && $document->status === 'approved' => 'Confirm delivered goods or accepted services after checking the evidence against the purchase order.',
        $document->type === 'supplier_invoice' && $document->status === 'approved' => 'Match this supplier invoice against the purchase order, receipt, and verified invoice details before payment.',
        $document->type === 'customer_quotation' && $document->status === 'approved' => 'Issue the approved quotation to the customer.',
        $document->type === 'customer_invoice' && $document->status === 'approved' => 'Issue the approved invoice to the customer.',
        $document->type === 'customer_po' && in_array($document->status, ['issued', 'approved'], true) => 'Record delivery or service completion when work is accepted.',
        $document->type === 'supplier_po' && $document->status === 'issued' => 'Record goods or service receipt when the supplier has delivered or completed the work.',
        $document->type === 'goods_receipt' && $document->status === 'received' => 'Use this receipt to check and match the supplier invoice.',
        $document->type === 'supplier_invoice' && $document->status === 'matched' => 'Record supplier payment after the matched invoice is approved for payment.',
        $document->isInvoice() && in_array($document->status, ['issued', 'part_paid'], true) => 'Record payment against this invoice.',
        $document->isInvoice() && $document->status === 'paid' => 'Close the invoice after payment is confirmed.',
        $document->isInvoice() && $document->status === 'closed' && $balanceDue > 0 => 'Closed with a remaining balance. Review payment records before relying on this invoice status.',
        $document->status === 'cancelled' => 'Document is cancelled. No further document changes are available.',
        $document->status === 'closed' => 'Document is closed.',
        default => 'Review document status, attachments, approvals, and payment balance.',
    };
    $detailRows = [
        ['label' => $primaryDateLabel, 'value' => $date($document->issue_date)],
        ['label' => $secondaryDateLabel, 'value' => $date($document->due_date)],
        ['label' => 'Reference', 'value' => $document->external_reference ?: 'Not set'],
        ['label' => 'Payment terms', 'value' => $document->paymentTermsDisplay()],
        ['label' => 'Project / site', 'value' => $document->project_name ?: 'Not set'],
    ];
    if ($document->isInvoice()) {
        $detailRows[] = ['label' => 'Progress invoice', 'value' => $document->progress_invoice_number && $document->progress_invoice_total ? 'No. '.$document->progress_invoice_number.' of '.$document->progress_invoice_total : 'Not set'];
        $detailRows[] = ['label' => 'Billing stage', 'value' => $document->billing_stage_name ?: 'Not set'];
        $detailRows[] = ['label' => 'Balance', 'value' => $money($balanceDue)];
    }
    $readinessItems = [];
    if ($document->status === 'cancelled') {
        $readinessItems[] = ['state' => 'blocked', 'label' => 'Document cancelled', 'message' => 'No further document actions are available.'];
    } elseif ($document->status === 'pending_approval') {
        $readinessItems[] = ['state' => 'waiting', 'label' => 'Approval waiting', 'message' => 'Manager approval is required before this document can continue.'];
    }
    if ($document->status !== 'cancelled' && $document->type === 'purchase_request') {
        if ($supplierQuoteAttachment) {
            if ($supplierQuoteExtraction?->status === 'verified') {
                $readinessItems[] = ['state' => 'ready', 'label' => 'Supplier quotation verified', 'message' => 'The uploaded supplier quotation has been checked and can support approval.'];
            } elseif ($supplierQuoteExtraction?->status === 'processed') {
                $readinessItems[] = ['state' => 'waiting', 'label' => 'Supplier quotation details ready for review', 'message' => 'Confirm the extracted supplier quotation details before approval.'];
            } elseif ($supplierQuoteExtraction?->status === 'failed') {
                $readinessItems[] = ['state' => 'blocked', 'label' => 'Manual quotation verification needed', 'message' => 'OCR could not read the quotation. Check the uploaded file and verify the quotation details manually.'];
            } else {
                $readinessItems[] = ['state' => 'waiting', 'label' => 'Supplier quotation uploaded', 'message' => 'Run OCR or verify the quotation evidence before approval.'];
            }
        } elseif ($hasSupplierQuoteException) {
            $readinessItems[] = ['state' => 'waiting', 'label' => 'Quotation exception recorded', 'message' => 'Approver must confirm the exception reason in the approval comment.'];
        } else {
            $readinessItems[] = ['state' => 'blocked', 'label' => 'Supplier quotation missing', 'message' => 'Upload supplier quotation evidence, or record a quotation exception reason, before approval.'];
        }
    }
    if ($document->status !== 'cancelled' && $document->type === 'supplier_invoice' && $supplierInvoiceVerification) {
        if ($supplierInvoiceVerification['verified'] ?? false) {
            $verifiedBy = $supplierInvoiceVerification['verifier']?->name ?? 'Verified';
            $readinessItems[] = [
                'state' => 'ready',
                'label' => 'Invoice details verified',
                'message' => trim(($supplierInvoiceVerification['method_label'] ?? 'Verified').' by '.$verifiedBy.'.'),
            ];
        } else {
            foreach (($supplierInvoiceVerification['issues'] ?? []) as $issue) {
                $readinessItems[] = ['state' => 'blocked', 'label' => 'Verification blocked', 'message' => $issue];
            }
        }
    }
    if ($document->status !== 'cancelled' && $document->type === 'supplier_invoice' && $supplierInvoiceMatching) {
        $readinessItems[] = $supplierInvoiceCanMatch
            ? ['state' => 'ready', 'label' => 'Matching checklist ready', 'message' => 'Invoice can be matched against its source.']
            : ['state' => 'blocked', 'label' => 'Matching blocked', 'message' => implode(' ', $supplierInvoiceMatchingBlockers)];
    }
    if ($document->status !== 'cancelled' && $showPaymentBlockedReason) {
        $readinessItems[] = ['state' => 'blocked', 'label' => 'Payment not available yet', 'message' => $paymentBlockedReason];
    } elseif ($document->status !== 'cancelled' && $canRecordPayment) {
        $readinessItems[] = ['state' => 'money', 'label' => 'Payment ready', 'message' => 'Payment can be recorded for this invoice.'];
    }
    if ($readinessItems === []) {
        $readinessItems[] = ['state' => 'ready', 'label' => 'No blocker visible', 'message' => 'Review the facts, evidence, and history before acting.'];
    }
    $approvalReadinessIsDuplicated = $document->status === 'pending_approval'
        && count($readinessItems) === 1
        && ($readinessItems[0]['label'] ?? null) === 'Approval waiting'
        && $canDecideDocument;
    $showReadinessPanel = $showGenericCommandSections && ! $approvalReadinessIsDuplicated;
    $actionPanelTitle = $canDecideDocument ? 'Approval decision' : 'Available actions';
    $showActionPanel = $hasWorkflowActions && ! $canUseInlineSubmitAction;
@endphp

<div class="document-open-workspace @if($document->type === 'purchase_request' && $supplierQuoteAttachment) document-open-workspace-source @endif">
    <section class="document-open-side-panel document-workspace-panel" aria-label="Review and actions">
        <div class="document-open-heading">
            <div class="min-w-0">
                <p class="document-pane-kicker">{{ $processLabel }}</p>
                <h1>{{ $document->document_number }}</h1>
                <p>{{ $meta['singular'] }} for {{ $document->partyName() }}</p>
            </div>
            <div class="document-open-summary">
                <span
                    class="status-chip status-{{ $document->status }}"
                    aria-label="{{ $document->statusAriaLabel() }}"
                    data-status-group="{{ $document->statusSemanticGroupDisplay() }}"
                >{{ $document->statusDisplay() }}</span>
                @if($hasActiveSupplierQuoteCapture)
                    <strong>{{ $supplierQuoteReviewAmount ? 'Detected quotation '.$supplierQuoteReviewAmount : 'Quotation total pending review' }}</strong>
                @else
                    <strong>{{ $money($document->total) }}</strong>
                @endif
            </div>
        </div>

        @if($hasActiveSupplierQuoteCapture)
            @include('documents.partials.external-document-preview', [
                'document' => $document,
                'meta' => $meta,
                'sourcePanelMode' => 'capture',
            ])
        @endif

        @if(! $hasActiveSupplierQuoteCapture)
            <section class="document-side-card document-next-step-card">
                <div class="document-side-card-heading">
                    <p class="document-pane-kicker">Next action</p>
                    @unless($isQuotationApprovalReview)
                        <span>{{ $document->statusDisplay() }}</span>
                    @endunless
                </div>
                <h2>{{ $nextAction }}</h2>
                @if($canUseInlineSubmitAction)
                    <form method="post" action="{{ route('documents.submit', $document) }}" class="mt-4">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <button type="submit" class="btn btn-primary w-full">Submit for approval</button>
                    </form>
                @endif
                @if($canUseInlineSubmitAction && $canCancelDocument)
                    @include('documents.partials.cancel-document-form', [
                        'document' => $document,
                        'cancelFormClass' => 'mt-4 space-y-2',
                    ])
                @endif
                @if($nextDocumentLinks !== [])
                    <div class="mt-4 grid gap-2">
                        @foreach($nextDocumentLinks as $link)
                            <form method="post" action="{{ $link['route'] }}">
                                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                                <button type="submit" class="btn btn-primary w-full">{{ $link['label'] }}</button>
                            </form>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        @if($showReadinessPanel)
            <section class="document-side-card document-readiness-card">
                <h2 class="panel-title">Required before continuing</h2>
                <div class="document-readiness-list">
                    @foreach($readinessItems as $item)
                        <article class="document-readiness-item readiness-state-{{ $item['state'] }}">
                            <strong>{{ $item['label'] }}</strong>
                            <p>{{ $item['message'] }}</p>
                        </article>
                    @endforeach
                </div>

                @if($document->type === 'supplier_invoice' && $supplierInvoiceMatching)
                    <div class="document-checklist-summary">
                        <h3>Supplier invoice matching checklist</h3>
                        @foreach($supplierInvoiceMatching['checks'] as $check)
                            <div class="document-checklist-row {{ $check['passed'] ? 'is-passed' : 'is-blocked' }}">
                                <span>{{ $check['passed'] ? 'Passed' : 'Blocked' }}</span>
                                <strong>{{ $check['label'] }}</strong>
                                <p>{{ $check['message'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        @if($showActionPanel)
            <section class="document-side-card document-action-stack @if($canDecideDocument) document-decision-card @endif">
                <h2 class="panel-title">{{ $actionPanelTitle }}</h2>
                @if($canRecordPayment)
                    <a class="btn btn-primary w-full" href="{{ route('payments.create', $document) }}">Record payment</a>
                @endif
                @if($canSubmitForApproval && ! $canUseInlineSubmitAction)
                    <form method="post" action="{{ route('documents.submit', $document) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button type="submit" class="btn btn-primary w-full">Submit for approval</button></form>
                @endif
                @if($canApproveDocument)
                    <form method="post" action="{{ route('documents.approve', $document) }}" class="document-decision-form">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <label class="document-decision-field">
                            <span>{{ $hasSupplierQuoteException ? 'Approval comment' : 'Approval note' }}</span>
                            <textarea class="form-input" name="comment" rows="1" placeholder="{{ $hasSupplierQuoteException ? 'Confirm the quotation exception' : 'Optional note' }}" @if($hasSupplierQuoteException) required @endif></textarea>
                        </label>
                        <button type="submit" class="btn btn-primary document-decision-button">Approve</button>
                    </form>
                @elseif($canDecideDocument && $document->type === 'purchase_request' && ! $purchaseRequestApprovalReady)
                    <div class="document-readiness-item readiness-state-blocked">
                        <strong>Approval not available yet</strong>
                        <p>Confirm the supplier quotation details before approving this purchase request.</p>
                    </div>
                @endif
                @if($canRejectDocument)
                    <form method="post" action="{{ route('documents.reject', $document) }}" class="document-decision-form">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <label class="document-decision-field">
                            <span>Rejection reason</span>
                            <textarea class="form-input" name="comment" rows="1" placeholder="Why this should not proceed"></textarea>
                        </label>
                        <button type="submit" class="btn btn-danger document-decision-button">Reject</button>
                    </form>
                @endif
                @if($canMarkIssued)
                    <form method="post" action="{{ route('documents.transition', [$document, 'issue']) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button type="submit" class="btn btn-secondary w-full">{{ $issueActionLabel }}</button></form>
                @endif
                @if($canRecordDeliveryComplete)
                    <form method="post" action="{{ route('documents.transition', [$document, 'fulfill']) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button type="submit" class="btn btn-secondary w-full">Delivery / service complete</button></form>
                @endif
                @if($canMarkReceived)
                    <form method="post" action="{{ route('documents.transition', [$document, 'receive']) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button type="submit" class="btn btn-secondary w-full">Confirm receipt</button></form>
                @endif
                @if($canMarkMatched)
                    <form method="post" action="{{ route('documents.transition', [$document, 'match']) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button type="submit" class="btn btn-secondary w-full">Match supplier invoice</button></form>
                @endif
                @if($canOverrideSupplierInvoiceMatch)
                    <form method="post" action="{{ route('documents.transition', [$document, 'match']) }}" class="space-y-2">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <label class="form-label">Matching override notes
                            <textarea class="form-input" name="matching_override_reason" placeholder="Explain why this invoice can be matched despite the checklist blockers." required></textarea>
                            <span class="mt-1 block text-xs font-semibold text-slate-500">Use only when the amount mismatch or checklist blocker is accepted by an approver. The override is recorded in the audit trail.</span>
                        </label>
                        <button type="submit" class="btn btn-secondary w-full">Match with audited override</button>
                    </form>
                @endif
                @if($canCloseDocument)
                    <form method="post" action="{{ route('documents.transition', [$document, 'close']) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><button type="submit" class="btn btn-secondary w-full">Close</button></form>
                @endif
                @if($canCancelDocument)
                    @include('documents.partials.cancel-document-form', ['document' => $document])
                @endif
            </section>
        @endif

        @if(! $hasActiveSupplierQuoteCapture && ! $isQuotationApprovalReview)
            @if($hasVerifiedSupplierQuoteWorkspace)
                <section class="document-side-card document-pr-items-card">
                    <div class="document-side-section-heading">
                        <h2 class="panel-title">Requested items</h2>
                        <span>{{ $document->items->count() }} line{{ $document->items->count() === 1 ? '' : 's' }}</span>
                    </div>
                    <div class="document-compact-table">
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
                            @foreach($document->items as $item)
                                <tr>
                                    <td data-label="Description"><strong>{{ $item->description }}</strong></td>
                                    <td data-label="Qty" class="text-right">{{ \App\Models\Document::formatQuantity($item->quantity) }}</td>
                                    <td data-label="Unit">{{ $item->unit }}</td>
                                    <td data-label="Amount" class="text-right">{{ $money((float) $item->quantity * (float) $item->unit_price) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot>
                            <tr><th colspan="3">Total</th><td class="text-right">{{ $money($document->total) }}</td></tr>
                            </tfoot>
                        </table>
                    </div>
                </section>

                @if($hasCommercialText)
                    <section class="document-side-card document-side-notes">
                        <h2 class="panel-title">Supplier terms</h2>
                        @if($document->terms)
                            <div>
                                <strong>Terms and conditions</strong>
                                <p>{{ $document->terms }}</p>
                            </div>
                        @endif
                        @if($document->notes)
                            <div>
                                <strong>Notes</strong>
                                <p>{{ $document->notes }}</p>
                            </div>
                        @endif
                    </section>
                @endif
            @endif

            <section class="document-side-card">
                <h2 class="panel-title">Document details</h2>
                <dl class="document-detail-list">
                    @foreach($detailRows as $row)
                        <div>
                            <dt>{{ $row['label'] }}</dt>
                            <dd>{{ $row['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
                @if($document->delivery_to || $document->source_note)
                    <div class="document-side-notes">
                        @if($document->delivery_to)
                            <div>
                                <strong>Delivery / service location</strong>
                                <p>{{ $document->delivery_to }}</p>
                            </div>
                        @endif
                        @if($document->source_note)
                            <div>
                                <strong>Reason / note</strong>
                                <p>{{ $document->source_note }}</p>
                            </div>
                        @endif
                    </div>
                @endif
            </section>

            @if($showGenericCommandSections)
            <section class="document-side-card document-chain-card">
                <h2 class="panel-title">Document progress</h2>
                <dl class="document-detail-list">
                    <div>
                        <dt>How created</dt>
                        <dd>{{ $document->sourceTypeDisplay() }}</dd>
                    </div>
                    <div>
                        <dt>Related document</dt>
                        <dd>{{ $document->relatedDocument?->document_number ?? 'None' }}</dd>
                    </div>
                </dl>
                @include('documents.partials.workflow-timeline', ['meta' => $meta, 'document' => $document, 'documentChain' => $documentChain ?? null])
            </section>
            @endif
        @endif

        @if($showGenericCommandSections)
            <section class="document-side-card">
                <div class="document-side-section-heading">
                    <h2 class="panel-title">Evidence and attachments</h2>
                    <span>{{ $document->attachments->count() }} file{{ $document->attachments->count() === 1 ? '' : 's' }}</span>
                </div>
                <div class="document-attachment-list">
                    @forelse($document->attachments as $attachment)
                        <a href="{{ $attachment->isPreviewable() ? route('attachments.preview', $attachment) : route('attachments.download', $attachment) }}" @if($attachment->isPreviewable()) target="_blank" rel="noopener" @endif>
                            <span>{{ $attachment->original_name }}</span>
                            <strong>
                                {{ str_replace('_', ' ', ucfirst($attachment->category ?? 'supporting_document')) }}{{ $attachment->isPreviewable() ? ' · Preview available' : '' }}
                                @if($attachment->extraction?->status)
                                    · Details {{ str_replace('_', ' ', $attachment->extraction->status) }}
                                @endif
                            </strong>
                        </a>
                    @empty
                        <p>No attachments.</p>
                    @endforelse
                </div>
                @if($canWrite)
                    <form method="post" action="{{ route('documents.attachments.store', $document) }}" enctype="multipart/form-data" class="document-upload-form">
                        <input type="hidden" name="_token" value="{{ csrf_token() }}">
                        <label class="form-label">Attachment category
                            <select class="form-input" name="category">
                                <option value="supporting_document">Supporting document</option>
                                <option value="customer_po">Customer PO</option>
                                <option value="supplier_quote" @selected(in_array($document->type, ['purchase_request', 'supplier_quotation'], true))>Supplier quotation</option>
                                <option value="delivery_evidence">Delivery / service evidence</option>
                                <option value="invoice_copy" @selected($document->type === 'supplier_invoice')>Invoice copy</option>
                                <option value="delivery_order">Delivery order</option>
                                <option value="service_report">Service report</option>
                                <option value="uat_document">UAT / acceptance document</option>
                                <option value="installation_report">Installation report</option>
                                <option value="completion_photo">Completion photo</option>
                                <option value="email_approval">Email approval</option>
                                <option value="payment_proof">Payment proof</option>
                            </select>
                        </label>
                        <input class="form-input file:mr-3 file:rounded-md file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-bold file:text-slate-700 hover:file:bg-slate-200" type="file" name="attachment" required>
                        <button type="submit" class="btn btn-secondary w-full">Upload</button>
                    </form>
                @endif
            </section>
        @endif

        @if($document->billingStages->isNotEmpty() && ! $isQuotationApprovalReview)
            <details class="document-side-card document-side-disclosure">
                <summary>
                    <span>{{ $document->isInvoice() ? 'Progress billing' : 'Payment schedule' }}</span>
                    <strong>{{ $document->billingStages->count() }} stage{{ $document->billingStages->count() === 1 ? '' : 's' }}</strong>
                </summary>
                <div class="document-compact-table">
                    <table>
                        <thead>
                        <tr>
                            <th>Stage</th>
                            <th class="text-right">Amount</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($document->billingStages as $stage)
                            <tr class="{{ $stage->is_current ? 'is-current' : '' }}">
                                <td data-label="Stage">
                                    <strong>{{ $stage->stage_name }}</strong>
                                    <span>{{ $document->isInvoice() ? ($stage->is_current ? 'Current invoice stage' : 'Not billed on this invoice') : ($stage->condition_label ?: $stage->payment_term ?: '-') }}</span>
                                </td>
                                <td data-label="Amount" class="text-right">{{ $money($document->isInvoice() ? $stage->current_invoice : $stage->amount) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif

        @unless($hasActiveSupplierQuoteCapture || $hasVerifiedSupplierQuoteWorkspace || $isQuotationApprovalReview)
        <details class="document-side-card document-side-disclosure">
            <summary>
                <span>Line items</span>
                <strong>{{ $document->items->count() }} line{{ $document->items->count() === 1 ? '' : 's' }}</strong>
            </summary>
            <div class="document-compact-table">
                <table>
                    <thead>
                    <tr><th>Description</th><th class="text-right">Total</th></tr>
                    </thead>
                    <tbody>
                    @foreach($document->items as $item)
                        <tr>
                            <td data-label="Description">
                                <strong>{{ $item->description }}</strong>
                                <span>{{ \App\Models\Document::formatQuantity($item->quantity) }} {{ $item->unit }} x {{ $money($item->unit_price) }}</span>
                            </td>
                            <td data-label="Total" class="text-right">{{ $money((float) $item->quantity * (float) $item->unit_price) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                    <tr><th>Total</th><td class="text-right">{{ $money($document->total) }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </details>
        @endunless

        @if($showGenericCommandSections)
            <details class="document-side-card document-side-disclosure">
                <summary>
                    <span>Commercial notes</span>
                    <strong>Notes / terms</strong>
                </summary>
                <div class="document-side-notes">
                    <div>
                        <strong>Notes</strong>
                        <p>{{ $document->notes ?: 'No notes.' }}</p>
                    </div>
                    <div>
                        <strong>Terms</strong>
                        <p>{{ $document->terms ?: 'No terms.' }}</p>
                    </div>
                </div>
            </details>
        @endif

        @if($showGenericCommandSections)
            <details class="document-side-card document-side-disclosure">
                <summary>
                    <span>Payments</span>
                    <strong>{{ $money($balanceDue) }} balance</strong>
                </summary>
                <div class="document-compact-table">
                    <table>
                        <thead><tr><th>Reference</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>
                        @forelse($document->payments as $payment)
                            <tr>
                                <td data-label="Reference">
                                    <strong>{{ $payment->reference }}</strong>
                                    <span>{{ $payment->payment_date ? $companyProfile->formatDate($payment->payment_date) : 'Not set' }} · {{ $payment->typeDisplay() }}</span>
                                </td>
                                <td data-label="Amount" class="text-right">{{ $money($payment->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2">No payments recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </details>
        @endif

        @if($showGenericCommandSections || ($hasVerifiedSupplierQuoteWorkspace && $document->approvals->isNotEmpty()))
            <details class="document-side-card document-side-disclosure">
                <summary>
                    <span>Approval history</span>
                    <strong>{{ $document->approvals->count() }} event{{ $document->approvals->count() === 1 ? '' : 's' }}</strong>
                </summary>
                <div class="document-approval-list">
                    @forelse($document->approvals as $approval)
                        <article>
                            <strong>{{ ucfirst($approval->status) }}</strong>
                            <span>Requested by {{ $approval->requester?->name ?? 'Unknown' }}</span>
                            @if($approval->decider)
                                <span>Decided by {{ $approval->decider->name }}</span>
                            @endif
                            @if($approval->comment)
                                <p>{{ $approval->comment }}</p>
                            @endif
                        </article>
                    @empty
                        <p>No approval events.</p>
                    @endforelse
                </div>
            </details>
        @endif
    </section>

    <section class="document-open-preview-pane document-source-panel" aria-label="Uploaded document preview">
        <div class="document-open-preview-body">
            @if($previewMode === 'generated')
                @include('documents.partials.generated-pdf-output-preview', ['document' => $document, 'meta' => $meta, 'isActive' => true])
            @elseif($previewMode === 'supplier_invoice')
                @include('documents.partials.supplier-invoice-file-preview', ['document' => $document, 'meta' => $meta])
            @elseif($previewMode === 'external')
                @include('documents.partials.external-document-preview', [
                    'document' => $document,
                    'meta' => $meta,
                    'sourcePanelMode' => $document->type === 'purchase_request' && $supplierQuoteAttachment ? 'preview' : 'full',
                ])
            @else
                <article class="document-preview-empty">
                    <p class="document-pane-kicker">Document preview</p>
                    <h2>{{ $meta['singular'] }} record</h2>
                    <p>The document preview will appear here after it is ready for issue, receipt, or matching.</p>
                </article>
            @endif
        </div>
    </section>
</div>
@endsection
