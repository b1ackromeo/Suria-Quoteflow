<div class="sidebar-workspace" aria-label="Current company">
    @include('company_profiles.partials.logo-mark', [
        'company' => $companyProfile,
        'imageClass' => 'h-8 w-8 shrink-0 rounded-lg bg-white object-contain shadow-sm ring-1 ring-blue-100',
        'placeholderClass' => 'company-logo-placeholder h-8 w-8 rounded-lg text-[11px]',
    ])
    <span class="min-w-0">
        <span class="sidebar-workspace-name">{{ $companyProfile->displayName() }}</span>
        <span class="sidebar-workspace-label">Current company</span>
    </span>
</div>
