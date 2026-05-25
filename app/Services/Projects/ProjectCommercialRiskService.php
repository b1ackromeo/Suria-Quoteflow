<?php

namespace App\Services\Projects;

use App\Models\Document;
use App\Models\DocumentItem;

class ProjectCommercialRiskService
{
    private const CONTROLLED_TYPES = [
        'customer_quotation',
        'purchase_request',
        'supplier_po',
        'supplier_invoice',
    ];

    private const ACTIVE_COST_STATUSES = [
        'pending_approval',
        'approved',
        'issued',
        'fulfilled',
        'received',
        'matched',
        'part_paid',
        'paid',
        'closed',
    ];

    public function summary(Document $document): array
    {
        $document->loadMissing(['project', 'items.product', 'items.wbsItem']);
        $summary = $this->emptySummary();

        if (! $document->project_id || ! $document->project || ! in_array($document->type, self::CONTROLLED_TYPES, true)) {
            return $summary;
        }

        $summary['project_controlled'] = true;
        $summary['show_panel'] = true;

        if ($document->items->isEmpty()) {
            return $summary;
        }

        $summary['unassigned_line_count'] = $document->items
            ->filter(fn (DocumentItem $item) => ! filled($item->wbs_item_id))
            ->count();

        if ($summary['unassigned_line_count'] > 0) {
            $lineText = $summary['unassigned_line_count'] === 1 ? 'line is' : 'lines are';
            $this->addWarning(
                $summary,
                'Work item missing',
                $summary['unassigned_line_count'].' project '.$lineText.' not assigned to a work item.',
            );
        }

        if ($document->type === 'customer_quotation') {
            $this->addMarginImpact($document, $summary);
        } else {
            $this->addBudgetImpact($document, $summary);
        }

        $summary['has_warnings'] = $summary['warnings'] !== [];
        $summary['requires_approval_note'] = collect($summary['warnings'])
            ->contains(fn (array $warning) => (bool) ($warning['requires_note'] ?? false));

        return $summary;
    }

    private function emptySummary(): array
    {
        return [
            'project_controlled' => false,
            'show_panel' => false,
            'has_warnings' => false,
            'requires_approval_note' => false,
            'warnings' => [],
            'metrics' => [],
            'work_items' => [],
            'unassigned_line_count' => 0,
        ];
    }

    private function addMarginImpact(Document $document, array &$summary): void
    {
        $quotedRevenue = (float) $document->items->sum(fn (DocumentItem $item) => $this->lineSubtotal($item));
        $estimatedCost = (float) $document->items->sum(fn (DocumentItem $item) => $this->estimatedLineCost($item));
        $expectedMargin = $quotedRevenue - $estimatedCost;
        $expectedMarginPercent = $quotedRevenue > 0 ? ($expectedMargin / $quotedRevenue) * 100 : null;
        $targetMarginPercent = (float) $document->project->margin_target_percent;

        $this->addMetric($summary, 'Quoted revenue', $quotedRevenue, 'money');
        $this->addMetric($summary, 'Estimated cost', $estimatedCost, 'money');
        $this->addMetric($summary, 'Expected gross margin', $expectedMargin, 'money');
        $this->addMetric($summary, 'Expected margin', $expectedMarginPercent, 'percent');
        $this->addMetric($summary, 'Margin target', $targetMarginPercent, 'percent');

        if ($estimatedCost <= 0 && $quotedRevenue > 0) {
            $this->addWarning(
                $summary,
                'Estimated cost missing',
                'Product cost is missing, so the margin check may be incomplete.',
            );
        }

        if ($targetMarginPercent > 0 && $expectedMarginPercent !== null && $expectedMarginPercent < $targetMarginPercent) {
            $this->addWarning(
                $summary,
                'Margin below target',
                'Expected margin is '.$this->formatPercent($expectedMarginPercent).', below the project target of '.$this->formatPercent($targetMarginPercent).'.',
                true,
            );
        }
    }

