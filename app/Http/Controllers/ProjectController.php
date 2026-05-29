<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Models\WbsItem;
use App\Services\Projects\ProjectCommercialReportService;
use App\Support\Audit;
use App\Support\SearchFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request, ProjectCommercialReportService $projectCommercialReport): View
    {
        [$search, $status, $review] = $this->projectFilters($request);
        $projectScope = $this->projectScopeQuery($search, $status, $review, $projectCommercialReport);
        $projects = $this->projectListQuery(clone $projectScope)
            ->paginate(20)
            ->withQueryString();
        $portfolio = $projectCommercialReport->portfolio($projects->getCollection());

        return view('projects.index', [
            'projects' => $projects,
            'portfolioSummaries' => $portfolio['items'],
            'searchTerm' => $search,
            'statusFilter' => $status,
            'reviewFilter' => $review,
            'statuses' => Project::STATUSES,
            'reviewFilters' => ProjectCommercialReportService::REVIEW_FILTERS,
            'summary' => $projectCommercialReport->portfolioOverview($projectScope),
        ]);
    }

    public function exportCsv(Request $request, ProjectCommercialReportService $projectCommercialReport)
    {
        [$search, $status, $review] = $this->projectFilters($request);
        $query = $this->projectListQuery($this->projectScopeQuery($search, $status, $review, $projectCommercialReport));
        $filename = 'project-commercial-review-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query, $projectCommercialReport) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Project Code',
                'Project Name',
                'Status',
                'Customer',
                'Manager',
                'Target Date',
                'Linked Documents',
                'Customer Confirmed',
                'Customer Invoiced',
                'Supplier Committed',
                'Supplier Invoiced',
                'Expected Margin',
                'Expected Margin %',
                'Budget Remaining',
                'Unassigned Project Lines',
                'Review Status',
                'Review Reasons',
            ]);

            $query->chunk(200, function ($projects) use ($handle, $projectCommercialReport) {
                $portfolio = $projectCommercialReport->portfolio($projects)['items'];

                foreach ($projects as $project) {
                    $commercial = $portfolio[$project->id] ?? [
                        'customer_confirmed' => 0,
                        'customer_invoiced' => 0,
                        'supplier_committed' => 0,
                        'supplier_invoiced' => 0,
                        'expected_margin' => 0,
                        'expected_margin_percent' => null,
                        'budget_remaining' => (float) $project->budget_amount,
                        'unassigned_line_count' => 0,
                        'review_count' => 0,
                        'review_reasons' => [],
                    ];

                    fputcsv($handle, [
                        $project->project_code,
                        $project->name,
                        $project->statusDisplay(),
                        $project->customer?->name ?? '',
                        $project->manager?->name ?? '',
                        optional($project->expected_completion_date)->format('Y-m-d'),
                        $project->documents_count,
                        $this->csvAmount($commercial['customer_confirmed']),
                        $this->csvAmount($commercial['customer_invoiced']),
                        $this->csvAmount($commercial['supplier_committed']),
                        $this->csvAmount($commercial['supplier_invoiced']),
                        $this->csvAmount($commercial['expected_margin']),
                        $commercial['expected_margin_percent'] === null ? '' : $this->csvAmount($commercial['expected_margin_percent']),
                        $this->csvAmount($commercial['budget_remaining']),
                        $commercial['unassigned_line_count'],
                        $commercial['review_count'] > 0 ? 'Review needed' : 'No visible exceptions',
                        implode('; ', $commercial['review_reasons']),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function exportReportCsv(Project $project, ProjectCommercialReportService $projectCommercialReport)
    {
        $project->load(['customer', 'manager']);
        $workItems = $project->wbsItems()->with('parent')->get();
        $commercialReport = $projectCommercialReport->report($project, $workItems);
        $filename = 'project-commercial-report-'.$project->project_code.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($project, $workItems, $commercialReport) {
            $handle = fopen('php://output', 'w');
            $summary = $commercialReport['summary'];
            $billingProgress = $commercialReport['billing'];
            $deliveryBilling = $billingProgress['delivery'] ?? [
                'customer' => [],
                'supplier' => [],
                'work_items' => [],
                'alert_levels' => [],
                'recent_documents' => [],
            ];
            $retentionSummary = $commercialReport['retention'];
            $variationSummary = $commercialReport['variations'];
            $workItemSummaries = $commercialReport['work_items'];
            $workItemTotals = $commercialReport['totals'];
            $unassigned = $commercialReport['unassigned'];
            $exceptions = $commercialReport['exceptions'];
            $evidence = $commercialReport['evidence'];

            fputcsv($handle, ['Project commercial report']);
            fputcsv($handle, ['Project Code', $project->project_code]);
            fputcsv($handle, ['Project Name', $project->name]);
            fputcsv($handle, ['Status', $project->statusDisplay()]);
            fputcsv($handle, ['Customer', $project->customer?->name ?? '']);
            fputcsv($handle, ['Manager', $project->manager?->name ?? '']);
            fputcsv($handle, ['Target Date', optional($project->expected_completion_date)->format('Y-m-d')]);
            fputcsv($handle, []);

            fputcsv($handle, ['Commercial Summary']);
            fputcsv($handle, ['Metric', 'Value']);

            foreach ([
                'Quoted Revenue' => $summary['quoted_revenue'],
                'Customer Confirmed' => $summary['customer_confirmed'],
                'Customer Invoiced' => $summary['customer_invoiced'],
                'Customer Paid' => $summary['customer_paid'],
                'Estimated Cost' => $summary['estimated_cost'],
                'Supplier Committed' => $summary['supplier_committed'],
                'Received / Accepted' => $summary['received_cost'],
                'Supplier Invoiced' => $summary['supplier_invoiced'],
                'Supplier Paid' => $summary['supplier_paid'],
                'Expected Margin' => $summary['expected_margin'],
                'Actual Margin' => $summary['actual_margin'],
                'Unbilled Revenue' => $summary['unbilled_revenue'],
                'Unpaid Supplier Cost' => $summary['unpaid_supplier_cost'],
                'Budget Remaining' => $summary['budget_remaining'],
            ] as $label => $value) {
                fputcsv($handle, [$label, $this->csvAmount($value)]);
            }

            fputcsv($handle, ['Expected Margin %', $this->csvPercent($summary['expected_margin_percent'])]);
            fputcsv($handle, ['Actual Margin %', $this->csvPercent($summary['actual_margin_percent'])]);
            fputcsv($handle, ['Linked Documents', $summary['document_count']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Billing Progress']);
            fputcsv($handle, ['Scope', 'Milestone Schedules', 'Planned Stages', 'Progress Invoices', 'Scheduled Value', 'Invoiced Value', 'Remaining Staged Value', 'Above Scheduled Value', 'Latest Progress', 'Latest Billing Stage']);

            foreach ([
                'Customer billing' => $billingProgress['customer'] ?? [],
                'Supplier billing' => $billingProgress['supplier'] ?? [],
            ] as $label => $billing) {
                fputcsv($handle, [
                    $label,
                    (int) ($billing['schedule_document_count'] ?? 0),
                    (int) ($billing['planned_stage_count'] ?? 0),
                    (int) ($billing['progress_invoice_count'] ?? 0),
                    $this->csvAmount($billing['scheduled_value'] ?? 0),
                    $this->csvAmount($billing['invoiced_value'] ?? 0),
                    $this->csvAmount($billing['remaining_value'] ?? 0),
                    $this->csvAmount($billing['over_billed_value'] ?? 0),
                    $billing['latest_progress_label'] ?? '',
                    $billing['latest_stage_name'] ?? '',
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Recent Staged Invoices']);
            fputcsv($handle, ['Document Number', 'Document Type', 'Party', 'Issue Date', 'Progress Invoice', 'Billing Stage', 'Amount']);

            if (($billingProgress['recent_invoices'] ?? []) === []) {
                fputcsv($handle, ['No staged invoices recorded', '', '', '', '', 'No milestone customer or supplier invoices are linked to this project yet.', '']);
            } else {
                foreach ($billingProgress['recent_invoices'] as $invoice) {
                    fputcsv($handle, [
                        $invoice['document_number'] ?? '',
                        $invoice['document_label'] ?? '',
                        $invoice['party_name'] ?? '',
                        $invoice['issue_date'] ?? '',
                        $invoice['progress_label'] ?? '',
                        $invoice['billing_stage_name'] ?? '',
                        $this->csvAmount($invoice['total'] ?? 0),
                    ]);
                }
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Partial Delivery Billing']);
            fputcsv($handle, ['Metric', 'Value']);

            $deliveryCustomer = $deliveryBilling['customer'] ?? [];
            $deliverySupplier = $deliveryBilling['supplier'] ?? [];

            foreach ([
                'Customer Confirmed' => $deliveryCustomer['confirmed_value'] ?? 0,
                'Customer Invoiced' => $deliveryCustomer['invoiced_value'] ?? 0,
                'Customer Unbilled' => $deliveryCustomer['unbilled_value'] ?? 0,
                'Above Confirmed Value' => $deliveryCustomer['above_confirmed_value'] ?? 0,
            ] as $label => $value) {
                fputcsv($handle, [$label, $this->csvAmount($value)]);
            }

            fputcsv($handle, ['Customer Invoiced %', $this->csvPercent($deliveryCustomer['invoiced_percent'] ?? null)]);

            foreach ([
                'Purchase Order Committed' => $deliverySupplier['committed_value'] ?? 0,
                'Received / Accepted' => $deliverySupplier['received_value'] ?? 0,
                'Supplier Invoiced' => $deliverySupplier['invoiced_value'] ?? 0,
                'Not Yet Received' => $deliverySupplier['not_yet_received_value'] ?? 0,
                'Received Not Invoiced' => $deliverySupplier['received_not_invoiced_value'] ?? 0,
                'Above Received Value' => $deliverySupplier['above_received_value'] ?? 0,
            ] as $label => $value) {
                fputcsv($handle, [$label, $this->csvAmount($value)]);
            }

            fputcsv($handle, ['Received %', $this->csvPercent($deliverySupplier['received_percent'] ?? null)]);
            fputcsv($handle, ['Supplier Invoiced %', $this->csvPercent($deliverySupplier['invoiced_percent'] ?? null)]);

            $deliveryAlertLevels = $deliveryBilling['alert_levels'] ?? [];

            fputcsv($handle, []);
            fputcsv($handle, ['Delivery Billing Alert Levels']);
            fputcsv($handle, ['Alert Level', 'Value', 'Source']);
            fputcsv($handle, [
                'Customer unbilled / not yet received',
                $deliveryAlertLevels['delivery_gap']['label'] ?? '',
                $deliveryAlertLevels['delivery_gap']['source'] ?? '',
            ]);
            fputcsv($handle, [
                'Received not invoiced',
                $deliveryAlertLevels['received_not_invoiced']['label'] ?? '',
                $deliveryAlertLevels['received_not_invoiced']['source'] ?? '',
            ]);

            fputcsv($handle, []);
            fputcsv($handle, ['Delivery Billing Alerts']);
            fputcsv($handle, ['Alert', 'Message', 'Amount', 'Alert Level %', 'Threshold']);

            if (($deliveryBilling['alerts'] ?? []) === []) {
                fputcsv($handle, ['No delivery billing alerts visible', 'Delivery billing gaps are below the current alert levels.', '', '', '']);
            } else {
                foreach ($deliveryBilling['alerts'] as $alert) {
                    fputcsv($handle, [
                        $alert['title'] ?? '',
                        $alert['message'] ?? '',
                        $this->csvAmount($alert['amount'] ?? 0),
                        $this->csvPercent($alert['percent'] ?? null),
                        $alert['threshold_label'] ?? '',
                    ]);
                }
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Delivery Billing By Work Item']);
            fputcsv($handle, [
                'Work item / cost code',
                'Line Count',
                'Delivery Gap Alert Level',
                'Received Not Invoiced Alert Level',
                'Alert Source',
                'Delivery Billing Status',
                'Customer Confirmed',
                'Customer Invoiced',
                'Customer Unbilled',
                'Supplier Committed',
                'Received / Accepted',
                'Supplier Invoiced',
                'Not Yet Received',
                'Received Not Invoiced',
                'Above Confirmed',
                'Above Received',
            ]);

            if (($deliveryBilling['work_items'] ?? []) === []) {
                fputcsv($handle, ['No partial delivery billing yet', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'No customer PO received, customer invoice, purchase order, goods receipt, or supplier invoice lines are linked to project work items yet.']);
            } else {
                foreach ($deliveryBilling['work_items'] as $item) {
                    fputcsv($handle, [
                        $item['work_item_label'] ?? '',
                        (int) ($item['line_count'] ?? 0),
                        $item['delivery_gap_alert_label'] ?? '',
                        $item['received_not_invoiced_alert_label'] ?? '',
                        $item['delivery_alert_source'] ?? '',
                        $item['attention_label'] ?? '',
                        $this->csvAmount($item['customer_confirmed'] ?? 0),
                        $this->csvAmount($item['customer_invoiced'] ?? 0),
                        $this->csvAmount($item['customer_unbilled_value'] ?? 0),
                        $this->csvAmount($item['supplier_committed'] ?? 0),
                        $this->csvAmount($item['received_value'] ?? 0),
                        $this->csvAmount($item['supplier_invoiced'] ?? 0),
                        $this->csvAmount($item['supplier_not_yet_received_value'] ?? 0),
                        $this->csvAmount($item['received_not_invoiced_value'] ?? 0),
                        $this->csvAmount($item['customer_above_confirmed_value'] ?? 0),
                        $this->csvAmount($item['supplier_above_received_value'] ?? 0),
                    ]);
                }
            }

            $deliveryEvidence = collect($deliveryBilling['evidence'] ?? [])
                ->filter(fn ($issue) => (int) ($issue['line_count'] ?? 0) > 0)
                ->values();

            fputcsv($handle, []);
            fputcsv($handle, ['Delivery Billing Evidence']);
            fputcsv($handle, ['Issue', 'Gap Amount', 'Work item / cost code', 'Document Number', 'Document Type', 'Issue Date', 'Party', 'Line Description', 'Amount']);

            if ($deliveryEvidence->isEmpty()) {
                fputcsv($handle, ['No delivery billing evidence visible', '', '', '', '', '', '', 'No document lines are currently listed for the visible delivery billing gaps.', '']);
            } else {
                foreach ($deliveryEvidence as $issue) {
                    foreach ($issue['items'] ?? [] as $item) {
                        fputcsv($handle, [
                            $issue['title'] ?? '',
                            $this->csvAmount($issue['amount'] ?? 0),
                            $item['work_item_label'] ?? '',
                            $item['document_number'] ?? '',
                            $item['document_label'] ?? '',
                            $item['issue_date'] ?? '',
                            $item['party_name'] ?? '',
                            $item['description'] ?? '',
                            $this->csvAmount($item['line_total'] ?? 0),
                        ]);
                    }
                }
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Recent Delivery Documents']);
            fputcsv($handle, ['Document Number', 'Document Type', 'Party', 'Issue Date', 'Amount']);

            if (($deliveryBilling['recent_documents'] ?? []) === []) {
                fputcsv($handle, ['No delivery documents recorded', '', '', 'No customer PO received, customer invoice, purchase order, goods receipt, or supplier invoice is linked to this project yet.', '']);
            } else {
                foreach ($deliveryBilling['recent_documents'] as $document) {
                    fputcsv($handle, [
                        $document['document_number'] ?? '',
                        $document['document_label'] ?? '',
                        $document['party_name'] ?? '',
                        $document['issue_date'] ?? '',
                        $this->csvAmount($document['total'] ?? 0),
                    ]);
                }
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Retention Summary']);
            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Customer Retention Held', $this->csvAmount($retentionSummary['customer_retention_held'] ?? 0)]);
            fputcsv($handle, ['Supplier Retention Held', $this->csvAmount($retentionSummary['supplier_retention_held'] ?? 0)]);
            fputcsv($handle, ['Net Retention Exposure', $this->csvAmount($retentionSummary['net_retention_exposure'] ?? 0)]);
            fputcsv($handle, ['Customer Retention Release', $retentionSummary['customer_release_date'] ?? '']);
            fputcsv($handle, ['Supplier Retention Release', $retentionSummary['supplier_release_date'] ?? '']);
            fputcsv($handle, ['Documents With Retention', (int) ($retentionSummary['document_count'] ?? 0)]);

            fputcsv($handle, []);
            fputcsv($handle, ['Variation Orders']);
            fputcsv($handle, ['Metric', 'Value']);
            fputcsv($handle, ['Original Contract Value', $this->csvAmount($variationSummary['original_contract_value'] ?? $project->contract_value)]);
            fputcsv($handle, ['Approved Customer Change', $this->csvAmount($variationSummary['approved_customer_change'] ?? 0)]);
            fputcsv($handle, ['Pending Customer Change', $this->csvAmount($variationSummary['pending_customer_change'] ?? 0)]);
            fputcsv($handle, ['Revised Contract Value', $this->csvAmount($variationSummary['revised_contract_value'] ?? $project->contract_value)]);
            fputcsv($handle, ['Original Budget Amount', $this->csvAmount($variationSummary['original_budget_amount'] ?? $project->budget_amount)]);
            fputcsv($handle, ['Approved Supplier Cost Change', $this->csvAmount($variationSummary['approved_supplier_cost_change'] ?? 0)]);
            fputcsv($handle, ['Pending Supplier Cost Change', $this->csvAmount($variationSummary['pending_supplier_cost_change'] ?? 0)]);
            fputcsv($handle, ['Revised Budget Amount', $this->csvAmount($variationSummary['revised_budget_amount'] ?? $project->budget_amount)]);
            fputcsv($handle, ['Approved Margin Baseline', $this->csvAmount($variationSummary['approved_margin_baseline'] ?? 0)]);
            fputcsv($handle, ['Approved Margin Baseline %', $this->csvPercent($variationSummary['approved_margin_baseline_percent'] ?? null)]);
            fputcsv($handle, ['Approved Variation Orders', (int) ($variationSummary['approved_count'] ?? 0)]);
            fputcsv($handle, ['Pending Variation Orders', (int) ($variationSummary['pending_count'] ?? 0)]);
            fputcsv($handle, ['Not Proceeding Variation Orders', (int) ($variationSummary['not_proceeding_count'] ?? 0)]);

            fputcsv($handle, []);
            fputcsv($handle, ['Variation Order Register']);
            fputcsv($handle, ['Variation Number', 'Title', 'Status', 'Effective Date', 'Customer Value Change', 'Supplier Cost Change', 'Margin Impact', 'Linked Document Number', 'Linked Document Type', 'Notes']);

            if (($variationSummary['items'] ?? []) === []) {
                fputcsv($handle, ['No variation orders recorded', '', '', '', '', '', '', '', '', 'Add approved or pending scope changes here when the original project baseline is no longer enough.']);
            } else {
                foreach ($variationSummary['items'] as $variation) {
                    fputcsv($handle, [
                        $variation['variation_number'] ?? '',
                        $variation['title'] ?? '',
                        $variation['status_label'] ?? '',
                        $variation['effective_date'] ?? '',
                        $this->csvAmount($variation['customer_value'] ?? 0),
                        $this->csvAmount($variation['supplier_cost'] ?? 0),
                        $this->csvAmount($variation['margin_impact'] ?? 0),
                        $variation['source_document_number'] ?? '',
                        $variation['source_document_label'] ?? '',
                        $variation['notes'] ?? '',
                    ]);
                }
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Project Exceptions']);
            fputcsv($handle, ['Title', 'Message', 'Amount']);

            if ($exceptions === []) {
                fputcsv($handle, ['No project exceptions visible', 'Budget, margin, actual cost, and work-item assignment checks have no visible blockers.', '']);
            } else {
                foreach ($exceptions as $exception) {
                    fputcsv($handle, [
                        $exception['title'],
                        $exception['message'],
                        array_key_exists('amount', $exception) ? $this->csvAmount($exception['amount']) : '',
                    ]);
                }
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Review Evidence']);
            fputcsv($handle, ['Issue', 'Work item / cost code', 'Document Number', 'Document Type', 'Issue Date', 'Party', 'Line Description', 'Amount']);

            if (! $this->hasProjectEvidence($evidence)) {
                fputcsv($handle, ['No review evidence visible', '', '', '', '', '', 'No document lines are currently listed for the visible project exceptions.', '']);
            } else {
                $unassignedEvidence = $evidence['unassigned_lines'] ?? ['line_count' => 0, 'items' => []];

                if (($unassignedEvidence['line_count'] ?? 0) > 0) {
                    $this->writeEvidenceRows(
                        $handle,
                        'Work item missing',
                        null,
                        $unassignedEvidence['items'] ?? [],
                        (int) ($unassignedEvidence['line_count'] ?? 0),
                    );
                }

                foreach ($evidence['over_committed_work_items'] ?? [] as $group) {
                    $this->writeEvidenceRows(
                        $handle,
                        'Budget overrun',
                        $group['work_item_label'] ?? null,
                        $group['items'] ?? [],
                        (int) ($group['line_count'] ?? 0),
                    );
                }

                foreach ($evidence['over_actual_work_items'] ?? [] as $group) {
                    $this->writeEvidenceRows(
                        $handle,
                        'Actual cost over budget',
                        $group['work_item_label'] ?? null,
                        $group['items'] ?? [],
                        (int) ($group['line_count'] ?? 0),
                    );
                }

                $supplierActualEvidence = $evidence['supplier_actual_lines'] ?? ['line_count' => 0, 'items' => []];

                if (($supplierActualEvidence['line_count'] ?? 0) > 0) {
                    $this->writeEvidenceRows(
                        $handle,
                        'Supplier actual above committed',
                        null,
                        $supplierActualEvidence['items'] ?? [],
                        (int) ($supplierActualEvidence['line_count'] ?? 0),
                    );
                }
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Work Breakdown']);
            fputcsv($handle, [
                'Cost Code',
                'Work Item',
                'Status',
                'Cost Type',
                'Line Count',
                'Revenue Budget',
                'Cost Budget',
                'Quoted Revenue',
                'Customer Confirmed',
                'Customer Invoiced',
                'Supplier Committed',
                'Received / Accepted',
                'Supplier Actual',
                'Remaining Budget',
                'Actual Variance',
            ]);

            foreach ($workItems as $workItem) {
                $itemSummary = $workItemSummaries[$workItem->id] ?? [
                    'line_count' => 0,
                    'quoted_revenue' => 0,
                    'customer_confirmed' => 0,
                    'customer_invoiced' => 0,
                    'supplier_committed' => 0,
                    'received_cost' => 0,
                    'supplier_actual' => 0,
                    'remaining_budget' => (float) $workItem->cost_budget,
                    'actual_variance' => (float) $workItem->cost_budget,
                ];

                fputcsv($handle, [
                    $workItem->code,
                    $workItem->name,
                    $workItem->statusDisplay(),
                    $workItem->costTypeDisplay(),
                    $itemSummary['line_count'],
                    $this->csvAmount($workItem->revenue_budget),
                    $this->csvAmount($workItem->cost_budget),
                    $this->csvAmount($itemSummary['quoted_revenue']),
                    $this->csvAmount($itemSummary['customer_confirmed']),
                    $this->csvAmount($itemSummary['customer_invoiced']),
                    $this->csvAmount($itemSummary['supplier_committed']),
                    $this->csvAmount($itemSummary['received_cost']),
                    $this->csvAmount($itemSummary['supplier_actual']),
                    $this->csvAmount($itemSummary['remaining_budget']),
                    $this->csvAmount($itemSummary['actual_variance']),
                ]);
            }

            fputcsv($handle, [
                '',
                'Unassigned project lines',
                '',
                '',
                $unassigned['line_count'],
                '',
                '',
                $this->csvAmount($unassigned['quoted_revenue']),
                $this->csvAmount($unassigned['customer_confirmed']),
                $this->csvAmount($unassigned['customer_invoiced']),
                $this->csvAmount($unassigned['supplier_committed']),
                $this->csvAmount($unassigned['received_cost']),
                $this->csvAmount($unassigned['supplier_actual']),
                '',
                '',
            ]);

            fputcsv($handle, [
                '',
                'Work breakdown totals',
                '',
                '',
                $workItemTotals['line_count'],
                $this->csvAmount($workItemTotals['revenue_budget']),
                $this->csvAmount($workItemTotals['cost_budget']),
                $this->csvAmount($workItemTotals['quoted_revenue']),
                $this->csvAmount($workItemTotals['customer_confirmed']),
                $this->csvAmount($workItemTotals['customer_invoiced']),
                $this->csvAmount($workItemTotals['supplier_committed']),
                $this->csvAmount($workItemTotals['received_cost']),
                $this->csvAmount($workItemTotals['supplier_actual']),
                $this->csvAmount($workItemTotals['remaining_budget']),
                $this->csvAmount($workItemTotals['actual_variance']),
            ]);

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function create(): View
    {
        $this->ensureProjectManagementAccess();

        return view('projects.form', $this->formData(new Project([
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'margin_target_percent' => 20,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureProjectManagementAccess();

        $project = Project::create($this->validated($request));
        Audit::record('project_created', $project, null, $project->toArray());

        return redirect()->route('projects.show', $project)->with('status', 'Project created.');
    }

    public function show(Project $project, ProjectCommercialReportService $projectCommercialReport): View
    {
        $project->load(['customer', 'manager']);
        $workItems = $project->wbsItems()->with('parent')->get();
        $commercialReport = $projectCommercialReport->report($project, $workItems);

        return view('projects.show', [
            'project' => $project,
            'summary' => $commercialReport['summary'],
            'billingProgress' => $commercialReport['billing'],
            'retentionSummary' => $commercialReport['retention'],
            'variationSummary' => $commercialReport['variations'],
            'workItems' => $workItems,
            'workItemSummaries' => $commercialReport['work_items'],
            'workItemTotals' => $commercialReport['totals'],
            'unassignedWorkItemSummary' => $commercialReport['unassigned'],
            'projectExceptions' => $commercialReport['exceptions'],
            'projectEvidence' => $commercialReport['evidence'],
            'workItemStatuses' => WbsItem::STATUSES,
            'workItemCostTypes' => WbsItem::COST_TYPES,
            'sourceDocuments' => $project->documents()
                ->whereIn('type', ['customer_quotation', 'customer_po', 'supplier_quotation', 'supplier_po'])
                ->latest('issue_date')
                ->latest('id')
                ->get(['id', 'type', 'document_number']),
            'documents' => $project->documents()
                ->with(['customer', 'supplier'])
                ->latest('issue_date')
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),
            'canManageProject' => request()->user()->hasRole('admin', 'manager'),
        ]);
    }

    public function edit(Project $project): View
    {
        $this->ensureProjectManagementAccess();

        return view('projects.form', $this->formData($project));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->ensureProjectManagementAccess();

        $before = $project->toArray();
        $project->update($this->validated($request, $project->id));
        Audit::record('project_updated', $project, $before, $project->fresh()->toArray());

        return redirect()->route('projects.show', $project)->with('status', 'Project updated.');
    }

    private function formData(Project $project): array
    {
        return [
            'project' => $project,
            'companyProfile' => CompanyProfile::active(),
            'statuses' => Project::STATUSES,
            'customers' => Customer::query()->where('is_active', true)->orderBy('name')->get(),
            'managers' => User::query()
                ->whereIn('role', ['admin', 'manager'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ];
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'project_code' => ['required', 'string', 'max:80', 'unique:projects,project_code,'.($ignoreId ?? 'NULL').',id'],
            'name' => ['required', 'string', 'max:255'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'manager_id' => ['nullable', 'exists:users,id'],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'start_date' => ['nullable', 'date'],
            'expected_completion_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'contract_value' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'budget_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'margin_target_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'delivery_gap_alert_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'received_not_invoiced_alert_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        foreach (['contract_value', 'budget_amount', 'margin_target_percent'] as $field) {
            $data[$field] = (float) ($data[$field] ?? 0);
        }

        foreach (['delivery_gap_alert_percent', 'received_not_invoiced_alert_percent'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? (float) $data[$field] : null;
        }

        return $data;
    }

    private function ensureProjectManagementAccess(): void
    {
        abort_unless(request()->user()->hasRole('admin', 'manager'), 403);
    }

    private function projectFilters(Request $request): array
    {
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();
        $review = $request->string('review')->toString();

        return [
            $search,
            array_key_exists($status, Project::STATUSES) ? $status : null,
            array_key_exists($review, ProjectCommercialReportService::REVIEW_FILTERS) ? $review : null,
        ];
    }

    private function projectScopeQuery(?string $search, ?string $status, ?string $review, ProjectCommercialReportService $projectCommercialReport): Builder
    {
        $query = Project::query()
            ->when($search, fn ($query, $term) => SearchFilters::projects($query, $term))
            ->when($status, fn ($query, $status) => $query->where('projects.status', $status));

        $projectCommercialReport->applyReviewFilter($query, $review);

        return $query;
    }

    private function projectListQuery(Builder $query): Builder
    {
        return $query
            ->select('projects.*')
            ->with(['customer', 'manager'])
            ->withCount('documents')
            ->orderByRaw("case when projects.status = 'active' then 0 when projects.status = 'on_hold' then 1 when projects.status = 'completed' then 2 else 3 end")
            ->orderBy('projects.expected_completion_date')
            ->orderBy('projects.project_code');
    }

    private function csvAmount(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function csvPercent(mixed $value): string
    {
        return $value === null ? '' : $this->csvAmount($value);
    }

    private function hasProjectEvidence(array $evidence): bool
    {
        return (int) ($evidence['unassigned_lines']['line_count'] ?? 0) > 0
            || ! empty($evidence['over_committed_work_items'] ?? [])
            || ! empty($evidence['over_actual_work_items'] ?? [])
            || (int) ($evidence['supplier_actual_lines']['line_count'] ?? 0) > 0;
    }

    /**
     * @param  resource  $handle
     * @param  array<int, array<string, mixed>>  $items
     */
    private function writeEvidenceRows($handle, string $issue, ?string $workItemLabel, array $items, int $lineCount): void
    {
        foreach ($items as $item) {
            fputcsv($handle, [
                $issue,
                $workItemLabel ?: (string) ($item['work_item_label'] ?? ''),
                $item['document_number'] ?? '',
                $item['document_label'] ?? '',
                $item['issue_date'] ?? '',
                $item['party_name'] ?? '',
                $item['description'] ?? '',
                $this->csvAmount($item['line_total'] ?? 0),
            ]);
        }

        if ($lineCount > count($items)) {
            fputcsv($handle, [
                'Note',
                $workItemLabel ?: '',
                '',
                '',
                '',
                '',
                'Showing first '.count($items).' of '.$lineCount.' lines in this export.',
                '',
            ]);
        }
    }
}
