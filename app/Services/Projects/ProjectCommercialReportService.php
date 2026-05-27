<?php

namespace App\Services\Projects;

use App\Models\Document;
use App\Models\DocumentBillingStage;
use App\Models\DocumentItem;
use App\Models\Payment;
use App\Models\Project;
use App\Models\WbsItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectCommercialReportService
{
    private const EVIDENCE_LIMIT = 25;

    public const REPORT_STATUSES = [
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

    public const REVIEW_FILTERS = [
        'needs_review' => 'Needs review',
        'clear' => 'No visible exceptions',
    ];

    /**
     * @param  Collection<int, Project>  $projects
     */
    public function portfolio(Collection $projects): array
    {
        $projectIds = $projects->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        if ($projectIds === []) {
            return [
                'items' => [],
                'totals' => $this->emptyPortfolioTotals(),
            ];
        }

        $documentTotals = $this->documentTotalsByProject($projectIds);
        $unassignedLineCounts = $this->unassignedLineCountsByProject($projectIds);
        $workItemExceptions = $this->workItemExceptionCountsByProject($projectIds);
        $items = [];
        $totals = $this->emptyPortfolioTotals();

        foreach ($projects as $project) {
            $projectId = (int) $project->id;
            $documentSummary = $documentTotals[$projectId] ?? $this->emptyDocumentTotals();
            $customerConfirmed = $documentSummary['customer_confirmed'];
            $supplierCommitted = $documentSummary['supplier_committed'];
            $supplierInvoiced = $documentSummary['supplier_invoiced'];
            $expectedMargin = $customerConfirmed - $supplierCommitted;
            $expectedMarginPercent = $customerConfirmed > 0 ? ($expectedMargin / $customerConfirmed) * 100 : null;
            $budgetRemaining = (float) $project->budget_amount - $supplierCommitted;
            $unassignedLineCount = (int) ($unassignedLineCounts[$projectId] ?? 0);
            $workItemException = $workItemExceptions[$projectId] ?? [
                'over_committed_count' => 0,
                'over_actual_count' => 0,
            ];
            $reviewReasons = $this->portfolioReviewReasons(
                $project,
                $expectedMarginPercent,
                $budgetRemaining,
                $supplierCommitted,
                $supplierInvoiced,
                $unassignedLineCount,
                $workItemException,
            );

            $items[$projectId] = [
                'customer_confirmed' => $customerConfirmed,
                'customer_invoiced' => $documentSummary['customer_invoiced'],
                'supplier_committed' => $supplierCommitted,
                'supplier_invoiced' => $supplierInvoiced,
                'expected_margin' => $expectedMargin,
                'expected_margin_percent' => $expectedMarginPercent,
                'budget_remaining' => $budgetRemaining,
                'unassigned_line_count' => $unassignedLineCount,
                'review_count' => count($reviewReasons),
                'review_reasons' => $reviewReasons,
            ];

            $totals['customer_confirmed'] += $customerConfirmed;
            $totals['customer_invoiced'] += $documentSummary['customer_invoiced'];
            $totals['supplier_committed'] += $supplierCommitted;
            $totals['supplier_invoiced'] += $supplierInvoiced;
            $totals['expected_margin'] += $expectedMargin;
            $totals['unassigned_line_count'] += $unassignedLineCount;

            if ($reviewReasons !== []) {
                $totals['review_projects']++;
            }
        }

        $totals['expected_margin_percent'] = $totals['customer_confirmed'] > 0
            ? ($totals['expected_margin'] / $totals['customer_confirmed']) * 100
            : null;

        return [
            'items' => $items,
            'totals' => $totals,
        ];
    }

    public function portfolioOverview(?Builder $projectScope = null): array
    {
        $documentTotals = $this->documentTotalsQuery();
        $unassignedLines = $this->unassignedLinesQuery();
        $workItemExceptions = $this->workItemExceptionsQuery();
        $review = $this->reviewSqlExpressions();
        $projectIds = clone ($projectScope ?? Project::query());
        $projectIds->select('projects.id')->reorder();

        $row = Project::query()
            ->joinSub($projectIds, 'project_scope', fn ($join) => $join->on('project_scope.id', '=', 'projects.id'))
            ->leftJoinSub($documentTotals, 'document_totals', fn ($join) => $join->on('document_totals.project_id', '=', 'projects.id'))
            ->leftJoinSub($unassignedLines, 'unassigned_lines', fn ($join) => $join->on('unassigned_lines.project_id', '=', 'projects.id'))
            ->leftJoinSub($workItemExceptions, 'work_item_exceptions', fn ($join) => $join->on('work_item_exceptions.project_id', '=', 'projects.id'))
            ->selectRaw('count(projects.id) as total')
            ->selectRaw("sum(case when projects.status = 'active' then 1 else 0 end) as active")
            ->selectRaw('coalesce(sum(projects.contract_value), 0) as contract_value')
            ->selectRaw('coalesce(sum(projects.budget_amount), 0) as budget_amount')
            ->selectRaw("coalesce(sum({$review['customer_confirmed']}), 0) as customer_confirmed")
            ->selectRaw("coalesce(sum({$review['customer_invoiced']}), 0) as customer_invoiced")
            ->selectRaw("coalesce(sum({$review['supplier_committed']}), 0) as supplier_committed")
            ->selectRaw("coalesce(sum({$review['supplier_invoiced']}), 0) as supplier_invoiced")
            ->selectRaw("coalesce(sum({$review['unassigned_line_count']}), 0) as unassigned_line_count")
            ->selectRaw("sum(case when {$review['needs_review']} then 1 else 0 end) as review_projects")
            ->first();

        $customerConfirmedTotal = (float) ($row->customer_confirmed ?? 0);
        $supplierCommittedTotal = (float) ($row->supplier_committed ?? 0);
        $expectedMargin = $customerConfirmedTotal - $supplierCommittedTotal;

        return [
            'total' => (int) ($row->total ?? 0),
            'active' => (int) ($row->active ?? 0),
            'contract_value' => (float) ($row->contract_value ?? 0),
            'budget_amount' => (float) ($row->budget_amount ?? 0),
            'customer_confirmed' => $customerConfirmedTotal,
            'customer_invoiced' => (float) ($row->customer_invoiced ?? 0),
            'supplier_committed' => $supplierCommittedTotal,
            'supplier_invoiced' => (float) ($row->supplier_invoiced ?? 0),
            'expected_margin' => $expectedMargin,
            'expected_margin_percent' => $customerConfirmedTotal > 0 ? ($expectedMargin / $customerConfirmedTotal) * 100 : null,
            'unassigned_line_count' => (int) ($row->unassigned_line_count ?? 0),
            'review_projects' => (int) ($row->review_projects ?? 0),
        ];
    }

    public function applyReviewFilter(Builder $query, ?string $reviewFilter): Builder
    {
        if (! array_key_exists((string) $reviewFilter, self::REVIEW_FILTERS)) {
            return $query;
        }

        $documentTotals = $this->documentTotalsQuery();
        $unassignedLines = $this->unassignedLinesQuery();
        $workItemExceptions = $this->workItemExceptionsQuery();
        $review = $this->reviewSqlExpressions();

        $query
            ->leftJoinSub($documentTotals, 'document_totals', fn ($join) => $join->on('document_totals.project_id', '=', 'projects.id'))
            ->leftJoinSub($unassignedLines, 'unassigned_lines', fn ($join) => $join->on('unassigned_lines.project_id', '=', 'projects.id'))
            ->leftJoinSub($workItemExceptions, 'work_item_exceptions', fn ($join) => $join->on('work_item_exceptions.project_id', '=', 'projects.id'));

        if ($reviewFilter === 'needs_review') {
            return $query->whereRaw("({$review['needs_review']})");
        }

        return $query->whereRaw("not ({$review['needs_review']})");
    }

    /**
     * @param  Collection<int, WbsItem>  $workItems
     */
    public function report(Project $project, Collection $workItems): array
    {
        $summary = $this->commercialSummary($project);
        $workItemReport = $this->workItemSummaries($project, $workItems);

        return [
            'summary' => $summary,
            'billing' => $this->billingProgress($project),
            'retention' => $this->retentionSummary($project),
            'work_items' => $workItemReport['items'],
            'totals' => $workItemReport['totals'],
            'unassigned' => $workItemReport['unassigned'],
            'exceptions' => $this->exceptions($project, $summary, $workItems, $workItemReport),
            'evidence' => $this->exceptionEvidence($project, $summary, $workItems, $workItemReport),
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

    private function billingProgress(Project $project): array
    {
        return [
            'customer' => $this->milestoneSummary($project, 'customer_po', 'customer_invoice'),
            'supplier' => $this->milestoneSummary($project, 'supplier_po', 'supplier_invoice'),
            'recent_invoices' => $this->recentMilestoneInvoices($project),
        ];
    }

    private function retentionSummary(Project $project): array
    {
        $retentionDocuments = Document::query()
            ->where('project_id', $project->id)
            ->whereIn('status', self::REPORT_STATUSES)
            ->whereIn('type', ['customer_invoice', 'supplier_invoice'])
            ->whereNotNull('retention_amount')
            ->where('retention_amount', '>', 0);

        $customerRetention = (clone $retentionDocuments)
            ->where('type', 'customer_invoice');
        $supplierRetention = (clone $retentionDocuments)
            ->where('type', 'supplier_invoice');

        $customerHeld = (float) (clone $customerRetention)->sum('retention_amount');
        $supplierHeld = (float) (clone $supplierRetention)->sum('retention_amount');

        return [
            'customer_retention_held' => $customerHeld,
            'supplier_retention_held' => $supplierHeld,
            'net_retention_exposure' => $customerHeld - $supplierHeld,
            'customer_release_date' => (clone $customerRetention)->min('retention_release_date'),
            'supplier_release_date' => (clone $supplierRetention)->min('retention_release_date'),
            'document_count' => (clone $retentionDocuments)->count(),
        ];
    }

    private function milestoneSummary(Project $project, string $scheduleType, string $invoiceType): array
    {
        $scheduleDocuments = Document::query()
            ->where('project_id', $project->id)
            ->where('type', $scheduleType)
            ->where('payment_terms_type', 'milestone')
            ->whereIn('status', self::REPORT_STATUSES);

        $invoiceDocuments = Document::query()
            ->where('project_id', $project->id)
            ->where('type', $invoiceType)
            ->where('payment_terms_type', 'milestone')
            ->whereIn('status', self::REPORT_STATUSES);

        $scheduleAggregate = (clone $scheduleDocuments)
            ->selectRaw('count(*) as document_count, coalesce(sum(total), 0) as total_amount')
            ->first();

        $invoiceAggregate = (clone $invoiceDocuments)
            ->selectRaw('count(*) as document_count, coalesce(sum(total), 0) as total_amount')
            ->first();

        $plannedStageCount = (int) DocumentBillingStage::query()
            ->join('documents', 'documents.id', '=', 'document_billing_stages.document_id')
            ->where('documents.project_id', $project->id)
            ->where('documents.type', $scheduleType)
            ->where('documents.payment_terms_type', 'milestone')
            ->whereIn('documents.status', self::REPORT_STATUSES)
            ->count();

        $latestInvoice = (clone $invoiceDocuments)
            ->with(['customer:id,name', 'supplier:id,name'])
            ->where(function ($query) {
                $query->whereNotNull('billing_stage_name')
                    ->orWhereNotNull('progress_invoice_number')
                    ->orWhereNotNull('progress_invoice_total');
            })
            ->latest('issue_date')
            ->latest('id')
            ->first([
                'id',
                'type',
                'document_number',
                'issue_date',
                'total',
                'customer_id',
                'supplier_id',
                'progress_invoice_number',
                'progress_invoice_total',
                'billing_stage_name',
            ]);

        $scheduledValue = (float) ($scheduleAggregate->total_amount ?? 0);
        $invoicedValue = (float) ($invoiceAggregate->total_amount ?? 0);

        return [
            'schedule_document_count' => (int) ($scheduleAggregate->document_count ?? 0),
            'planned_stage_count' => $plannedStageCount,
            'progress_invoice_count' => (int) ($invoiceAggregate->document_count ?? 0),
            'scheduled_value' => $scheduledValue,
            'invoiced_value' => $invoicedValue,
            'remaining_value' => max($scheduledValue - $invoicedValue, 0),
            'over_billed_value' => max($invoicedValue - $scheduledValue, 0),
            'latest_progress_label' => $this->progressLabel(
                $latestInvoice?->progress_invoice_number,
                $latestInvoice?->progress_invoice_total,
            ),
            'latest_stage_name' => $latestInvoice?->billing_stage_name ?: null,
            'latest_invoice_id' => $latestInvoice?->id,
            'latest_invoice_number' => $latestInvoice?->document_number,
            'latest_invoice_total' => $latestInvoice ? (float) $latestInvoice->total : 0.0,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentMilestoneInvoices(Project $project): array
    {
        return Document::query()
            ->where('project_id', $project->id)
            ->whereIn('type', ['customer_invoice', 'supplier_invoice'])
            ->where('payment_terms_type', 'milestone')
            ->whereIn('status', self::REPORT_STATUSES)
            ->with(['customer:id,name', 'supplier:id,name'])
            ->latest('issue_date')
            ->latest('id')
            ->limit(10)
            ->get([
                'id',
                'type',
                'document_number',
                'issue_date',
                'total',
                'customer_id',
                'supplier_id',
                'progress_invoice_number',
                'progress_invoice_total',
                'billing_stage_name',
            ])
            ->map(function (Document $document) {
                $meta = Document::metaForSlug(Document::slugForType($document->type));

                return [
                    'document_id' => (int) $document->id,
                    'document_number' => (string) $document->document_number,
                    'document_label' => (string) ($meta['singular'] ?? 'Invoice'),
                    'party_name' => (string) ($document->customer?->name ?? $document->supplier?->name ?? 'Internal'),
                    'issue_date' => $document->issue_date?->format('Y-m-d'),
                    'progress_label' => $this->progressLabel($document->progress_invoice_number, $document->progress_invoice_total),
                    'billing_stage_name' => $document->billing_stage_name ?: null,
                    'total' => (float) $document->total,
                ];
            })
            ->all();
    }

    private function progressLabel(mixed $number, mixed $total): ?string
    {
        if (! $number || ! $total) {
            return null;
        }

        return 'No. '.(int) $number.' of '.(int) $total;
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

    /**
     * @param  Collection<int, WbsItem>  $workItems
     */
    private function exceptionEvidence(Project $project, array $summary, Collection $workItems, array $workItemReport): array
    {
        $overCommittedWorkItems = $workItems->filter(function (WbsItem $workItem) use ($workItemReport) {
            return (bool) ($workItemReport['items'][$workItem->id]['over_committed'] ?? false);
        })->values();

        $overActualWorkItems = $workItems->filter(function (WbsItem $workItem) use ($workItemReport) {
            return (bool) ($workItemReport['items'][$workItem->id]['over_actual'] ?? false);
        })->values();

        return [
            'unassigned_lines' => $this->unassignedLineEvidence($project),
            'over_committed_work_items' => $this->groupedWorkItemEvidence(
                $project,
                $overCommittedWorkItems,
                $workItemReport,
                'supplier_po',
                fn (WbsItem $workItem, array $itemSummary) => [
                    'cost_budget' => (float) $workItem->cost_budget,
                    'supplier_committed' => (float) $itemSummary['supplier_committed'],
                    'over_amount' => abs((float) $itemSummary['remaining_budget']),
                ],
            ),
            'over_actual_work_items' => $this->groupedWorkItemEvidence(
                $project,
                $overActualWorkItems,
                $workItemReport,
                'supplier_invoice',
                fn (WbsItem $workItem, array $itemSummary) => [
                    'cost_budget' => (float) $workItem->cost_budget,
                    'supplier_actual' => (float) $itemSummary['supplier_actual'],
                    'over_amount' => abs((float) $itemSummary['actual_variance']),
                ],
            ),
            'supplier_actual_lines' => $this->supplierActualEvidence($project, $summary),
        ];
    }

    private function unassignedLineEvidence(Project $project): array
    {
        $lineCount = (int) $this->projectEvidenceLineQuery($project)
            ->reorder()
            ->whereNull('document_items.wbs_item_id')
            ->count();

        if ($lineCount === 0) {
            return [
                'line_count' => 0,
                'showing_count' => 0,
                'is_truncated' => false,
                'items' => [],
            ];
        }

        $items = $this->projectEvidenceLineQuery($project)
            ->whereNull('document_items.wbs_item_id')
            ->limit(self::EVIDENCE_LIMIT)
            ->get()
            ->map(fn (object $row) => $this->formatEvidenceLine($row))
            ->all();

        return [
            'line_count' => $lineCount,
            'showing_count' => count($items),
            'is_truncated' => $lineCount > count($items),
            'items' => $items,
        ];
    }

    /**
     * @param  Collection<int, WbsItem>  $workItems
     * @param  callable(WbsItem, array<string, mixed>): array<string, float>  $summaryBuilder
     * @return array<int, array<string, mixed>>
     */
    private function groupedWorkItemEvidence(
        Project $project,
        Collection $workItems,
        array $workItemReport,
        string $documentType,
        callable $summaryBuilder,
    ): array {
        if ($workItems->isEmpty()) {
            return [];
        }

        $workItemIds = $workItems->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $lineCounts = $this->workItemEvidenceLineCounts($project, $workItemIds, $documentType);
        $evidence = [];

        foreach ($workItems as $workItem) {
            $itemSummary = $workItemReport['items'][$workItem->id] ?? null;

            if (! is_array($itemSummary)) {
                continue;
            }

            $items = $this->projectEvidenceLineQuery($project)
                ->where('documents.type', $documentType)
                ->where('document_items.wbs_item_id', $workItem->id)
                ->limit(self::EVIDENCE_LIMIT)
                ->get()
                ->map(fn (object $row) => $this->formatEvidenceLine($row))
                ->all();

            $lineCount = (int) ($lineCounts[$workItem->id] ?? 0);

            $evidence[] = array_merge([
                'work_item_id' => (int) $workItem->id,
                'work_item_label' => $workItem->displayLabel(),
                'line_count' => $lineCount,
                'showing_count' => count($items),
                'is_truncated' => $lineCount > count($items),
                'items' => $items,
            ], $summaryBuilder($workItem, $itemSummary));
        }

        return $evidence;
    }

    private function supplierActualEvidence(Project $project, array $summary): array
    {
        if (($summary['supplier_committed'] ?? 0) <= 0 || ($summary['supplier_invoiced'] ?? 0) <= ($summary['supplier_committed'] ?? 0)) {
            return [
                'line_count' => 0,
                'showing_count' => 0,
                'is_truncated' => false,
                'over_amount' => 0.0,
                'supplier_committed' => (float) ($summary['supplier_committed'] ?? 0),
                'supplier_invoiced' => (float) ($summary['supplier_invoiced'] ?? 0),
                'items' => [],
            ];
        }

        $lineCount = (int) $this->projectEvidenceLineQuery($project)
            ->reorder()
            ->where('documents.type', 'supplier_invoice')
            ->count();

        $items = $this->projectEvidenceLineQuery($project)
            ->where('documents.type', 'supplier_invoice')
            ->limit(self::EVIDENCE_LIMIT)
            ->get()
            ->map(fn (object $row) => $this->formatEvidenceLine($row))
            ->all();

        return [
            'line_count' => $lineCount,
            'showing_count' => count($items),
            'is_truncated' => $lineCount > count($items),
            'over_amount' => (float) $summary['supplier_invoiced'] - (float) $summary['supplier_committed'],
            'supplier_committed' => (float) $summary['supplier_committed'],
            'supplier_invoiced' => (float) $summary['supplier_invoiced'],
            'items' => $items,
        ];
    }

    /**
     * @param  array<int>  $workItemIds
     * @return array<int, int>
     */
    private function workItemEvidenceLineCounts(Project $project, array $workItemIds, string $documentType): array
    {
        if ($workItemIds === []) {
            return [];
        }

        return $this->projectEvidenceLineQuery($project)
            ->reorder()
            ->where('documents.type', $documentType)
            ->whereIn('document_items.wbs_item_id', $workItemIds)
            ->select('document_items.wbs_item_id as wbs_item_id')
            ->selectRaw('count(*) as line_count')
            ->groupBy('document_items.wbs_item_id')
            ->pluck('line_count', 'wbs_item_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    private function projectEvidenceLineQuery(Project $project)
    {
        return DocumentItem::query()
            ->join('documents', 'documents.id', '=', 'document_items.document_id')
            ->leftJoin('customers', 'customers.id', '=', 'documents.customer_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'documents.supplier_id')
            ->leftJoin('wbs_items', 'wbs_items.id', '=', 'document_items.wbs_item_id')
            ->where('document_items.project_id', $project->id)
            ->whereIn('documents.status', self::REPORT_STATUSES)
            ->select([
                'document_items.id',
                'document_items.wbs_item_id',
                'document_items.description',
                'document_items.line_total',
                'documents.id as document_id',
                'documents.document_number',
                'documents.type as document_type',
                'documents.issue_date',
                'customers.name as customer_name',
                'suppliers.name as supplier_name',
                'wbs_items.code as work_item_code',
                'wbs_items.name as work_item_name',
            ])
            ->orderByDesc('documents.issue_date')
            ->orderByDesc('document_items.id');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatEvidenceLine(object $row): array
    {
        $slug = Document::slugForType((string) $row->document_type);
        $meta = Document::metaForSlug($slug);
        $workItemCode = $row->work_item_code ? trim((string) $row->work_item_code) : null;
        $workItemName = $row->work_item_name ? trim((string) $row->work_item_name) : null;

        return [
            'document_id' => (int) $row->document_id,
            'document_number' => (string) $row->document_number,
            'document_label' => (string) ($meta['singular'] ?? 'Document'),
            'issue_date' => $row->issue_date,
            'party_name' => (string) ($row->customer_name ?? $row->supplier_name ?? 'Internal'),
            'description' => filled($row->description) ? trim((string) $row->description) : 'No line description',
            'line_total' => (float) $row->line_total,
            'work_item_label' => $workItemCode && $workItemName ? $workItemCode.' - '.$workItemName : null,
        ];
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.').'%';
    }

    /**
     * @param  array<int>  $projectIds
     * @return array<int, array<string, float>>
     */
    private function documentTotalsByProject(array $projectIds): array
    {
        return $this->documentTotalsQuery()
            ->whereIn('project_id', $projectIds)
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->project_id => $this->normalizeDocumentTotals($row)])
            ->all();
    }

    private function documentTotalsQuery()
    {
        return Document::query()
            ->whereNotNull('project_id')
            ->whereIn('status', self::REPORT_STATUSES)
            ->select('project_id')
            ->selectRaw("sum(case when type = 'customer_quotation' then total else 0 end) as quoted_revenue")
            ->selectRaw("sum(case when type = 'customer_po' then total else 0 end) as customer_confirmed")
            ->selectRaw("sum(case when type = 'customer_invoice' then total else 0 end) as customer_invoiced")
            ->selectRaw("sum(case when type = 'supplier_po' then total else 0 end) as supplier_committed")
            ->selectRaw("sum(case when type = 'goods_receipt' then total else 0 end) as received_cost")
            ->selectRaw("sum(case when type = 'supplier_invoice' then total else 0 end) as supplier_invoiced")
            ->groupBy('project_id');
    }

    /**
     * @param  array<int>  $projectIds
     * @return array<int, int>
     */
    private function unassignedLineCountsByProject(array $projectIds): array
    {
        return $this->unassignedLinesQuery()
            ->whereIn('document_items.project_id', $projectIds)
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->project_id => (int) $row->line_count])
            ->all();
    }

    private function unassignedLinesQuery()
    {
        return DocumentItem::query()
            ->join('documents', 'documents.id', '=', 'document_items.document_id')
            ->whereNotNull('document_items.project_id')
            ->whereNull('document_items.wbs_item_id')
            ->whereIn('documents.status', self::REPORT_STATUSES)
            ->select('document_items.project_id')
            ->selectRaw('count(*) as line_count')
            ->groupBy('document_items.project_id');
    }

    /**
     * @param  array<int>  $projectIds
     * @return array<int, array{over_committed_count: int, over_actual_count: int}>
     */
    private function workItemExceptionCountsByProject(array $projectIds): array
    {
        return $this->workItemExceptionsQuery()
            ->whereIn('project_id', $projectIds)
            ->get()
            ->mapWithKeys(fn ($row) => [
                (int) $row->project_id => [
                    'over_committed_count' => (int) $row->over_committed_count,
                    'over_actual_count' => (int) $row->over_actual_count,
                ],
            ])
            ->all();
    }

    private function workItemExceptionsQuery()
    {
        $workItemTotals = DocumentItem::query()
            ->join('documents', 'documents.id', '=', 'document_items.document_id')
            ->join('wbs_items', 'wbs_items.id', '=', 'document_items.wbs_item_id')
            ->whereNotNull('document_items.project_id')
            ->whereNotNull('document_items.wbs_item_id')
            ->whereIn('documents.status', self::REPORT_STATUSES)
            ->select('document_items.project_id', 'document_items.wbs_item_id', 'wbs_items.cost_budget')
            ->selectRaw("sum(case when documents.type = 'supplier_po' then document_items.line_total else 0 end) as supplier_committed")
            ->selectRaw("sum(case when documents.type = 'supplier_invoice' then document_items.line_total else 0 end) as supplier_actual")
            ->groupBy('document_items.project_id', 'document_items.wbs_item_id', 'wbs_items.cost_budget');

        return DB::query()
            ->fromSub($workItemTotals, 'work_item_totals')
            ->select('project_id')
            ->selectRaw('sum(case when cost_budget > 0 and supplier_committed > cost_budget then 1 else 0 end) as over_committed_count')
            ->selectRaw('sum(case when cost_budget > 0 and supplier_actual > cost_budget then 1 else 0 end) as over_actual_count')
            ->groupBy('project_id');
    }

    /**
     * @return array<string, string>
     */
    private function reviewSqlExpressions(): array
    {
        $customerConfirmed = 'coalesce(document_totals.customer_confirmed, 0)';
        $customerInvoiced = 'coalesce(document_totals.customer_invoiced, 0)';
        $supplierCommitted = 'coalesce(document_totals.supplier_committed, 0)';
        $supplierInvoiced = 'coalesce(document_totals.supplier_invoiced, 0)';
        $unassignedLineCount = 'coalesce(unassigned_lines.line_count, 0)';
        $workItemOverCommitted = 'coalesce(work_item_exceptions.over_committed_count, 0)';
        $workItemOverActual = 'coalesce(work_item_exceptions.over_actual_count, 0)';
        $belowMargin = "projects.margin_target_percent > 0 and {$customerConfirmed} > 0 and ((({$customerConfirmed}) - ({$supplierCommitted})) * 100 / nullif({$customerConfirmed}, 0)) < projects.margin_target_percent";
        $overBudget = "projects.budget_amount > 0 and {$supplierCommitted} > projects.budget_amount";
        $actualAboveCommitted = "{$supplierCommitted} > 0 and {$supplierInvoiced} > {$supplierCommitted}";
        $needsReview = "({$belowMargin}) or ({$overBudget}) or ({$actualAboveCommitted}) or ({$unassignedLineCount} > 0) or ({$workItemOverCommitted} > 0) or ({$workItemOverActual} > 0)";

        return [
            'customer_confirmed' => $customerConfirmed,
            'customer_invoiced' => $customerInvoiced,
            'supplier_committed' => $supplierCommitted,
            'supplier_invoiced' => $supplierInvoiced,
            'unassigned_line_count' => $unassignedLineCount,
            'needs_review' => $needsReview,
        ];
    }

    /**
     * @param  array{over_committed_count: int, over_actual_count: int}  $workItemException
     * @return array<int, string>
     */
    private function portfolioReviewReasons(
        Project $project,
        ?float $expectedMarginPercent,
        float $budgetRemaining,
        float $supplierCommitted,
        float $supplierInvoiced,
        int $unassignedLineCount,
        array $workItemException,
    ): array {
        $reasons = [];
        $targetMargin = (float) $project->margin_target_percent;

        if ($targetMargin > 0 && $expectedMarginPercent !== null && $expectedMarginPercent < $targetMargin) {
            $reasons[] = 'Margin below target';
        }

        if ((float) $project->budget_amount > 0 && $budgetRemaining < 0) {
            $reasons[] = 'Budget overrun';
        }

        if ($unassignedLineCount > 0) {
            $reasons[] = 'Work item missing';
        }

        if (($workItemException['over_committed_count'] ?? 0) > 0) {
            $reasons[] = 'Work item budget overrun';
        }

        if (($workItemException['over_actual_count'] ?? 0) > 0) {
            $reasons[] = 'Actual cost over budget';
        }

        if ($supplierCommitted > 0 && $supplierInvoiced > $supplierCommitted) {
            $reasons[] = 'Supplier actual above committed';
        }

        return $reasons;
    }

    private function emptyPortfolioTotals(): array
    {
        return [
            'customer_confirmed' => 0.0,
            'customer_invoiced' => 0.0,
            'supplier_committed' => 0.0,
            'supplier_invoiced' => 0.0,
            'expected_margin' => 0.0,
            'expected_margin_percent' => null,
            'unassigned_line_count' => 0,
            'review_projects' => 0,
        ];
    }

    private function emptyDocumentTotals(): array
    {
        return [
            'quoted_revenue' => 0.0,
            'customer_confirmed' => 0.0,
            'customer_invoiced' => 0.0,
            'supplier_committed' => 0.0,
            'received_cost' => 0.0,
            'supplier_invoiced' => 0.0,
        ];
    }

    private function normalizeDocumentTotals(object $row): array
    {
        return [
            'quoted_revenue' => (float) ($row->quoted_revenue ?? 0),
            'customer_confirmed' => (float) ($row->customer_confirmed ?? 0),
            'customer_invoiced' => (float) ($row->customer_invoiced ?? 0),
            'supplier_committed' => (float) ($row->supplier_committed ?? 0),
            'received_cost' => (float) ($row->received_cost ?? 0),
            'supplier_invoiced' => (float) ($row->supplier_invoiced ?? 0),
        ];
    }
}
