<?php

namespace App\Services\Projects;

use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\Payment;
use App\Models\Project;
use App\Models\WbsItem;
use Illuminate\Support\Collection;

class ProjectCommercialReportService
{
    private const REPORT_STATUSES = [
        'draft',
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

    /**
     * @param  Collection<int, WbsItem>  $workItems
     */
    public function report(Project $project, Collection $workItems): array
    {
        $summary = $this->commercialSummary($project);
        $workItemReport = $this->workItemSummaries($project, $workItems);

        return [
            'summary' => $summary,
            'work_items' => $workItemReport['items'],
            'totals' => $workItemReport['totals'],
            'unassigned' => $workItemReport['unassigned'],
            'exceptions' => $this->exceptions($project, $summary, $workItems, $workItemReport),
        ];
    }

    private function commercialSummary(Project $project): array
    {
        $documents = Document::query()
            ->where('project_id', $project->id)
            ->whereIn('status', self::REPORT_STATUSES);

        $quotedRevenue = (float) (clone $documents)->where('type', 'customer_quotation')->sum('total');
        $customerConfirmed = (float) (clone $documents)->where('type', 'customer_po')->sum('total');
        $customerInvoiced = (float) (clone $documents)->where('type', 'customer_invoice')->sum('total');
        $supplierCommitted = (float) (clone $documents)->where('type', 'supplier_po')->sum('total');
        $receivedCost = (float) (clone $documents)->where('type', 'goods_receipt')->sum('total');
        $supplierInvoiced = (float) (clone $documents)->where('type', 'supplier_invoice')->sum('total');

        $customerPaid = (float) Payment::query()
            ->whereHas('document', fn ($query) => $query
                ->where('project_id', $project->id)
                ->where('type', 'customer_invoice')
                ->whereIn('status', self::REPORT_STATUSES))
            ->sum('amount');
        $supplierPaid = (float) Payment::query()
            ->whereHas('document', fn ($query) => $query
                ->where('project_id', $project->id)
                ->where('type', 'supplier_invoice')
                ->whereIn('status', self::REPORT_STATUSES))
            ->sum('amount');

        $expectedMargin = $customerConfirmed - $supplierCommitted;
        $actualMargin = $customerInvoiced - $supplierInvoiced;

        return [
            'quoted_revenue' => $quotedRevenue,
            'customer_confirmed' => $customerConfirmed,
            'customer_invoiced' => $customerInvoiced,
            'customer_paid' => $customerPaid,
            'estimated_cost' => $this->estimatedQuotationCost($project),
            'supplier_committed' => $supplierCommitted,
            'received_cost' => $receivedCost,
            'supplier_invoiced' => $supplierInvoiced,
            'supplier_paid' => $supplierPaid,
            'expected_margin' => $expectedMargin,
            'expected_margin_percent' => $customerConfirmed > 0 ? ($expectedMargin / $customerConfirmed) * 100 : null,
            'actual_margin' => $actualMargin,
            'actual_margin_percent' => $customerInvoiced > 0 ? ($actualMargin / $customerInvoiced) * 100 : null,
            'unbilled_revenue' => $customerConfirmed - $customerInvoiced,
            'unpaid_supplier_cost' => $supplierInvoiced - $supplierPaid,
            'budget_remaining' => (float) $project->budget_amount - $supplierCommitted,
            'document_count' => (clone $documents)->count(),
        ];
    }

    /**
     * @param  Collection<int, WbsItem>  $workItems
     */
    private function workItemSummaries(Project $project, Collection $workItems): array
    {
        $lineTotals = DocumentItem::query()
            ->join('documents', 'documents.id', '=', 'document_items.document_id')
            ->where('document_items.project_id', $project->id)
            ->whereIn('documents.status', self::REPORT_STATUSES)
            ->select('document_items.wbs_item_id')
            ->selectRaw('count(*) as line_count')
            ->selectRaw("sum(case when documents.type = 'customer_quotation' then document_items.line_total else 0 end) as quoted_revenue")
            ->selectRaw("sum(case when documents.type = 'customer_po' then document_items.line_total else 0 end) as customer_confirmed")
            ->selectRaw("sum(case when documents.type = 'customer_invoice' then document_items.line_total else 0 end) as customer_invoiced")
            ->selectRaw("sum(case when documents.type = 'supplier_po' then document_items.line_total else 0 end) as supplier_committed")
            ->selectRaw("sum(case when documents.type = 'goods_receipt' then document_items.line_total else 0 end) as received_cost")
            ->selectRaw("sum(case when documents.type = 'supplier_invoice' then document_items.line_total else 0 end) as supplier_actual")
            ->groupBy('document_items.wbs_item_id')
            ->get()
            ->keyBy(fn ($row) => $row->wbs_item_id === null ? 'unassigned' : (string) $row->wbs_item_id);

        $items = [];
        $totals = [
            'revenue_budget' => 0.0,
            'cost_budget' => 0.0,
            'quoted_revenue' => 0.0,
            'customer_confirmed' => 0.0,
            'customer_invoiced' => 0.0,
            'supplier_committed' => 0.0,
            'received_cost' => 0.0,
            'supplier_actual' => 0.0,
            'remaining_budget' => 0.0,
            'actual_variance' => 0.0,
            'line_count' => 0,
        ];

        foreach ($workItems as $workItem) {
            $line = $lineTotals->get((string) $workItem->id);
            $costBudget = (float) $workItem->cost_budget;
            $supplierCommitted = (float) ($line->supplier_committed ?? 0);
            $supplierActual = (float) ($line->supplier_actual ?? 0);
            $summary = [
                'line_count' => (int) ($line->line_count ?? 0),
                'quoted_revenue' => (float) ($line->quoted_revenue ?? 0),
                'customer_confirmed' => (float) ($line->customer_confirmed ?? 0),
                'customer_invoiced' => (float) ($line->customer_invoiced ?? 0),
                'supplier_committed' => $supplierCommitted,
                'received_cost' => (float) ($line->received_cost ?? 0),
                'supplier_actual' => $supplierActual,
                'remaining_budget' => $costBudget - $supplierCommitted,
                'actual_variance' => $costBudget - $supplierActual,
                'over_committed' => $costBudget > 0 && $supplierCommitted > $costBudget,
                'over_actual' => $costBudget > 0 && $supplierActual > $costBudget,
            ];

            $items[$workItem->id] = $summary;

            $totals['revenue_budget'] += (float) $workItem->revenue_budget;
            $totals['cost_budget'] += $costBudget;
            $totals['line_count'] += $summary['line_count'];

            foreach (['quoted_revenue', 'customer_confirmed', 'customer_invoiced', 'supplier_committed', 'received_cost', 'supplier_actual'] as $key) {
                $totals[$key] += $summary[$key];
            }
        }

        $totals['remaining_budget'] = $totals['cost_budget'] - $totals['supplier_committed'];
        $totals['actual_variance'] = $totals['cost_budget'] - $totals['supplier_actual'];
        $unassigned = $lineTotals->get('unassigned');

        return [
            'items' => $items,
            'totals' => $totals,
            'unassigned' => [
                'line_count' => (int) ($unassigned->line_count ?? 0),
                'quoted_revenue' => (float) ($unassigned->quoted_revenue ?? 0),
                'customer_confirmed' => (float) ($unassigned->customer_confirmed ?? 0),
                'customer_invoiced' => (float) ($unassigned->customer_invoiced ?? 0),
                'supplier_committed' => (float) ($unassigned->supplier_committed ?? 0),
                'received_cost' => (float) ($unassigned->received_cost ?? 0),
                'supplier_actual' => (float) ($unassigned->supplier_actual ?? 0),
            ],
        ];
    }

    private function estimatedQuotationCost(Project $project): float
    {
        return (float) DocumentItem::query()
            ->join('documents', 'documents.id', '=', 'document_items.document_id')
            ->leftJoin('products', 'products.id', '=', 'document_items.product_id')
            ->where('document_items.project_id', $project->id)
            ->where('documents.type', 'customer_quotation')
            ->whereIn('documents.status', self::REPORT_STATUSES)
            ->selectRaw('coalesce(sum(document_items.quantity * coalesce(products.cost_price, 0)), 0) as amount')
            ->value('amount');
    }

    /**
     * @param  Collection<int, WbsItem>  $workItems
     */
    private function exceptions(Project $project, array $summary, Collection $workItems, array $workItemReport): array
    {
        $exceptions = [];
        $targetMargin = (float) $project->margin_target_percent;

        if ($targetMargin > 0 && $summary['expected_margin_percent'] !== null && $summary['expected_margin_percent'] < $targetMargin) {
            $exceptions[] = [
                'state' => 'blocked',
                'title' => 'Margin below target',
                'message' => 'Expected margin is '.$this->formatPercent($summary['expected_margin_percent']).', below the project target of '.$this->formatPercent($targetMargin).'.',
            ];
        }

        if (($workItemReport['unassigned']['line_count'] ?? 0) > 0) {
            $lineCount = (int) $workItemReport['unassigned']['line_count'];
            $exceptions[] = [
                'state' => 'waiting',
                'title' => 'Work item missing',
                'message' => $lineCount.' project line'.($lineCount === 1 ? '' : 's').' still need a work item or cost code.',
            ];
        }

        foreach ($workItems as $workItem) {
            $itemSummary = $workItemReport['items'][$workItem->id] ?? null;

            if (! $itemSummary) {
                continue;
            }

            if ($itemSummary['over_committed']) {
                $exceptions[] = [
                    'state' => 'blocked',
                    'title' => 'Budget overrun',
                    'message' => $workItem->displayLabel().' has supplier commitments above its cost budget.',
                    'amount' => abs($itemSummary['remaining_budget']),
                ];
            }

            if ($itemSummary['over_actual']) {
                $exceptions[] = [
                    'state' => 'blocked',
                    'title' => 'Actual cost over budget',
                    'message' => $workItem->displayLabel().' has supplier invoices above its cost budget.',
                    'amount' => abs($itemSummary['actual_variance']),
                ];
            }
        }

        if ($summary['supplier_committed'] > 0 && $summary['supplier_invoiced'] > $summary['supplier_committed']) {
            $exceptions[] = [
                'state' => 'waiting',
                'title' => 'Supplier actual above committed',
                'message' => 'Supplier invoices are above purchase order commitments.',
                'amount' => $summary['supplier_invoiced'] - $summary['supplier_committed'],
            ];
        }

        return $exceptions;
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.').'%';
    }
}
