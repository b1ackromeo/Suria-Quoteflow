<?php

namespace App\Services\Invoices;

use App\Models\AttachmentExtraction;
use App\Models\CompanyProfile;
use App\Models\Document;

class SupplierInvoiceMatchingService
{
    private const ROUNDING_TOLERANCE = 0.01;
    private const SOFT_TOLERANCE_AMOUNT = 1.00;
    private const SOFT_TOLERANCE_RATE = 0.005;

    public function __construct(private SupplierInvoiceVerificationService $verification)
    {
    }

    public function canMatch(Document $document): bool
    {
        return $this->checklist($document)['passes'];
    }

    public function blockingMessages(Document $document): array
    {
        return $this->checklist($document)['blocking_messages'];
    }

    public function checklist(Document $document): array
    {
        if ($document->type !== 'supplier_invoice') {
            return [
                'passes' => true,
                'checks' => [],
                'blocking_messages' => [],
            ];
        }

        $document->loadMissing(['supplier', 'relatedDocument', 'attachments.extraction.verifier']);

        $checks = [];
        $verificationIssues = $this->verification->blockingIssues($document);
        $verifiedExtraction = $this->verification->verifiedExtraction($document);
        $relatedDocument = $document->relatedDocument;
        $isDirectException = $document->source_type === 'direct_supplier_invoice';
        $linkedDocumentLabel = $this->linkedDocumentLabel($relatedDocument);

        $checks[] = $this->check(
            'verification',
            'Invoice details verified',
            $verificationIssues === [],
            $verificationIssues === []
                ? $this->verifiedDetailsMessage($verifiedExtraction)
                : implode(' ', $verificationIssues)
        );

        $checks[] = $this->check(
            'source',
            'Linked purchase document',
            $isDirectException || (bool) $relatedDocument,
            $isDirectException
                ? 'Direct supplier invoice exception selected.'
                : ($relatedDocument ? 'Linked to '.$relatedDocument->document_number.'.' : 'Link this invoice to a goods receipt or purchase order.')
        );

        $sourceTypeAllowed = $isDirectException || ($relatedDocument && in_array($relatedDocument->type, ['goods_receipt', 'supplier_po'], true));
        $checks[] = $this->check(
            'source_type',
            'Linked document type',
            $sourceTypeAllowed,
            $sourceTypeAllowed ? ucfirst($linkedDocumentLabel).' can be used for supplier invoice matching.' : 'Use a goods receipt or purchase order for matching.'
        );

        $supplierMatches = $isDirectException || ($relatedDocument && (int) $relatedDocument->supplier_id === (int) $document->supplier_id);
        $checks[] = $this->check(
            'supplier',
            'Supplier matches linked document',
            $supplierMatches,
            $supplierMatches ? 'Supplier matches the linked '.$linkedDocumentLabel.'.' : 'Select a goods receipt or purchase order for the same supplier.'
        );

        $duplicateMessage = 'Supplier invoice number is unique for this supplier.';
        $duplicatePasses = false;

        if (! filled($document->external_reference)) {
            $duplicateMessage = 'Verify the supplier invoice number before matching.';
        } else {
            $duplicateExists = Document::query()
                ->where('type', 'supplier_invoice')
                ->where('supplier_id', $document->supplier_id)
                ->where('external_reference', $document->external_reference)
                ->whereKeyNot($document->id)
                ->exists();

            $duplicatePasses = ! $duplicateExists;
            if ($duplicateExists) {
                $duplicateMessage = 'This supplier invoice number already exists for this supplier.';
            }
        }

        $checks[] = $this->check('duplicate_invoice_number', 'Duplicate invoice number', $duplicatePasses, $duplicateMessage);

        [$amountPasses, $amountMessage] = $this->amountCheck($document, $relatedDocument, $isDirectException, $verifiedExtraction);
        $checks[] = $this->check('amount', 'Invoice total checked', $amountPasses, $amountMessage);

        $directNotePasses = ! $isDirectException || filled($document->source_note);
        $checks[] = $this->check(
            'direct_exception_note',
            'Exception reason',
            $directNotePasses,
            $directNotePasses ? ($isDirectException ? 'Direct exception reason is recorded.' : 'Normal matching path.') : 'Add a reason for this direct supplier invoice.'
        );

        $blockingMessages = collect($checks)
            ->where('passed', false)
            ->pluck('message')
            ->values()
            ->all();

        return [
            'passes' => $blockingMessages === [],
            'checks' => $checks,
            'blocking_messages' => $blockingMessages,
        ];
    }

