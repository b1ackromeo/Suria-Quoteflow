<?php

namespace App\Services\Documents;

use App\Models\Attachment;
use App\Services\Ocr\TesseractInvoiceExtractor;

class BusinessDocumentCaptureService extends TesseractInvoiceExtractor
{
    public const ENGINE = 'quoteflow_document_capture';

    public function __construct(private ?PaddleOcrDocumentAnalyzer $paddleOcr = null)
    {
    }

    public function extract(Attachment $attachment): array
    {
        $attachment->loadMissing('document');
        $originalDocument = $attachment->document;

        if ($originalDocument?->type === 'purchase_request' && $attachment->category === 'supplier_quote') {
            $quotationDocument = $originalDocument->replicate();
            $quotationDocument->type = 'supplier_quotation';
            $attachment->setRelation('document', $quotationDocument);
        }

        try {
            $result = parent::extract($attachment);
        } finally {
            if ($originalDocument) {
                $attachment->setRelation('document', $originalDocument);
            }
        }

        $localMechanism = $result['engine'] ?? 'local';
        $result['engine'] = self::ENGINE.':'.$localMechanism;

        return $result;
    }

    public function engineName(): string
    {
        return self::ENGINE;
    }

    protected function initialTextCandidates(Attachment $attachment, ?string $documentType): array
    {
        if (! $this->paddleOcr) {
            return [];
        }

        return collect($this->paddleOcr->candidates($attachment))
            ->map(fn (array $candidate) => $this->candidateFromText(
                (string) ($candidate['engine'] ?? 'paddleocr-'.PaddleOcrDocumentAnalyzer::VERSION),
                (string) ($candidate['text'] ?? ''),
                $documentType
            ))
            ->filter()
            ->values()
            ->all();
    }
}
