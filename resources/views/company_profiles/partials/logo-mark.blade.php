@php
    $company = $company ?? \App\Models\CompanyProfile::active();
    $logoUrl = $company->logoUrl();
    $imageClass = $imageClass ?? 'h-10 w-10 rounded-lg object-cover shadow-sm';
    $placeholderClass = $placeholderClass ?? 'company-logo-placeholder h-10 w-10 rounded-lg text-xs';
@endphp

@if(filled($logoUrl))
    <img class="{{ $imageClass }}" src="{{ $logoUrl }}" alt="{{ $company->displayName() }}">
@else
    <span class="{{ $placeholderClass }}" aria-hidden="true">{{ $company->initials() }}</span>
@endif
