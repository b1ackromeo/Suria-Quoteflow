<?php

namespace App\Services\Documents;

use App\Models\Attachment;
use App\Models\Document;
use Illuminate\Support\Carbon;
use Throwable;

class ExternalDocumentExtractionService
{
    public const METHOD_OCR_ASSISTED = 'ocr_assisted';
    public const METHOD_MANUAL = 'manual';

    public function supportsAssistedCapture(Document $document): bool
    {
        return in_array($document->type, ['purchase_request', 'supplier_invoice', 'supplier_quotation'], true);
    }

    public function shouldAutoExtract(Attachment $attachment): bool
    {
        $attachment->loadMissing('document');

        if (! $attachment->document) {
            return false;
        }

        return match ($attachment->document->type) {
            'purchase_request' => $attachment->category === 'supplier_quote',
            'supplier_invoice' => true,
            'supplier_quotation' => in_array($attachment->category, ['supplier_quote', 'supporting_document'], true),
            default => false,
        };
    }

    public function uploadProcessedMessage(Document $document): string
    {
        return match ($document->type) {
            'purchase_request', 'supplier_quotation' => 'Attachment uploaded. Quote OCR draft is ready for verification.',
            default => 'Attachment uploaded. OCR extraction draft is ready for verification.',
        };
    }

    public function uploadFailedMessage(Document $document): string
    {
        return match ($document->type) {
            'purchase_request', 'supplier_quotation' => 'Attachment uploaded. OCR could not read the supplier quotation; verify the quote details manually from the source file.',
            default => 'Attachment uploaded. OCR extraction could not run yet; check the extraction message on the invoice page.',
        };
    }

    public function readyMessage(Document $document): string
    {
        return match ($document->type) {
            'purchase_request', 'supplier_quotation' => 'Quote OCR draft is ready. Verify the fields before using the quote for purchasing.',
            default => 'OCR extraction draft is ready. Verify the fields before approval.',
        };
    }

    public function verificationSuccessMessage(Document $document): string
    {
        return match ($document->type) {
            'purchase_request' => 'Supplier quote evidence verified.',
            'supplier_quotation' => 'Supplier quotation details verified.',
            default => 'Supplier invoice extraction verified.',
        };
    }

    public function extractionUnavailableMessage(): string
    {
        return 'Only PDF and image files can be extracted.';
    }

    public function fieldLabels(Document $document): array
    {
        return match ($document->type) {
            'purchase_request', 'supplier_quotation' => [
                'supplier_name' => 'Supplier name',
                'quote_number' => 'Supplier quote no.',
                'quote_date' => 'Quote date',
                'valid_until' => 'Valid until',
                'subtotal' => 'Subtotal',
                'tax_total' => 'Tax amount',
                'total' => 'Quote total',
                'payment_terms' => 'Payment terms',
            ],
            default => [],
        };
    }

    public function manualFields(Document $document): array
    {
        return match ($document->type) {
            'purchase_request', 'supplier_quotation' => [
                'supplier_name' => $document->supplier?->name,
                'quote_number' => $document->external_reference,
                'quote_date' => optional($document->issue_date)->format('Y-m-d'),
                'valid_until' => optional($document->due_date)->format('Y-m-d'),
                'subtotal' => number_format((float) $document->subtotal, 2, '.', ''),
                'tax_total' => number_format((float) $document->tax_total, 2, '.', ''),
                'total' => number_format((float) $document->total, 2, '.', ''),
                'payment_terms' => $document->paymentTermsDisplay(),
            ],
            default => [],
        };
    }

    public function manualItems(Document $document): array
    {
        return $document->items
            ->map(fn ($item) => [
                'description' => $item->description,
                'quantity' => number_format((float) $item->quantity, 3, '.', ''),
                'unit' => $item->unit,
                'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
                'tax_rate' => number_format((float) $item->tax_rate, 2, '.', ''),
            ])
            ->values()
            ->all();
    }

    public function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach (array_values($items) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '') {
                continue;
            }

            $quantity = $this->decimalFromText($item['quantity'] ?? null) ?? 1.0;
            $lineTotal = $this->decimalFromText($item['line_total'] ?? null);
            $unitPrice = $this->decimalFromText($item['unit_price'] ?? null);

            if ($unitPrice === null && $lineTotal !== null && $quantity > 0) {
                $unitPrice = round($lineTotal / $quantity, 2);
            }

            $normalized[] = [
                'product_id' => null,
                'description' => $description,
                'quantity' => $quantity,
                'unit' => trim((string) ($item['unit'] ?? 'unit')) ?: 'unit',
                'unit_price' => $unitPrice ?? 0.0,
                'tax_rate' => $this->decimalFromText($item['tax_rate'] ?? null) ?? 0.0,
            ];

            if (count($normalized) >= 20) {
                break;
            }
        }

        return $normalized;
    }

    public function supplierQuotationPayload(array $fields): array
    {
        $payload = [];

        if (filled($fields['quote_number'] ?? null)) {
            $payload['external_reference'] = $fields['quote_number'];
        }

        if (filled($fields['quote_date'] ?? null)) {
            $date = $this->dateFromText($fields['quote_date']);
            if ($date) {
                $payload['issue_date'] = $date;
            }
        }

        if (filled($fields['valid_until'] ?? null)) {
            $date = $this->dateFromText($fields['valid_until']);
            if ($date) {
                $payload['due_date'] = $date;
            }
        }

        foreach (['subtotal', 'tax_total', 'total'] as $moneyField) {
            $amount = $this->decimalFromText($fields[$moneyField] ?? null);
            if ($amount !== null) {
                $payload[$moneyField] = $amount;
            }
        }

        if (filled($fields['payment_terms'] ?? null)) {
            $payload['payment_terms_label'] = $fields['payment_terms'];
        }

        return $payload;
    }

    private function decimalFromText(mixed $value): ?float
    {
        if (! filled($value)) {
            return null;
        }

        $normalized = preg_replace('/[^\d.\-]/', '', (string) $value);

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    private function dateFromText(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (Throwable) {
            return null;
        }
    }
}
