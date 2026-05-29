<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\WbsItem;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectWorkItemController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->ensureProjectManagementAccess();

        $workItem = $project->wbsItems()->create($this->validated($request, $project));
        Audit::record('work_item_created', $workItem, null, $workItem->toArray());

        return redirect()->route('projects.show', $project)->with('status', 'Work item created.');
    }

    public function update(Request $request, Project $project, WbsItem $wbsItem): RedirectResponse
    {
        $this->ensureProjectManagementAccess();
        $this->ensureProjectOwnsWorkItem($project, $wbsItem);

        $before = $wbsItem->toArray();
        $wbsItem->update($this->validated($request, $project, $wbsItem->id));
        Audit::record('work_item_updated', $wbsItem, $before, $wbsItem->fresh()->toArray());

        return redirect()->route('projects.show', $project)->with('status', 'Work item updated.');
    }

    public function destroy(Project $project, WbsItem $wbsItem): RedirectResponse
    {
        $this->ensureProjectManagementAccess();
        $this->ensureProjectOwnsWorkItem($project, $wbsItem);

        if ($wbsItem->documentItems()->exists()) {
            return redirect()
                ->route('projects.show', $project)
                ->withErrors(['work_item' => 'This work item is used on document lines. Set it to inactive instead of deleting it.']);
        }

        $before = $wbsItem->toArray();
        $wbsItem->delete();
        Audit::record('work_item_deleted', $project, $before, ['work_item_id' => $wbsItem->id]);

        return redirect()->route('projects.show', $project)->with('status', 'Work item deleted.');
    }

    private function validated(Request $request, Project $project, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'parent_id' => ['nullable', Rule::exists('wbs_items', 'id')->where('project_id', $project->id)],
            'code' => [
                'required',
                'string',
                'max:80',
                Rule::unique('wbs_items', 'code')->where('project_id', $project->id)->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cost_type' => ['required', Rule::in(array_keys(WbsItem::COST_TYPES))],
            'revenue_budget' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'cost_budget' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'delivery_gap_alert_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'received_not_invoiced_alert_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'delivery_gap_alert_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'received_not_invoiced_alert_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'status' => ['required', Rule::in(array_keys(WbsItem::STATUSES))],
        ]);

        if ($ignoreId !== null && (int) ($data['parent_id'] ?? 0) === $ignoreId) {
            throw ValidationException::withMessages(['parent_id' => 'Select a different parent work item.']);
        }

        foreach (['revenue_budget', 'cost_budget'] as $field) {
            $data[$field] = (float) ($data[$field] ?? 0);
        }

        foreach (['delivery_gap_alert_percent', 'received_not_invoiced_alert_percent', 'delivery_gap_alert_amount', 'received_not_invoiced_alert_amount'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? (float) $data[$field] : null;
        }

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    private function ensureProjectOwnsWorkItem(Project $project, WbsItem $wbsItem): void
    {
        abort_unless((int) $wbsItem->project_id === (int) $project->id, 404);
    }

    private function ensureProjectManagementAccess(): void
    {
        abort_unless(request()->user()->hasRole('admin', 'manager'), 403);
    }
}
