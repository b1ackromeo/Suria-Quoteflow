<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectVariation;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectVariationController extends Controller
{
    private const SOURCE_DOCUMENT_TYPES = [
        'customer_quotation',
        'customer_po',
        'supplier_quotation',
        'supplier_po',
    ];

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->ensureProjectManagementAccess();

        $variation = $project->variations()->create($this->validated($request, $project));
        Audit::record('project_variation_created', $variation, null, $variation->toArray());

        return redirect()->route('projects.show', $project)->with('status', 'Variation order created.');
    }

    public function update(Request $request, Project $project, ProjectVariation $projectVariation): RedirectResponse
    {
        $this->ensureProjectManagementAccess();
        $this->ensureProjectOwnsVariation($project, $projectVariation);

        $before = $projectVariation->toArray();
        $projectVariation->update($this->validated($request, $project, $projectVariation->id));
        Audit::record('project_variation_updated', $projectVariation, $before, $projectVariation->fresh()->toArray());

        return redirect()->route('projects.show', $project)->with('status', 'Variation order updated.');
    }

    public function destroy(Project $project, ProjectVariation $projectVariation): RedirectResponse
    {
        $this->ensureProjectManagementAccess();
        $this->ensureProjectOwnsVariation($project, $projectVariation);

        $before = $projectVariation->toArray();
        $projectVariation->delete();
        Audit::record('project_variation_deleted', $projectVariation, $before, ['project_id' => $project->id]);

        return redirect()->route('projects.show', $project)->with('status', 'Variation order deleted.');
    }

    private function validated(Request $request, Project $project, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'variation_number' => [
                'required',
                'string',
                'max:80',
                Rule::unique('project_variations', 'variation_number')->where('project_id', $project->id)->ignore($ignoreId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(ProjectVariation::STATUSES))],
            'effective_date' => ['nullable', 'date'],
            'customer_value' => ['nullable', 'numeric', 'min:-999999999', 'max:999999999'],
            'supplier_cost' => ['nullable', 'numeric', 'min:-999999999', 'max:999999999'],
            'source_document_id' => [
                'nullable',
                Rule::exists('documents', 'id')->where(function ($query) use ($project) {
                    $query
                        ->where('project_id', $project->id)
                        ->whereIn('type', self::SOURCE_DOCUMENT_TYPES);
                }),
            ],
            'notes' => ['nullable', 'string'],
        ]);

        $data['customer_value'] = round((float) ($data['customer_value'] ?? 0), 2);
        $data['supplier_cost'] = round((float) ($data['supplier_cost'] ?? 0), 2);

        if (abs($data['customer_value']) < 0.01 && abs($data['supplier_cost']) < 0.01) {
            throw ValidationException::withMessages([
                'customer_value' => 'Add a customer value change or supplier cost change before saving this variation order.',
            ]);
        }

        if (($data['status'] ?? null) === 'approved' && blank($data['effective_date'] ?? null)) {
            throw ValidationException::withMessages([
                'effective_date' => 'Add an effective date before approving this variation order.',
            ]);
        }

        $data['source_document_id'] = filled($data['source_document_id'] ?? null)
            ? (int) $data['source_document_id']
            : null;

        return $data;
    }

    private function ensureProjectOwnsVariation(Project $project, ProjectVariation $projectVariation): void
    {
        abort_unless((int) $projectVariation->project_id === (int) $project->id, 404);
    }

    private function ensureProjectManagementAccess(): void
    {
        abort_unless(request()->user()->hasRole('admin', 'manager'), 403);
    }
}
