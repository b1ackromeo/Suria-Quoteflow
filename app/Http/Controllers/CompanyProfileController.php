<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyProfileController extends Controller
{
    public function index(): View
    {
        return view('company_profiles.index', [
            'company' => $this->companyIdentity(),
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('company-profiles.edit', $this->companyIdentity());
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->update($request, $this->companyIdentity());
    }

    public function edit(CompanyProfile $companyProfile): View
    {
        return view('company_profiles.form', [
            'company' => $companyProfile,
            'countries' => $this->countries(),
            'timezones' => $this->timezones(),
            'currencies' => $this->currencies(),
            'dateFormats' => $this->dateFormats(),
            'numberFormats' => $this->numberFormats(),
        ]);
    }

    public function update(Request $request, CompanyProfile $companyProfile): RedirectResponse
    {
        $before = $companyProfile->toArray();
        $data = $this->validated($request);
        unset($data['logo']);

        DB::transaction(function () use ($request, $companyProfile, $before, &$data) {
            if ($path = $this->storeLogo($request)) {
                $data['logo_path'] = $path;
            }

            $data['base_currency'] = strtoupper($data['base_currency']);
            $data['is_active'] = true;
            CompanyProfile::whereKeyNot($companyProfile->id)->update(['is_active' => false]);

            $companyProfile->update($data);

            Audit::record('company_profile_updated', $companyProfile, $before, $companyProfile->fresh()->toArray());
        });

        return redirect()->route('company-profiles.index')->with('status', 'Company profile updated.');
    }

    public function activate(CompanyProfile $companyProfile): RedirectResponse
    {
        return redirect()->route('company-profiles.edit', $companyProfile);
    }

    private function validated(Request $request): array
    {
        $this->mergeMissingGlobalDefaults($request);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'country' => ['required', 'string', 'max:120'],
            'timezone' => ['required', Rule::in(timezone_identifiers_list())],
            'base_currency' => ['required', 'string', 'size:3'],
            'date_format' => ['required', Rule::in(array_keys($this->dateFormats()))],
            'number_format' => ['required', Rule::in(array_keys($this->numberFormats()))],
            'tax_label' => ['required', 'string', 'max:80'],
            'tax_registration_number' => ['nullable', 'string', 'max:120'],
            'default_tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'payment_instructions' => ['nullable', 'string', 'max:5000'],
            'pdf_footer' => ['nullable', 'string', 'max:5000'],
            'logo' => ['nullable', 'image', 'max:1024'],
        ]);
    }

    private function mergeMissingGlobalDefaults(Request $request): void
    {
        $defaults = CompanyProfile::defaults();

        foreach (['country', 'timezone', 'base_currency', 'date_format', 'number_format', 'tax_label', 'default_tax_rate'] as $field) {
            if (! $request->has($field)) {
                $request->merge([$field => $defaults[$field]]);
            }
        }
    }

    private function companyIdentity(): CompanyProfile
    {
        return CompanyProfile::query()->where('is_active', true)->first()
            ?? CompanyProfile::query()->orderBy('id')->first()
            ?? CompanyProfile::create(CompanyProfile::defaults());
    }

    private function storeLogo(Request $request): ?string
    {
        if (! $request->hasFile('logo')) {
            return null;
        }

        return $request->file('logo')->store('company-logos', 'public');
    }

    private function countries(): array
    {
        return [
            'Malaysia',
            'Singapore',
            'Indonesia',
            'Thailand',
            'Philippines',
            'Vietnam',
            'Brunei',
            'Australia',
            'New Zealand',
            'United Kingdom',
            'United States',
            'Other',
        ];
    }

    private function timezones(): array
    {
        return collect(timezone_identifiers_list())
            ->filter(fn (string $timezone) => str_starts_with($timezone, 'Asia/') || str_starts_with($timezone, 'Australia/') || str_starts_with($timezone, 'Pacific/') || str_starts_with($timezone, 'Europe/') || str_starts_with($timezone, 'America/'))
            ->values()
            ->all();
    }

    private function currencies(): array
    {
        return [
            'MYR' => 'MYR - Malaysian Ringgit',
            'SGD' => 'SGD - Singapore Dollar',
            'USD' => 'USD - US Dollar',
            'EUR' => 'EUR - Euro',
            'GBP' => 'GBP - Pound Sterling',
            'AUD' => 'AUD - Australian Dollar',
            'NZD' => 'NZD - New Zealand Dollar',
            'IDR' => 'IDR - Indonesian Rupiah',
            'THB' => 'THB - Thai Baht',
            'PHP' => 'PHP - Philippine Peso',
            'VND' => 'VND - Vietnamese Dong',
            'BND' => 'BND - Brunei Dollar',
        ];
    }

    private function dateFormats(): array
    {
        return [
            'd M Y' => '20 May 2026',
            'd/m/Y' => '20/05/2026',
            'm/d/Y' => '05/20/2026',
            'Y-m-d' => '2026-05-20',
        ];
    }

    private function numberFormats(): array
    {
        return [
            'en-MY' => '1,234.56',
            'en-SG' => '1,234.56',
            'en-US' => '1,234.56',
            'en-GB' => '1,234.56',
            'de-DE' => '1.234,56',
            'fr-FR' => '1 234,56',
        ];
    }
}
