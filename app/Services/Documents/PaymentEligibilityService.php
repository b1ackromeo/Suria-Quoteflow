<?php

namespace App\Services\Documents;

use App\Models\Document;

class PaymentEligibilityService
{
    private const ELIGIBLE_STATUSES = [
        'customer_invoice' => ['issued', 'part_paid'],
        'supplier_invoice' => ['matched', 'part_paid'],
    ];

    public function canRecordPayment(Document $document): bool
    {
        return in_array($document->status, self::ELIGIBLE_STATUSES[$document->type] ?? [], true);
    }

    public function blockedReason(Document $document): ?string
    {
        if ($this->canRecordPayment($document)) {
            return null;
        }

        if (! $document->isInvoice()) {
            return 'Payment can only be recorded against customer or supplier invoices.';
        }

        if (in_array($document->status, ['paid', 'closed', 'cancelled'], true)) {
            return 'Payment is not available after an invoice is paid, closed, or cancelled.';
        }

        return match ($document->type) {
            'customer_invoice' => 'Payment is available after this customer invoice is issued.',
            'supplier_invoice' => 'Match this supplier invoice before recording payment.',
            default => 'Payment is not available for this document status.',
        };
    }
}