    public function amountTolerancePolicy(float $sourceAmount): array
    {
        $roundingTolerance = self::ROUNDING_TOLERANCE;
        $softTolerance = min(self::SOFT_TOLERANCE_AMOUNT, abs($sourceAmount) * self::SOFT_TOLERANCE_RATE);

        return [
            'rounding_tolerance' => $roundingTolerance,
            'soft_tolerance_amount' => self::SOFT_TOLERANCE_AMOUNT,
            'soft_tolerance_rate' => self::SOFT_TOLERANCE_RATE,
            'soft_tolerance_limit' => max($roundingTolerance, $softTolerance),
        ];
    }

    private function check(string $key, string $label, bool $passed, string $message): array
    {
        return compact('key', 'label', 'passed', 'message');
    }

    private function verifiedDetailsMessage(?AttachmentExtraction $extraction): string
    {
        if (! $extraction) {
            return 'Supplier invoice details are verified.';
        }

        return 'Verified by '.$this->verification->methodLabel($extraction).'.';
    }

    private function amountCheck(Document $document, ?Document $relatedDocument, bool $isDirectException, ?AttachmentExtraction $verifiedExtraction): array
    {
        if ($isDirectException) {
            $fields = $verifiedExtraction?->verified_fields ?? [];

            if (filled($fields['total'] ?? null) || (bool) $verifiedExtraction?->recorded_total_confirmed) {
                return [true, 'Invoice total was checked during verification.'];
            }

            return [false, 'Confirm the recorded invoice total before matching.'];
        }

        if (! $relatedDocument) {
            return [false, 'Amount check waits for a linked goods receipt or purchase order.'];
        }

        $invoiceTotal = (float) $document->total;
        $sourceTotal = (float) $relatedDocument->total;
        $difference = abs($invoiceTotal - $sourceTotal);
        $policy = $this->amountTolerancePolicy($sourceTotal);
        $companyProfile = CompanyProfile::active();
        $currency = strtoupper($document->currency ?: $relatedDocument->currency ?: $companyProfile->baseCurrency());
        $money = fn (float $amount): string => $companyProfile->formatMoney($amount, $currency);

        if ($difference == 0.0) {
            return [true, 'Invoice total matches the '.$this->linkedDocumentLabel($relatedDocument).' amount exactly.'];
        }

        if ($difference <= $policy['rounding_tolerance']) {
            return [true, 'Invoice total is within the '.$money($policy['rounding_tolerance']).' rounding tolerance for the '.$this->linkedDocumentLabel($relatedDocument).' amount.'];
        }

        if ($difference <= $policy['soft_tolerance_limit']) {
            return [true, 'Invoice total variance of '.$money($difference).' is within the soft tolerance of '.$money($policy['soft_tolerance_limit']).' for the '.$this->linkedDocumentLabel($relatedDocument).' amount.'];
        }

        if ($invoiceTotal < $sourceTotal) {
            return [false, 'Partial supplier invoice amount differs from the '.$this->linkedDocumentLabel($relatedDocument).' by '.$money($difference).'. Partial matching is not treated as a normal match; use admin or manager override with notes if this partial invoice is accepted.'];
        }

        return [false, 'Invoice total variance of '.$money($difference).' exceeds the soft tolerance of '.$money($policy['soft_tolerance_limit']).'. Admin or manager override with notes is required.'];
    }

    private function linkedDocumentLabel(?Document $document): string
    {
        return match ($document?->type) {
            'goods_receipt' => 'goods receipt',
            'supplier_po' => 'purchase order',
            default => 'linked document',
        };
    }
}
