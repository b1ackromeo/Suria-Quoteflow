<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Models\WbsItem;
use App\Support\Audit;
use App\Support\SearchFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();
        $status = array_key_exists($status, Project::STATUSES) ? $status : null;

        return view('projects.index', [
            'projects' => Project::query()
                ->with(['customer', 'manager'])
                ->withCount('documents')
                ->withSum(['documents as supplier_committed_total' => fn ($query) => $query->where('type', 'supplier_po')], 'total')
                ->when($search, fn ($query, $term) => SearchFilters::projects($query, $term))
                ->when($status, fn ($query, $status) => $query->where('status', $status))
                ->orderByRaw("case when status = 'active' then 0 when status = 'on_hold' then 1 when status = 'completed' then 2 else 3 end")
                ->orderBy('expected_completion_date')
                ->orderBy('project_code')
                ->paginate(20)
                ->withQueryString(),
            'searchTerm' => $search,
            'statusFilter' => $status,
            'statuses' => Project::STATUSES,
            'summary' => [
                'total' => Project::count(),
                'active' => Project::query()->where('status', 'active')->count(),
                'contract_value' => (float) Project::sum('contract_value'),
                'budget_amount' => (float) Project::sum('budget_amount'),
            ],
        ]);
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

    public function show(Project $project): View
    {
        $project->load(['customer', 'manager']);
        $workItems = $project->wbsItems()->with('parent')->get();
        $workItemSummaries = $this->workItemSummaries($project, $workItems);

        return view('projects.show', [
            'project' => $project,
            'summary' => $this->commercialSummary($project),
            'workItems' => $workItems,
            'workItemSummaries' => $workItemSummaries['items'],
            'workItemTotals' => $workItemSummaries['totals'],
            'unassignedWorkItemSummary' => $workItemSummaries['unassigned'],
            'workItemStatuses' => WbsItem::STATUSES,
            'workItemCostTypes' => WbsItem::COST_TYPES,
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
            'description' => ['nullable', 'string'],
        ]);

        foreach (['contract_value', 'budget_amount', 'margin_target_percent'] as $field) {
            $data[$field] = (float) ($data[$field] ?? 0);
        }

        return $data;
    }

    private function commercialSummary(Project $project): array
    {
        $documents = Document::query()->where('project_id', $project->id);

        $customerConfirmed = (clone $documents)->where('type', 'customer_po')->sum('total');
        $customerInvoiced = (clone $documents)->where('type', 'customer_invoice')->sum('total');
        $supplierCommitted = (clone $documents)->where('type', 'supplier_po')->sum('total');
        $supplierInvoiced = (clone $documents)->where('type', 'supplier_invoice')->sum('total');

        $customerPaid = Payment::query()
            ->whereHas('document', fn ($query) => $query->where('project_id', $project->id)->where('type', 'customer_invoice'))
            ->sum('amount');
        $supplierPaid = Payment::query()
            ->whereHas('document', fn ($query) => $query->where('project_id', $project->id)->where('type', 'supplier_invoice'))
            ->sum('amount');

        return [
            'quoted_revenue' => (float) (clone $documents)->where('type', 'customer_quotation')->sum('total'),
            'customer_confirmed' => (float) $customerConfirmed,
            'customer_invoiced' => (float) $customerInvoiced,
            'customer_paid' => (float) $customerPaid,
            'supplier_committed' => (float) $supplierCommitted,
            'supplier_invoiced' => (float) $supplierInvoiced,
            'supplier_paid' => (float) $supplierPaid,
            'expected_margin' => (float) $customerConfirmed - (float) $supplierCommitted,
            'actual_margin' => (float) $customerInvoiced - (float) $supplierInvoiced,
            'budget_remaining' => (float) $project->budget_amount - (float) $supplierCommitted,
            'document_count' => (clone $documents)->count(),
        ];
    }

    private function workItemSummaries(Project $project, $workItems): array
    {
        $lineTotals = DocumentItem::query()
            ->join('documents', 'documents.id', '=', 'document_items.document_id')
            ->where('document_items.project_id', $project->id)
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
            'line_count' => 0,
        ];

        foreach ($workItems as $workItem) {
            $line = $lineTotals->get((string) $workItem->id);
            $summary = [
                'line_count' => (int) ($line->line_count ?? 0),
                'quoted_revenue' => (float) ($line->quoted_revenue ?? 0),
                'customer_confirmed' => (float) ($line->customer_confirmed ?? 0),
                'customer_invoiced' => (float) ($line->customer_invoiced ?? 0),
                'supplier_committed' => (float) ($line->supplier_committed ?? 0),
                'received_cost' => (float) ($line->received_cost ?? 0),
                'supplier_actual' => (float) ($line->supplier_actual ?? 0),
                'remaining_budget' => (float) $workItem->cost_budget - (float) ($line->supplier_committed ?? 0),
            ];

            $items[$workItem->id] = $summary;

            $totals['revenue_budget'] += (float) $workItem->revenue_budget;
            $totals['cost_budget'] += (float) $workItem->cost_budget;
            $totals['line_count'] += $summary['line_count'];

            foreach (['quoted_revenue', 'customer_confirmed', 'customer_invoiced', 'supplier_committed', 'received_cost', 'supplier_actual'] as $key) {
                $totals[$key] += $summary[$key];
            }
        }

        $totals['remaining_budget'] = $totals['cost_budget'] - $totals['supplier_committed'];
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

    private function ensureProjectManagementAccess(): void
    {
        abort_unless(request()->user()->hasRole('admin', 'manager'), 403);
    }
}
