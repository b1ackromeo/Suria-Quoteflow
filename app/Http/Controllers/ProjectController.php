<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Models\WbsItem;
use App\Services\Projects\ProjectCommercialReportService;
use App\Support\Audit;
use App\Support\SearchFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request, ProjectCommercialReportService $projectCommercialReport): View
    {
        $search = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();
        $status = array_key_exists($status, Project::STATUSES) ? $status : null;
        $projects = Project::query()
            ->with(['customer', 'manager'])
            ->withCount('documents')
            ->when($search, fn ($query, $term) => SearchFilters::projects($query, $term))
            ->when($status, fn ($query, $status) => $query->where('status', $status))
            ->orderByRaw("case when status = 'active' then 0 when status = 'on_hold' then 1 when status = 'completed' then 2 else 3 end")
            ->orderBy('expected_completion_date')
            ->orderBy('project_code')
            ->paginate(20)
            ->withQueryString();
        $portfolio = $projectCommercialReport->portfolio($projects->getCollection());

        return view('projects.index', [
            'projects' => $projects,
            'portfolioSummaries' => $portfolio['items'],
            'searchTerm' => $search,
            'statusFilter' => $status,
            'statuses' => Project::STATUSES,
            'summary' => $projectCommercialReport->portfolioOverview(),
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

    public function show(Project $project, ProjectCommercialReportService $projectCommercialReport): View
    {
        $project->load(['customer', 'manager']);
        $workItems = $project->wbsItems()->with('parent')->get();
        $commercialReport = $projectCommercialReport->report($project, $workItems);

        return view('projects.show', [
            'project' => $project,
            'summary' => $commercialReport['summary'],
            'workItems' => $workItems,
            'workItemSummaries' => $commercialReport['work_items'],
            'workItemTotals' => $commercialReport['totals'],
            'unassignedWorkItemSummary' => $commercialReport['unassigned'],
            'projectExceptions' => $commercialReport['exceptions'],
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

    private function ensureProjectManagementAccess(): void
    {
        abort_unless(request()->user()->hasRole('admin', 'manager'), 403);
    }
}