    private function addBudgetImpact(Document $document, array &$summary): void
    {
        $assignedItems = $document->items
            ->filter(fn (DocumentItem $item) => filled($item->wbs_item_id));
        $currentDocumentCost = (float) $document->items->sum(fn (DocumentItem $item) => $this->lineTotal($item));

        $this->addMetric($summary, $this->currentCostMetricLabel($document->type), $currentDocumentCost, 'money');

        if ($assignedItems->isEmpty()) {
            return;
        }

        $currentByWorkItem = [];
        foreach ($assignedItems as $item) {
            $workItemId = (int) $item->wbs_item_id;
            $currentByWorkItem[$workItemId] = ($currentByWorkItem[$workItemId] ?? 0) + $this->lineTotal($item);
        }

        $workItemIds = array_keys($currentByWorkItem);
        $historicalType = $document->type === 'supplier_invoice' ? 'supplier_invoice' : 'supplier_po';
        $usedBefore = $this->costTotals($document, $workItemIds, $historicalType);
        $checkedBudget = 0.0;
        $usedBeforeTotal = 0.0;

        foreach ($currentByWorkItem as $workItemId => $currentAmount) {
            $workItem = $assignedItems->firstWhere('wbs_item_id', $workItemId)?->wbsItem;

            if (! $workItem) {
                continue;
            }

            $budget = (float) $workItem->cost_budget;
            $previousAmount = (float) ($usedBefore[$workItemId] ?? 0);
            $remainingBefore = $budget - $previousAmount;
            $remainingAfter = $remainingBefore - $currentAmount;
            $checkedBudget += $budget;
            $usedBeforeTotal += $previousAmount;

            $summary['work_items'][] = [
                'code' => $workItem->code,
                'name' => $workItem->name,
                'current_amount' => round($currentAmount, 2),
                'budget' => round($budget, 2),
                'used_before' => round($previousAmount, 2),
                'remaining_before' => round($remainingBefore, 2),
                'remaining_after' => round($remainingAfter, 2),
                'over_budget' => $budget > 0 && $remainingAfter < 0,
            ];

            if ($budget <= 0) {
                $this->addWarning(
                    $summary,
                    'Cost budget missing',
                    'Work item '.$workItem->code.' has no cost budget for approval checking.',
                );
            } elseif ($remainingAfter < 0) {
                $this->addWarning(
                    $summary,
                    $document->type === 'supplier_invoice' ? 'Actual cost over budget' : 'Budget overrun',
                    'Work item '.$workItem->code.' would exceed the remaining budget by '.number_format(abs($remainingAfter), 2).'.',
                    true,
                );
            }
        }

        $this->addMetric($summary, 'Cost budget checked', $checkedBudget, 'money');
        $this->addMetric($summary, $historicalType === 'supplier_invoice' ? 'Actual cost before' : 'Committed before', $usedBeforeTotal, 'money');
        $this->addMetric($summary, 'Remaining after this document', $checkedBudget - $usedBeforeTotal - (float) array_sum($currentByWorkItem), 'money');
    }

    /**
     * @param  array<int>  $workItemIds
     * @return array<int, float>
     */
    private function costTotals(Document $document, array $workItemIds, string $documentType): array
    {
        if ($workItemIds === []) {
            return [];
        }

        return DocumentItem::query()
            ->join('documents', 'documents.id', '=', 'document_items.document_id')
            ->where('documents.project_id', $document->project_id)
            ->where('documents.type', $documentType)
            ->whereIn('documents.status', self::ACTIVE_COST_STATUSES)
            ->whereIn('document_items.wbs_item_id', $workItemIds)
            ->when(
                $document->type === $documentType && $document->exists,
                fn ($query) => $query->where('documents.id', '!=', $document->id),
            )
            ->select('document_items.wbs_item_id')
            ->selectRaw('coalesce(sum(document_items.line_total), 0) as amount')
            ->groupBy('document_items.wbs_item_id')
            ->pluck('amount', 'document_items.wbs_item_id')
            ->map(fn ($amount) => (float) $amount)
            ->all();
    }

    private function addMetric(array &$summary, string $label, mixed $value, string $format): void
    {
        $summary['metrics'][] = [
            'label' => $label,
            'value' => $value,
            'format' => $format,
        ];
    }

    private function addWarning(array &$summary, string $title, string $message, bool $requiresNote = false): void
    {
        $summary['warnings'][] = [
            'title' => $title,
            'message' => $message,
            'requires_note' => $requiresNote,
            'state' => $requiresNote ? 'blocked' : 'waiting',
        ];
    }

    private function currentCostMetricLabel(string $documentType): string
    {
        return match ($documentType) {
            'purchase_request' => 'Requested cost',
            'supplier_po' => 'Purchase order cost',
            'supplier_invoice' => 'Supplier invoice cost',
            default => 'Document cost',
        };
    }

    private function lineSubtotal(DocumentItem $item): float
    {
        return round((float) $item->quantity * (float) $item->unit_price, 2);
    }

    private function lineTotal(DocumentItem $item): float
    {
        return round((float) $item->line_total, 2);
    }

    private function estimatedLineCost(DocumentItem $item): float
    {
        if (! $item->product) {
            return 0.0;
        }

        return round((float) $item->quantity * (float) $item->product->cost_price, 2);
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.').'%';
    }
}
