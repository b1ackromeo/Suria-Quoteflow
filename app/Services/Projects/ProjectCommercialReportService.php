<?php

namespace App\Services\Projects;

use App\Models\CompanyProfile;
use App\Models\Document;
use App\Models\DocumentBillingStage;
use App\Models\DocumentItem;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectVariation;
use App\Models\WbsItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectCommercialReportService
{
    private const EVIDENCE_LIMIT = 25;

    private const DEFAULT_DELIVERY_GAP_ALERT_PERCENT = 25.0;

    private const DEFAULT_RECEIVED_NOT_INVOICED_ALERT_PERCENT = 10.0;

    private const DEFAULT_DELIVERY_GAP_ALERT_AMOUNT = 0.0;

    private const DEFAULT_RECEIVED_NOT_INVOICED_ALERT_AMOUNT = 0.0;

    private ?CompanyProfile $companyProfile = null;

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
        $billing = $this->billingProgress($project);
        $billing['delivery'] = $this->deliveryBilling($project, $workItems, $summary, $workItemReport);

        return [
            'summary' => $summary,
            'billing' => $billing,
            'retention' => $this->retentionSummary($project),
            'variations' => $this->variationSummary($project),
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

    private function variationSummary(Project $project): array
    {
        $variations = ProjectVariation::query()
            ->where('project_id', $project->id)
            ->with(['sourceDocument:id,type,document_number'])
            ->orderByRaw("case when status = 'approved' then 0 when status = 'pending_review' then 1 else 2 end")
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get();

        $approved = $variations->where('status', 'approved');
        $pending = $variations->where('status', 'pending_review');
        $notProceeding = $variations->where('status', 'not_proceeding');
        $approvedCustomerChange = (float) $approved->sum(fn (ProjectVariation $variation) => (float) $variation->customer_value);
        $approvedSupplierCostChange = (float) $approved->sum(fn (ProjectVariation $variation) => (float) $variation->supplier_cost);
        $pendingCustomerChange = (float) $pending->sum(fn (ProjectVariation $variation) => (float) $variation->customer_value);
        $pendingSupplierCostChange = (float) $pending->sum(fn (ProjectVariation $variation) => (float) $variation->supplier_cost);
        $revisedContractValue = (float) $project->contract_value + $approvedCustomerChange;
        $revisedBudgetAmount = (float) $project->budget_amount + $approvedSupplierCostChange;
        $approvedMarginBaseline = $revisedContractValue - $revisedBudgetAmount;

        return [
            'original_contract_value' => (float) $project->contract_value,
            'approved_customer_change' => $approvedCustomerChange,
            'pending_customer_change' => $pendingCustomerChange,
            'revised_contract_value' => $revisedContractValue,
            'original_budget_amount' => (float) $project->budget_amount,
            'approved_supplier_cost_change' => $approvedSupplierCostChange,
            'pending_supplier_cost_change' => $pendingSupplierCostChange,
            'revised_budget_amount' => $revisedBudgetAmount,
            'approved_margin_baseline' => $approvedMarginBaseline,
            'approved_margin_baseline_percent' => $revisedContractValue > 0 ? ($approvedMarginBaseline / $revisedContractValue) * 100 : null,
            'approved_count' => $approved->count(),
            'pending_count' => $pending->count(),
            'not_proceeding_count' => $notProceeding->count(),
            'total_count' => $variations->count(),
            'items' => $variations->map(function (ProjectVariation $variation) {
                return [
                    'id' => (int) $variation->id,
                    'variation_number' => (string) $variation->variation_number,
                    'title' => (string) $variation->title,
                    'status' => (string) $variation->status,
                    'status_label' => $variation->statusDisplay(),
                    'status_class' => $variation->statusChipClass(),
                    'effective_date' => $variation->effective_date?->format('Y-m-d'),
                    'customer_value' => (float) $variation->customer_value,
                    'supplier_cost' => (float) $variation->supplier_cost,
                    'margin_impact' => $variation->marginImpact(),
                    'source_document_id' => $variation->sourceDocument?->id,
                    'source_document_number' => $variation->sourceDocument?->document_number,
                    'source_document_label' => $variation->sourceDocument ? $this->documentLabel($variation->sourceDocument->type) : null,
                    'notes' => $variation->notes,
                ];
            })->all(),
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

    /**
     * @param  Collection<int, WbsItem>  $workItems
     */
    private function deliveryBilling(Project $project, Collection $workItems, array $summary, array $workItemReport): array
    {
        $customerConfirmed = (float) ($summary['customer_confirmed'] ?? 0);
        $customerInvoiced = (float) ($summary['customer_invoiced'] ?? 0);
        $supplierCommitted = (float) ($summary['supplier_committed'] ?? 0);
        $receivedCost = (float) ($summary['received_cost'] ?? 0);
        $supplierInvoiced = (float) ($summary['supplier_invoiced'] ?? 0);
        $alertLevels = $this->deliveryAlertLevels($project);

        $workItemRows = [];
        $workItemSummaries = $workItemReport['items'] ?? [];

        foreach ($workItems as $workItem) {
            $lineSummary = $workItemSummaries[$workItem->id] ?? [];

            if (! $this->hasDeliveryActivity($lineSummary)) {
                continue;
            }

            $workItemRows[] = array_merge(
                [
                    'work_item_id' => (int) $workItem->id,
                    'work_item_code' => (string) $workItem->code,
                    'work_item_name' => (string) $workItem->name,
                    'work_item_label' => $workItem->displayLabel(),
                    'line_count' => (int) ($lineSummary['line_count'] ?? 0),
                ],
                $this->deliveryGapSummary(
                    $lineSummary,
                    $this->deliveryAlertLevelsForWorkItem($workItem, $alertLevels),
                ),
            );
        }

        $unassigned = $workItemReport['unassigned'] ?? [];

        if ($this->hasDeliveryActivity($unassigned)) {
            $workItemRows[] = array_merge(
                [
                    'work_item_id' => null,
                    'work_item_code' => '',
                    'work_item_name' => 'Unassigned project lines',
                    'work_item_label' => 'Unassigned project lines',
                    'line_count' => (int) ($unassigned['line_count'] ?? 0),
                ],
                $this->deliveryGapSummary(
                    $unassigned,
                    $this->deliveryAlertLevelsForWorkItem(null, $alertLevels),
                ),
            );
        }

        return [
            'customer' => [
                'confirmed_value' => $customerConfirmed,
                'invoiced_value' => $customerInvoiced,
                'unbilled_value' => max($customerConfirmed - $customerInvoiced, 0),
                'above_confirmed_value' => max($customerInvoiced - $customerConfirmed, 0),
                'invoiced_percent' => $customerConfirmed > 0 ? ($customerInvoiced / $customerConfirmed) * 100 : null,
            ],
            'supplier' => [
                'committed_value' => $supplierCommitted,
                'received_value' => $receivedCost,
                'invoiced_value' => $supplierInvoiced,
                'not_yet_received_value' => max($supplierCommitted - $receivedCost, 0),
                'received_not_invoiced_value' => max($receivedCost - $supplierInvoiced, 0),
                'above_received_value' => max($supplierInvoiced - $receivedCost, 0),
                'received_percent' => $supplierCommitted > 0 ? ($receivedCost / $supplierCommitted) * 100 : null,
                'invoiced_percent' => $receivedCost > 0 ? ($supplierInvoiced / $receivedCost) * 100 : null,
            ],
            'work_items' => $workItemRows,
            'alert_levels' => $alertLevels,
            'alerts' => $this->deliveryBillingAlerts(
                $customerConfirmed,
                $customerInvoiced,
                $supplierCommitted,
                $receivedCost,
                $supplierInvoiced,
                $alertLevels,
            ),
            'evidence' => $this->deliveryBillingEvidence($project, $workItemRows),
            'recent_documents' => $this->recentDeliveryBillingDocuments($project),
        ];
    }

    private function deliveryGapSummary(array $summary, array $alertLevels): array
    {
        $customerConfirmed = (float) ($summary['customer_confirmed'] ?? 0);
        $customerInvoiced = (float) ($summary['customer_invoiced'] ?? 0);
        $supplierCommitted = (float) ($summary['supplier_committed'] ?? 0);
        $receivedCost = (float) ($summary['received_cost'] ?? 0);
        $supplierInvoiced = (float) ($summary['supplier_actual'] ?? $summary['supplier_invoiced'] ?? 0);
        $customerUnbilled = max($customerConfirmed - $customerInvoiced, 0);
        $customerAboveConfirmed = max($customerInvoiced - $customerConfirmed, 0);
        $supplierNotYetReceived = max($supplierCommitted - $receivedCost, 0);
        $receivedNotInvoiced = max($receivedCost - $supplierInvoiced, 0);
        $supplierAboveReceived = max($supplierInvoiced - $receivedCost, 0);
        $deliveryGapAlertPercent = (float) ($alertLevels['delivery_gap']['value'] ?? self::DEFAULT_DELIVERY_GAP_ALERT_PERCENT);
        $receivedNotInvoicedAlertPercent = (float) ($alertLevels['received_not_invoiced']['value'] ?? self::DEFAULT_RECEIVED_NOT_INVOICED_ALERT_PERCENT);
        $deliveryGapAlertAmount = (float) ($alertLevels['delivery_gap_amount']['value'] ?? self::DEFAULT_DELIVERY_GAP_ALERT_AMOUNT);
        $receivedNotInvoicedAlertAmount = (float) ($alertLevels['received_not_invoiced_amount']['value'] ?? self::DEFAULT_RECEIVED_NOT_INVOICED_ALERT_AMOUNT);
        $customerUnbilledPercent = $customerConfirmed > 0 ? ($customerUnbilled / $customerConfirmed) * 100 : null;
        $supplierNotYetReceivedPercent = $supplierCommitted > 0 ? ($supplierNotYetReceived / $supplierCommitted) * 100 : null;
        $receivedNotInvoicedPercent = $receivedCost > 0 ? ($receivedNotInvoiced / $receivedCost) * 100 : null;
        $attentionReasons = $this->deliveryGapAttentionReasons(
            $customerUnbilled,
            $customerUnbilledPercent,
            $customerAboveConfirmed,
            $supplierNotYetReceived,
            $supplierNotYetReceivedPercent,
            $receivedNotInvoiced,
            $receivedNotInvoicedPercent,
            $supplierAboveReceived,
            $deliveryGapAlertPercent,
            $receivedNotInvoicedAlertPercent,
            $deliveryGapAlertAmount,
            $receivedNotInvoicedAlertAmount,
        );

        return [
            'customer_confirmed' => $customerConfirmed,
            'customer_invoiced' => $customerInvoiced,
            'customer_unbilled_value' => $customerUnbilled,
            'customer_unbilled_percent' => $customerUnbilledPercent,
            'customer_unbilled_alert' => in_array('Customer unbilled above alert level', $attentionReasons, true),
            'customer_above_confirmed_value' => $customerAboveConfirmed,
            'supplier_committed' => $supplierCommitted,
            'received_value' => $receivedCost,
            'supplier_invoiced' => $supplierInvoiced,
            'supplier_not_yet_received_value' => $supplierNotYetReceived,
            'supplier_not_yet_received_percent' => $supplierNotYetReceivedPercent,
            'supplier_not_yet_received_alert' => in_array('Not yet received above alert level', $attentionReasons, true),
            'received_not_invoiced_value' => $receivedNotInvoiced,
            'received_not_invoiced_percent' => $receivedNotInvoicedPercent,
            'received_not_invoiced_alert' => in_array('Received not invoiced above alert level', $attentionReasons, true),
            'supplier_above_received_value' => $supplierAboveReceived,
            'alert_levels' => $alertLevels,
            'delivery_gap_alert_label' => $alertLevels['delivery_gap']['label'] ?? $this->formatPercent($deliveryGapAlertPercent),
            'received_not_invoiced_alert_label' => $alertLevels['received_not_invoiced']['label'] ?? $this->formatPercent($receivedNotInvoicedAlertPercent),
            'delivery_gap_alert_amount_label' => $alertLevels['delivery_gap_amount']['label'] ?? $this->formatAlertAmount($deliveryGapAlertAmount),
            'received_not_invoiced_alert_amount_label' => $alertLevels['received_not_invoiced_amount']['label'] ?? $this->formatAlertAmount($receivedNotInvoicedAlertAmount),
            'delivery_gap_alert_summary' => $this->deliveryThresholdLabel($deliveryGapAlertPercent, $deliveryGapAlertAmount),
            'received_not_invoiced_alert_summary' => $this->deliveryThresholdLabel($receivedNotInvoicedAlertPercent, $receivedNotInvoicedAlertAmount),
            'delivery_alert_source' => $this->deliveryAlertSourceLabel($alertLevels),
            'attention_reasons' => $attentionReasons,
            'attention_count' => count($attentionReasons),
            'attention_label' => $attentionReasons === [] ? 'Clear' : 'Review delivery billing',
            'attention_class' => $attentionReasons === [] ? 'status-approved' : 'status-pending_approval',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function deliveryGapAttentionReasons(
        float $customerUnbilled,
        ?float $customerUnbilledPercent,
        float $customerAboveConfirmed,
        float $supplierNotYetReceived,
        ?float $supplierNotYetReceivedPercent,
        float $receivedNotInvoiced,
        ?float $receivedNotInvoicedPercent,
        float $supplierAboveReceived,
        float $deliveryGapAlertPercent,
        float $receivedNotInvoicedAlertPercent,
        float $deliveryGapAlertAmount,
        float $receivedNotInvoicedAlertAmount,
    ): array {
        $reasons = [];

        if ($customerAboveConfirmed > 0) {
            $reasons[] = 'Customer invoice above confirmed value';
        }

        if ($supplierAboveReceived > 0) {
            $reasons[] = 'Supplier invoice above received value';
        }

        if ($this->deliveryMetricTriggersAlert($customerUnbilled, $customerUnbilledPercent, $deliveryGapAlertPercent, $deliveryGapAlertAmount)) {
            $reasons[] = 'Customer unbilled above alert level';
        }

        if ($this->deliveryMetricTriggersAlert($supplierNotYetReceived, $supplierNotYetReceivedPercent, $deliveryGapAlertPercent, $deliveryGapAlertAmount)) {
            $reasons[] = 'Not yet received above alert level';
        }

        if ($this->deliveryMetricTriggersAlert($receivedNotInvoiced, $receivedNotInvoicedPercent, $receivedNotInvoicedAlertPercent, $receivedNotInvoicedAlertAmount)) {
            $reasons[] = 'Received not invoiced above alert level';
        }

        return $reasons;
    }

    private function deliveryMetricTriggersAlert(
        float $amount,
        ?float $percent,
        float $percentThreshold,
        float $amountThreshold,
    ): bool {
        if ($amount <= 0) {
            return false;
        }

        return ($percent !== null && $percent >= $percentThreshold)
            || ($amountThreshold > 0 && $amount >= $amountThreshold);
    }

    private function hasDeliveryActivity(array $summary): bool
    {
        foreach (['customer_confirmed', 'customer_invoiced', 'supplier_committed', 'received_cost', 'supplier_actual', 'supplier_invoiced'] as $key) {
            if ((float) ($summary[$key] ?? 0) !== 0.0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function deliveryBillingAlerts(
        float $customerConfirmed,
        float $customerInvoiced,
        float $supplierCommitted,
        float $receivedCost,
        float $supplierInvoiced,
        array $alertLevels,
    ): array {
        $alerts = [];
        $deliveryGapAlertPercent = (float) ($alertLevels['delivery_gap']['value'] ?? self::DEFAULT_DELIVERY_GAP_ALERT_PERCENT);
        $receivedNotInvoicedAlertPercent = (float) ($alertLevels['received_not_invoiced']['value'] ?? self::DEFAULT_RECEIVED_NOT_INVOICED_ALERT_PERCENT);
        $deliveryGapAlertAmount = (float) ($alertLevels['delivery_gap_amount']['value'] ?? self::DEFAULT_DELIVERY_GAP_ALERT_AMOUNT);
        $receivedNotInvoicedAlertAmount = (float) ($alertLevels['received_not_invoiced_amount']['value'] ?? self::DEFAULT_RECEIVED_NOT_INVOICED_ALERT_AMOUNT);
        $customerUnbilled = max($customerConfirmed - $customerInvoiced, 0);
        $customerAboveConfirmed = max($customerInvoiced - $customerConfirmed, 0);
        $supplierNotYetReceived = max($supplierCommitted - $receivedCost, 0);
        $receivedNotInvoiced = max($receivedCost - $supplierInvoiced, 0);
        $supplierAboveReceived = max($supplierInvoiced - $receivedCost, 0);
        $customerUnbilledPercent = $customerConfirmed > 0 ? ($customerUnbilled / $customerConfirmed) * 100 : null;
        $supplierNotYetReceivedPercent = $supplierCommitted > 0 ? ($supplierNotYetReceived / $supplierCommitted) * 100 : null;
        $receivedNotInvoicedPercent = $receivedCost > 0 ? ($receivedNotInvoiced / $receivedCost) * 100 : null;

        if ($customerAboveConfirmed > 0) {
            $alerts[] = [
                'state' => 'blocked',
                'title' => 'Customer invoice above confirmed value',
                'message' => 'Customer invoice value is above customer PO received value. Review the customer invoice before relying on the project billing position.',
                'amount' => $customerAboveConfirmed,
                'percent' => $customerConfirmed > 0 ? ($customerAboveConfirmed / $customerConfirmed) * 100 : null,
                'threshold_label' => 'Any amount',
            ];
        }

        if ($supplierAboveReceived > 0) {
            $alerts[] = [
                'state' => 'blocked',
                'title' => 'Supplier invoice above received value',
                'message' => 'Supplier invoice value is above goods receipt value. Review the supplier invoice before recording further supplier payment.',
                'amount' => $supplierAboveReceived,
                'percent' => $receivedCost > 0 ? ($supplierAboveReceived / $receivedCost) * 100 : null,
                'threshold_label' => 'Any amount',
            ];
        }

        if ($this->deliveryMetricTriggersAlert($customerUnbilled, $customerUnbilledPercent, $deliveryGapAlertPercent, $deliveryGapAlertAmount)) {
            $alerts[] = [
                'state' => 'waiting',
                'title' => 'Customer billing attention',
                'message' => 'Customer unbilled value is '.$this->formatPercent($customerUnbilledPercent).' of customer PO received value.',
                'amount' => $customerUnbilled,
                'percent' => $customerUnbilledPercent,
                'threshold_label' => $this->deliveryThresholdLabel($deliveryGapAlertPercent, $deliveryGapAlertAmount),
            ];
        }

        if ($this->deliveryMetricTriggersAlert($supplierNotYetReceived, $supplierNotYetReceivedPercent, $deliveryGapAlertPercent, $deliveryGapAlertAmount)) {
            $alerts[] = [
                'state' => 'waiting',
                'title' => 'Supplier delivery attention',
                'message' => 'Purchase order value not yet received is '.$this->formatPercent($supplierNotYetReceivedPercent).' of supplier committed value.',
                'amount' => $supplierNotYetReceived,
                'percent' => $supplierNotYetReceivedPercent,
                'threshold_label' => $this->deliveryThresholdLabel($deliveryGapAlertPercent, $deliveryGapAlertAmount),
            ];
        }

        if ($this->deliveryMetricTriggersAlert($receivedNotInvoiced, $receivedNotInvoicedPercent, $receivedNotInvoicedAlertPercent, $receivedNotInvoicedAlertAmount)) {
            $alerts[] = [
                'state' => 'waiting',
                'title' => 'Supplier invoice expected',
                'message' => 'Received not invoiced value is '.$this->formatPercent($receivedNotInvoicedPercent).' of received / accepted value.',
                'amount' => $receivedNotInvoiced,
                'percent' => $receivedNotInvoicedPercent,
                'threshold_label' => $this->deliveryThresholdLabel($receivedNotInvoicedAlertPercent, $receivedNotInvoicedAlertAmount),
            ];
        }

        return $alerts;
    }

    /**
     * @return array<string, array{value: float, label: string, source: string}>
     */
    private function deliveryAlertLevels(Project $project): array
    {
        $company = $this->companyProfile();

        $deliveryGap = $this->resolvedAlertLevel(
            $project->delivery_gap_alert_percent,
            $company->deliveryGapAlertPercent(),
            self::DEFAULT_DELIVERY_GAP_ALERT_PERCENT,
        );
        $receivedNotInvoiced = $this->resolvedAlertLevel(
            $project->received_not_invoiced_alert_percent,
            $company->receivedNotInvoicedAlertPercent(),
            self::DEFAULT_RECEIVED_NOT_INVOICED_ALERT_PERCENT,
        );
        $deliveryGapAmount = $this->resolvedAlertLevel(
            $project->delivery_gap_alert_amount,
            $company->deliveryGapAlertAmount(),
            self::DEFAULT_DELIVERY_GAP_ALERT_AMOUNT,
        );
        $receivedNotInvoicedAmount = $this->resolvedAlertLevel(
            $project->received_not_invoiced_alert_amount,
            $company->receivedNotInvoicedAlertAmount(),
            self::DEFAULT_RECEIVED_NOT_INVOICED_ALERT_AMOUNT,
        );

        return [
            'delivery_gap' => [
                'value' => $deliveryGap['value'],
                'label' => $this->formatPercent($deliveryGap['value']),
                'source' => $deliveryGap['source'],
            ],
            'received_not_invoiced' => [
                'value' => $receivedNotInvoiced['value'],
                'label' => $this->formatPercent($receivedNotInvoiced['value']),
                'source' => $receivedNotInvoiced['source'],
            ],
            'delivery_gap_amount' => [
                'value' => $deliveryGapAmount['value'],
                'label' => $this->formatAlertAmount($deliveryGapAmount['value']),
                'source' => $deliveryGapAmount['source'],
            ],
            'received_not_invoiced_amount' => [
                'value' => $receivedNotInvoicedAmount['value'],
                'label' => $this->formatAlertAmount($receivedNotInvoicedAmount['value']),
                'source' => $receivedNotInvoicedAmount['source'],
            ],
        ];
    }

    /**
     * @param  array<string, array{value: float, label: string, source: string}>  $projectAlertLevels
     * @return array<string, array{value: float, label: string, source: string}>
     */
    private function deliveryAlertLevelsForWorkItem(?WbsItem $workItem, array $projectAlertLevels): array
    {
        if ($workItem === null) {
            return $projectAlertLevels;
        }

        return [
            'delivery_gap' => $this->resolvedWorkItemAlertLevel(
                $workItem->delivery_gap_alert_percent,
                $projectAlertLevels['delivery_gap'] ?? [],
                self::DEFAULT_DELIVERY_GAP_ALERT_PERCENT,
                'percent',
            ),
            'received_not_invoiced' => $this->resolvedWorkItemAlertLevel(
                $workItem->received_not_invoiced_alert_percent,
                $projectAlertLevels['received_not_invoiced'] ?? [],
                self::DEFAULT_RECEIVED_NOT_INVOICED_ALERT_PERCENT,
                'percent',
            ),
            'delivery_gap_amount' => $this->resolvedWorkItemAlertLevel(
                $workItem->delivery_gap_alert_amount,
                $projectAlertLevels['delivery_gap_amount'] ?? [],
                self::DEFAULT_DELIVERY_GAP_ALERT_AMOUNT,
                'amount',
            ),
            'received_not_invoiced_amount' => $this->resolvedWorkItemAlertLevel(
                $workItem->received_not_invoiced_alert_amount,
                $projectAlertLevels['received_not_invoiced_amount'] ?? [],
                self::DEFAULT_RECEIVED_NOT_INVOICED_ALERT_AMOUNT,
                'amount',
            ),
        ];
    }

    /**
     * @param  array{value?: float, label?: string, source?: string}  $baseLevel
     * @return array{value: float, label: string, source: string}
     */
    private function resolvedWorkItemAlertLevel(mixed $workItemValue, array $baseLevel, float $defaultValue, string $format): array
    {
        if ($workItemValue !== null && $workItemValue !== '') {
            $value = (float) $workItemValue;

            return [
                'value' => $value,
                'label' => $format === 'amount' ? $this->formatAlertAmount($value) : $this->formatPercent($value),
                'source' => 'Work item setting',
            ];
        }

        $value = (float) ($baseLevel['value'] ?? $defaultValue);

        return [
            'value' => $value,
            'label' => $baseLevel['label'] ?? ($format === 'amount' ? $this->formatAlertAmount($value) : $this->formatPercent($value)),
            'source' => $baseLevel['source'] ?? 'Company setting',
        ];
    }

    private function deliveryAlertSourceLabel(array $alertLevels): string
    {
        $sources = [];

        foreach (['delivery_gap', 'received_not_invoiced', 'delivery_gap_amount', 'received_not_invoiced_amount'] as $key) {
            if (str_ends_with($key, '_amount') && (float) ($alertLevels[$key]['value'] ?? 0) <= 0) {
                continue;
            }

            $source = (string) ($alertLevels[$key]['source'] ?? '');

            if ($source !== '' && ! in_array($source, $sources, true)) {
                $sources[] = $source;
            }
        }

        if (count($sources) === 1) {
            return $sources[0];
        }

        return $sources === [] ? 'Company setting' : 'Mixed settings';
    }

    /**
     * @return array{value: float, source: string}
     */
    private function resolvedAlertLevel(mixed $projectValue, mixed $companyValue, float $defaultValue): array
    {
        if ($projectValue !== null && $projectValue !== '') {
            return [
                'value' => (float) $projectValue,
                'source' => 'Project setting',
            ];
        }

        return [
            'value' => (float) ($companyValue ?? $defaultValue),
            'source' => 'Company setting',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $workItemRows
     * @return array<string, array<string, mixed>>
     */
    private function deliveryBillingEvidence(Project $project, array $workItemRows): array
    {
        $issues = [
            'customer_unbilled' => [
                'title' => 'Customer unbilled',
                'message' => 'Customer PO received lines are above customer invoice lines for these project work items.',
                'amount_key' => 'customer_unbilled_value',
                'document_types' => ['customer_po', 'customer_invoice'],
            ],
            'supplier_not_yet_received' => [
                'title' => 'Not yet received',
                'message' => 'Purchase order lines are above goods receipt lines for these project work items.',
                'amount_key' => 'supplier_not_yet_received_value',
                'document_types' => ['supplier_po', 'goods_receipt'],
            ],
            'received_not_invoiced' => [
                'title' => 'Received not invoiced',
                'message' => 'Goods receipt lines are above supplier invoice lines for these project work items.',
                'amount_key' => 'received_not_invoiced_value',
                'document_types' => ['goods_receipt', 'supplier_invoice'],
            ],
            'customer_above_confirmed' => [
                'title' => 'Above confirmed value',
                'message' => 'Customer invoice lines are above customer PO received lines for these project work items.',
                'amount_key' => 'customer_above_confirmed_value',
                'document_types' => ['customer_invoice', 'customer_po'],
            ],
            'supplier_above_received' => [
                'title' => 'Above received value',
                'message' => 'Supplier invoice lines are above goods receipt lines for these project work items.',
                'amount_key' => 'supplier_above_received_value',
                'document_types' => ['supplier_invoice', 'goods_receipt'],
            ],
        ];

        $evidence = [];

        foreach ($issues as $key => $issue) {
            $rows = collect($workItemRows)
                ->filter(fn (array $row) => (float) ($row[$issue['amount_key']] ?? 0) > 0)
                ->values();
            $amount = (float) $rows->sum(fn (array $row) => (float) ($row[$issue['amount_key']] ?? 0));
            $workItemIds = $rows
                ->pluck('work_item_id')
                ->filter(fn ($id) => $id !== null)
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();
            $includeUnassigned = $rows->contains(fn (array $row) => $row['work_item_id'] === null);

            if ($amount <= 0 || ($workItemIds === [] && ! $includeUnassigned)) {
                $evidence[$key] = [
                    'title' => $issue['title'],
                    'message' => $issue['message'],
                    'amount' => 0.0,
                    'line_count' => 0,
                    'showing_count' => 0,
                    'is_truncated' => false,
                    'items' => [],
                ];

                continue;
            }

            $lineCount = (int) $this->deliveryEvidenceLineQuery($project, $issue['document_types'], $workItemIds, $includeUnassigned)
                ->reorder()
                ->count();
            $items = $this->deliveryEvidenceLineQuery($project, $issue['document_types'], $workItemIds, $includeUnassigned)
                ->limit(self::EVIDENCE_LIMIT)
                ->get()
                ->map(fn (object $row) => $this->formatEvidenceLine($row))
                ->all();

            $evidence[$key] = [
                'title' => $issue['title'],
                'message' => $issue['message'],
                'amount' => $amount,
                'line_count' => $lineCount,
                'showing_count' => count($items),
                'is_truncated' => $lineCount > count($items),
                'items' => $items,
            ];
        }

        return $evidence;
    }

    /**
     * @param  array<int, string>  $documentTypes
     * @param  array<int, int>  $workItemIds
     */
    private function deliveryEvidenceLineQuery(Project $project, array $documentTypes, array $workItemIds, bool $includeUnassigned)
    {
        return $this->projectEvidenceLineQuery($project)
            ->whereIn('documents.type', $documentTypes)
            ->where(function ($query) use ($workItemIds, $includeUnassigned) {
                if ($workItemIds !== []) {
                    $query->whereIn('document_items.wbs_item_id', $workItemIds);
                }

                if ($includeUnassigned) {
                    $workItemIds === []
                        ? $query->whereNull('document_items.wbs_item_id')
                        : $query->orWhereNull('document_items.wbs_item_id');
                }
            });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentDeliveryBillingDocuments(Project $project): array
    {
        return Document::query()
            ->where('project_id', $project->id)
            ->whereIn('type', ['customer_po', 'customer_invoice', 'supplier_po', 'goods_receipt', 'supplier_invoice'])
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
            ])
            ->map(fn (Document $document) => [
                'document_id' => (int) $document->id,
                'document_number' => (string) $document->document_number,
                'document_label' => $this->documentLabel($document->type),
                'party_name' => (string) ($document->customer?->name ?? $document->supplier?->name ?? 'Internal'),
                'issue_date' => $document->issue_date?->format('Y-m-d'),
                'total' => (float) $document->total,
            ])
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

    private function documentLabel(?string $type): ?string
    {
        if (! $type) {
            return null;
        }

        $slug = Document::slugForType($type);
        $meta = Document::metaForSlug($slug);

        return (string) ($meta['singular'] ?? 'Document');
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.').'%';
    }

    private function formatAlertAmount(float $value): string
    {
        return $this->companyProfile()->formatMoney($value);
    }

    private function deliveryThresholdLabel(float $percentThreshold, float $amountThreshold): string
    {
        $parts = [$this->formatPercent($percentThreshold)];

        if ($amountThreshold > 0) {
            $parts[] = $this->formatAlertAmount($amountThreshold);
        }

        return implode(' or ', $parts);
    }

    private function companyProfile(): CompanyProfile
    {
        if ($this->companyProfile === null) {
            $this->companyProfile = CompanyProfile::active();
        }

        return $this->companyProfile;
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
