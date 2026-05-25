<?php

namespace App\Services\Documents;

use App\Models\CompanyProfile;
use App\Models\Document;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DocumentConversionService
{
    private const CONVERSIONS = [
        [
            'source_type' => 'customer_quotation',
            'target_module' => 'customer-pos',
            'target_type' => 'customer_po',
            'statuses' => ['approved', 'issued'],
            'label' => 'Create customer PO received',
        ],
        [
            'source_type' => 'customer_po',
            'target_module' => 'customer-invoices',
            'target_type' => 'customer_invoice',
            'statuses' => ['fulfilled'],
            'label' => 'Create customer invoice',
        ],
        [
            'source_type' => 'purchase_request',
            'target_module' => 'supplier-quotations',
            'target_type' => 'supplier_quotation',
            'statuses' => ['approved'],
            'label' => 'Add supplier quotation',
        ],
        [
            'source_type' => 'purchase_request',
            'target_module' => 'supplier-pos',
            'target_type' => 'supplier_po',
            'statuses' => ['approved'],
            'label' => 'Create purchase order',
        ],
        [
            'source_type' => 'supplier_quotation',
            'target_module' => 'supplier-pos',
            'target_type' => 'supplier_po',
            'statuses' => ['approved'],
            'label' => 'Create purchase order',
        ],
        [
            'source_type' => 'supplier_po',
            'target_module' => 'goods-receipts',
            'target_type' => 'goods_receipt',
            'statuses' => ['issued'],
            'label' => 'Record goods receipt',
        ],
        [
            'source_type' => 'goods_receipt',
            'target_module' => 'supplier-invoices',
            'target_type' => 'supplier_invoice',
            'statuses' => ['received'],
            'label' => 'Record supplier invoice',
        ],
    ];

    public function optionsFor(Document $source): array
    {
        return array_values(array_filter(
            self::CONVERSIONS,
            fn (array $conversion) => $conversion['source_type'] === $source->type
                && in_array($source->status, $conversion['statuses'], true)
        ));
    }

    public function convert(Document $source, string $targetModule, int $userId): Document
    {
        $conversion = $this->conversionFor($source, $targetModule);
        abort_unless($conversion, 422, 'This document cannot create that next document.');

        $targetMeta = Document::metaForSlug($targetModule);
        abort_unless($targetMeta['type'] === $conversion['target_type'], 422, 'Select a valid next document type.');

        $source->loadMissing(['items', 'billingStages']);

        return DB::transaction(function () use ($source, $targetMeta, $userId) {
            $target = Document::create($this->targetAttributes($source, $targetMeta, $userId));

            $this->copyItems($source, $target);
            $this->copyBillingStages($source, $target);

            $target->load(['items', 'billingStages']);
            Audit::record('document_created', $target, null, $target->toArray());
            Audit::record('document_converted', $target, [
                'source_document_id' => $source->id,
                'source_document_number' => $source->document_number,
            ], [
                'document_id' => $target->id,
                'document_number' => $target->document_number,
                'related_document_id' => $target->related_document_id,
                'source_type' => $target->source_type,
            ]);

            return $target;
        });
    }

    private function conversionFor(Document $source, string $targetModule): ?array
    {
        foreach ($this->optionsFor($source) as $conversion) {
            if ($conversion['target_module'] === $targetModule) {
                return $conversion;
            }
        }

        return null;
    }

    private function targetAttributes(Document $source, array $targetMeta, int $userId): array
    {
        $targetType = $targetMeta['type'];
        $paymentDueDays = $targetType === 'goods_receipt' ? null : $source->payment_due_days;
        $issueDate = now()->toDateString();

        return [
            'type' => $targetType,
            'direction' => $targetMeta['direction'],
            'document_number' => Document::nextNumber($targetType),
            'external_reference' => $this->externalReferenceFor($targetType, $source),
            'customer_id' => $source->customer_id,
            'supplier_id' => $source->supplier_id,
            'related_document_id' => $source->id,
            'project_id' => $source->project_id,
            'source_type' => $this->sourceTypeForRelatedDocument($targetType, $source->type),
            'source_note' => null,
            'project_name' => $source->project_name,
            'delivery_to' => $source->delivery_to,
            'status' => 'draft',
            'issue_date' => $issueDate,
            'due_date' => $this->dueDateFor($targetType, $issueDate, $paymentDueDays),
            'currency' => $source->currency ?: CompanyProfile::active()->baseCurrency(),
            'payment_terms_type' => $targetType === 'goods_receipt' ? 'standard' : ($source->payment_terms_type ?: 'standard'),
            'payment_due_days' => $paymentDueDays,
            'payment_terms_label' => $targetType === 'goods_receipt' ? null : $source->payment_terms_label,
            'progress_invoice_number' => null,
            'progress_invoice_total' => null,
            'billing_stage_name' => null,
            'notes' => $targetType === 'goods_receipt' ? null : $source->notes,
            'terms' => $targetType === 'goods_receipt' ? null : $source->terms,
            'created_by' => $userId,
        ];
    }

    private function externalReferenceFor(string $targetType, Document $source): ?string
    {
        return match ($targetType) {
            'customer_invoice' => $source->external_reference ?: $source->document_number,
            'supplier_po' => $source->external_reference ?: $source->document_number,
            default => null,
        };
    }

    private function dueDateFor(string $targetType, string $issueDate, ?int $paymentDueDays): ?string
    {
        if (! in_array($targetType, ['customer_invoice', 'supplier_invoice'], true) || $paymentDueDays === null) {
            return null;
        }

        return Carbon::parse($issueDate)->addDays($paymentDueDays)->toDateString();
    }

    private function copyItems(Document $source, Document $target): void
    {
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($source->items as $item) {
            $quantity = (float) $item->quantity;
            $unitPrice = (float) $item->unit_price;
            $taxRate = $target->type === 'goods_receipt' ? 0 : (float) $item->tax_rate;
            $lineSubtotal = round($quantity * $unitPrice, 2);
            $taxAmount = round($lineSubtotal * ($taxRate / 100), 2);

            $target->items()->create([
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $quantity,
                'unit' => $item->unit,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_total' => $lineSubtotal + $taxAmount,
            ]);

            $subtotal += $lineSubtotal;
            $taxTotal += $taxAmount;
        }

        $target->update([
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $subtotal + $taxTotal,
        ]);
    }

    private function copyBillingStages(Document $source, Document $target): void
    {
        foreach ($source->billingStages as $stage) {
            $target->billingStages()->create([
                'sort_order' => $stage->sort_order,
                'stage_name' => $stage->stage_name,
                'condition_label' => $stage->condition_label,
                'percentage' => $stage->percentage,
                'amount' => $stage->amount,
                'payment_term' => $stage->payment_term,
                'previously_invoiced' => 0,
                'current_invoice' => 0,
                'remaining_amount' => $stage->remaining_amount,
                'is_current' => false,
            ]);
        }
    }

    private function sourceTypeForRelatedDocument(string $targetType, string $sourceType): ?string
    {
        return match ([$targetType, $sourceType]) {
            ['supplier_quotation', 'purchase_request'] => 'purchase_request',
            ['supplier_po', 'supplier_quotation'] => 'supplier_quote',
            ['supplier_po', 'purchase_request'] => 'purchase_request',
            ['goods_receipt', 'supplier_po'] => 'supplier_po',
            ['supplier_invoice', 'goods_receipt'] => 'goods_receipt',
            ['customer_po', 'customer_quotation'] => 'quotation',
            ['customer_invoice', 'customer_po'] => 'customer_po',
            default => null,
        };
    }
}
