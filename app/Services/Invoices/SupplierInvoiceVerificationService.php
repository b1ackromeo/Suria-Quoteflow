<?php

namespace App\Services\Invoices;

use App\Models\Attachment;
use App\Models\AttachmentExtraction;
use App\Models\Document;
use Illuminate\Support\Collection;

class SupplierInvoiceVerificationService
{
    public const METHOD_OCR_ASSISTED = 'ocr_assisted';
    public const METHOD_MANUAL = 'manual';
    public const METHOD_EXTERNAL = 'external';

    public const METHOD_LABELS = [
        self::METHOD_OCR_ASSISTED => 'OCR-assisted',
        self::METHOD_MANUAL => 'Manual',
        self::METHOD_EXTERNAL => 'External',
    ];

    public function isVerified(Document $document): bool
    {
        return $this->blockingIssues($document) === [];
    }

    public function blockingIssues(Document $document): array
    {
        if ($document->type !== 'supplier_invoice') {
            return [];
        }

        $document->loadMissing('attachments.extraction.verifier');
        $invoiceCopies = $this->invoiceCopies($document);

        if ($invoiceCopies->isEmpty()) {
            return ['Upload the supplier invoice file as Invoice copy.'];
        }

        $verifiedExtraction = $this->verifiedExtraction($document);

        if (! $verifiedExtraction) {
            if ($invoiceCopies->contains(fn (Attachment $attachment) => $attachment->extraction?->status === 'processed')) {
                return ['Verify invoice details before submitting for approval.'];
            }

            if ($invoiceCopies->contains(fn (Attachment $attachment) => $attachment->extraction?->status === 'failed')) {
                return ['OCR could not read this file. Verify invoice details manually before submitting for approval.'];
            }

            return ['Verify supplier invoice details before submitting for approval.'];
        }

        return $this->verifiedExtractionIssues($verifiedExtraction);
    }

    public function summary(Document $document): array
    {
        $verifiedExtraction = $this->verifiedExtraction($document);
        $issues = $this->blockingIssues($document);

        return [
            'verified' => $issues === [],
            'issues' => $issues,
            'extraction' => $verifiedExtraction,
            'method' => $verifiedExtraction ? $this->method($verifiedExtraction) : null,
            'method_label' => $verifiedExtraction ? $this->methodLabel($verifiedExtraction) : null,
            'fields' => $verifiedExtraction?->verified_fields ?? [],
            'verified_at' => $verifiedExtraction?->verified_at,
            'verifier' => $verifiedExtraction?->verifier,
        ];
    }

    public function verifiedExtraction(Document $document): ?AttachmentExtraction
    {
        $document->loadMissing('attachments.extraction.verifier');

        return $this->invoiceCopies($document)
            ->map(fn (Attachment $attachment) => $attachment->extraction)
            ->filter(fn (?AttachmentExtraction $extraction) => $extraction?->status === 'verified')
            ->sortByDesc(fn (AttachmentExtraction $extraction) => $extraction->verified_at?->timestamp ?? 0)
            ->first();
    }

    public function method(AttachmentExtraction $extraction): string
    {
        return $extraction->verification_method ?: self::METHOD_OCR_ASSISTED;
    }

    public function methodLabel(AttachmentExtraction $extraction): string
    {
        return self::METHOD_LABELS[$this->method($extraction)] ?? 'Verified';
    }

    /**
     * @return Collection<int, Attachment>
     */
    private function invoiceCopies(Document $document): Collection
    {
        return $document->attachments->where('category', 'invoice_copy')->values();
    }

    /**
     * @return array<int, string>
     */
    private function verifiedExtractionIssues(AttachmentExtraction $extraction): array
    {
        $fields = $extraction->verified_fields ?? [];
        $issues = [];

        if (! filled($fields['invoice_number'] ?? null)) {
            $issues[] = 'Verify the supplier invoice number.';
        }

        if (! filled($fields['invoice_date'] ?? null)) {
            $issues[] = 'Verify the supplier invoice date.';
        }

        if (! filled($fields['total'] ?? null) && ! $extraction->recorded_total_confirmed) {
            $issues[] = 'Enter the invoice total or confirm the recorded total was checked.';
        }

        if (! $extraction->supplier_confirmed) {
            $issues[] = 'Confirm the invoice supplier matches this supplier record.';
        }

        if (! $extraction->verified_by || ! $extraction->verified_at) {
            $issues[] = 'Verify who checked the supplier invoice details.';
        }

        $method = $this->method($extraction);

        if (! isset(self::METHOD_LABELS[$method])) {
            $issues[] = 'Select how the supplier invoice details were verified.';
        }

        if (in_array($method, [self::METHOD_MANUAL, self::METHOD_EXTERNAL], true) && ! filled($extraction->verification_notes)) {
            $issues[] = 'Add verification notes for manual or external verification.';
        }

        return $issues;
    }
}
