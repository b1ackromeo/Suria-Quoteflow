<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class AttachmentExtraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'attachment_id',
        'document_id',
        'status',
        'engine',
        'language',
        'raw_text',
        'extracted_fields',
        'verified_fields',
        'verification_method',
        'verification_notes',
        'supplier_confirmed',
        'recorded_total_confirmed',
        'error_message',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'extracted_fields' => 'array',
        'verified_fields' => 'array',
        'supplier_confirmed' => 'boolean',
        'recorded_total_confirmed' => 'boolean',
        'verified_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $extraction): void {
            $extraction->syncVerifiedPurchaseRequestQuoteLines();
        });
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    private function syncVerifiedPurchaseRequestQuoteLines(): void
    {
        if ($this->status !== 'verified') {
            return;
        }

        $this->loadMissing(['attachment.document', 'document']);

        $document = $this->document ?: $this->attachment?->document;

        if (! $document || $document->type !== 'purchase_request') {
            return;
        }

        if (($this->attachment?->category ?? null) !== 'supplier_quote') {
            return;
        }

        $fields = $this->verified_fields ?? [];
        $items = collect($fields['items'] ?? [])
            ->filter(fn ($item) => is_array($item) && filled($item['description'] ?? null))
            ->values();

        if ($items->isEmpty()) {
            return;
        }

        $subtotal = 0.0;
        $taxTotal = 0.0;

        $document->items()->delete();

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $taxRate = (float) ($item['tax_rate'] ?? 0);
            $lineSubtotal = round($quantity * $unitPrice, 2);
            $taxAmount = round($lineSubtotal * ($taxRate / 100), 2);

            $document->items()->create([
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? 'unit',
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_total' => $lineSubtotal + $taxAmount,
            ]);

            $subtotal += $lineSubtotal;
            $taxTotal += $taxAmount;
        }

        $payload = [
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($taxTotal, 2),
            'total' => round($subtotal + $taxTotal, 2),
            'source_type' => 'supplier_quote',
        ];

        if (filled($fields['quote_number'] ?? null)) {
            $payload['external_reference'] = $fields['quote_number'];
        }

        if (filled($fields['quote_date'] ?? null)) {
            try {
                $payload['issue_date'] = Carbon::parse($fields['quote_date'])->toDateString();
            } catch (\Throwable) {
                // Keep the verified value stored on the extraction if the date cannot be parsed.
            }
        }

        if (filled($fields['valid_until'] ?? null)) {
            try {
                $payload['due_date'] = Carbon::parse($fields['valid_until'])->toDateString();
            } catch (\Throwable) {
                // Keep the verified value stored on the extraction if the date cannot be parsed.
            }
        }

        if (filled($fields['payment_terms'] ?? null)) {
            $payload['payment_terms_label'] = $fields['payment_terms'];
        }

        foreach (['subtotal', 'tax_total', 'total'] as $field) {
            $amount = $this->decimalFromExtraction($fields[$field] ?? null);

            if ($amount !== null) {
                $payload[$field] = $amount;
            }
        }

        $document->update($payload);
    }

    private function decimalFromExtraction(mixed $value): ?float
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
}
