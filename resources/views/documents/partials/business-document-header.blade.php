@php
    $companyProfile = $companyProfile ?? \App\Models\CompanyProfile::active();
    $companyAddressLine = trim(preg_replace('/\s+/', ' ', (string) $companyProfile->address));
    $companyRegistrationLine = $companyProfile->registration_number ? 'Reg. No. '.$companyProfile->registration_number : null;
    $companyContactLine = collect([
        $companyRegistrationLine,
        $companyAddressLine,
        $companyProfile->email,
        $companyProfile->phone,
    ])->filter()->implode(' | ');
    $primaryColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $companyProfile->primary_color)
        ? $companyProfile->primary_color
        : '#0a345f';
    $accentColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $companyProfile->accent_color)
        ? $companyProfile->accent_color
        : '#0a4f93';
    $documentNumber = $documentNumber ?? 'Draft document';
    $documentKicker = $documentKicker ?? null;
    $statusLabel = $statusLabel ?? null;
    $titleAttributes = $titleAttributes ?? '';
    $kickerAttributes = $kickerAttributes ?? '';
@endphp

<div
    class="business-letterhead"
    style="--doc-primary: {{ $primaryColor }}; --doc-accent: {{ $accentColor }};"
>
    <div class="business-letterhead-main">
        <div class="business-letterhead-brand">
            <img class="business-letterhead-logo" src="{{ $companyProfile->logoUrl() }}" alt="{{ $companyProfile->displayName() }}">
            <div class="business-letterhead-copy">
                <p class="business-letterhead-name">{{ $companyProfile->displayName() }}</p>
                <p class="business-letterhead-tagline">{{ $companyProfile->displayTagline() }}</p>
                @if($companyContactLine)
                    <p class="business-letterhead-contact">{{ $companyContactLine }}</p>
                @endif
            </div>
        </div>

        <div class="business-letterhead-document">
            @if($documentKicker)
                <p class="business-letterhead-kicker" {!! $kickerAttributes !!}>{{ $documentKicker }}</p>
            @endif
            <h3 {!! $titleAttributes !!}>{{ $documentTitle }}</h3>
            <div class="business-letterhead-number">
                <span>Document No.</span>
                <strong>{{ $documentNumber }}</strong>
            </div>
            @if($statusLabel)
                <span class="business-letterhead-status">{{ $statusLabel }}</span>
            @endif
        </div>
    </div>
    <div class="business-letterhead-rule"></div>
</div>
