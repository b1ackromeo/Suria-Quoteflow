@extends('layouts.app', [
    'title' => $project->exists ? 'Edit project' : 'New project',
    'contentMode' => 'fullscreen',
])

@php
    $isEditing = $project->exists;
    $selectedStatus = old('status', $project->status ?? 'active');
    $contractValue = old('contract_value', $project->contract_value ?? 0);
    $budgetAmount = old('budget_amount', $project->budget_amount ?? 0);
    $marginTarget = old('margin_target_percent', $project->margin_target_percent ?? 0);
@endphp

@section('content')
<form
    method="post"
    action="{{ $isEditing ? route('projects.update', $project) : route('projects.store') }}"
    class="fullscreen-workspace directory-form-workspace"
>
    @csrf
    @if($isEditing)
        @method('put')
    @endif

    <section class="directory-page-header">
        <div class="min-w-0">
            <p class="document-pane-kicker">Project control</p>
            <h1 class="document-pane-title">{{ $isEditing ? 'Edit project' : 'New project' }}</h1>
            <p class="mt-1 max-w-3xl text-sm font-semibold leading-6 text-slate-500">
                Keep the job identity, customer, budget, and margin reference ready before linking commercial documents.
            </p>
        </div>
        <div class="directory-header-actions">
            <a class="btn btn-secondary" href="{{ $isEditing ? route('projects.show', $project) : route('projects.index') }}">Cancel</a>
            <button class="btn btn-primary" type="submit">Save project</button>
        </div>
    </section>

    <div class="directory-form-body">
        <section class="directory-form-section">
            <div class="directory-form-section-header">
                <div>
                    <p class="studio-section-kicker">Project record</p>
                    <h2 class="studio-section-title">Project identity</h2>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="form-label">
                    Project code
                    <input class="form-input" name="project_code" value="{{ old('project_code', $project->project_code) }}" placeholder="PRJ-2026-001" required>
                </label>
                <label class="form-label md:col-span-2">
                    Project name
                    <input class="form-input" name="name" value="{{ old('name', $project->name) }}" placeholder="Cyberjaya network rollout" required>
                </label>
                <label class="form-label">
                    Status
                    <select class="form-input" name="status" required>
                        @foreach($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-label md:col-span-2">
                    Customer
                    <select class="form-input" name="customer_id">
                        <option value="">No customer assigned</option>
                        @foreach($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((int) old('customer_id', $project->customer_id) === $customer->id)>{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-label md:col-span-2">
                    Project manager
                    <select class="form-input" name="manager_id">
                        <option value="">No manager assigned</option>
                        @foreach($managers as $manager)
                            <option value="{{ $manager->id }}" @selected((int) old('manager_id', $project->manager_id) === $manager->id)>{{ $manager->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-label">
                    Start date
                    <input class="form-input" type="date" name="start_date" value="{{ old('start_date', optional($project->start_date)->format('Y-m-d')) }}">
                </label>
                <label class="form-label">
                    Expected completion
                    <input class="form-input" type="date" name="expected_completion_date" value="{{ old('expected_completion_date', optional($project->expected_completion_date)->format('Y-m-d')) }}">
                </label>
                <label class="form-label">
                    Contract value
                    <input class="form-input" type="number" step="0.01" min="0" name="contract_value" value="{{ $contractValue }}">
                </label>
                <label class="form-label">
                    Budget amount
                    <input class="form-input" type="number" step="0.01" min="0" name="budget_amount" value="{{ $budgetAmount }}">
                </label>
                <label class="form-label">
                    Margin target %
                    <input class="form-input" type="number" step="0.01" min="0" max="100" name="margin_target_percent" value="{{ $marginTarget }}">
                </label>
                <label class="form-label md:col-span-2 xl:col-span-4">
                    Project summary
                    <textarea class="form-input min-h-36 leading-6" name="description" placeholder="Scope, commercial notes, delivery phases, or budget context.">{{ old('description', $project->description) }}</textarea>
                </label>
            </div>
        </section>
    </div>
</form>
@endsection
