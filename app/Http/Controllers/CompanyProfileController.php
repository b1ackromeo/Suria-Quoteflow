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
            'numberFormats' => $this->numberFormats(),
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
            'currencyDisplays' => $this->currencyDisplays(),
            'dateFormats' => $this->dateFormats(),
            'numberFormats' => $this->numberFormats(),
            'countryDefaults' => $this->countryDefaults(),
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
            $data['currency_symbol_override'] = filled($data['currency_symbol_override'] ?? null) ? trim((string) $data['currency_symbol_override']) : null;
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
            'timezone' => ['required', Rule::in($this->timezones())],
            'base_currency' => ['required', 'string', 'size:3'],
            'currency_display' => ['nullable', Rule::in(array_keys($this->currencyDisplays()))],
            'currency_symbol_override' => ['nullable', 'string', 'max:20'],
            'date_format' => ['required', Rule::in(array_keys($this->dateFormats()))],
            'number_format' => ['required', Rule::in(array_keys($this->numberFormats()))],
            'tax_label' => ['required', 'string', 'max:80'],
            'tax_registration_label' => ['required', 'string', 'max:120'],
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

        foreach (['country', 'timezone', 'base_currency', 'currency_display', 'currency_symbol_override', 'date_format', 'number_format', 'tax_label', 'tax_registration_label', 'default_tax_rate'] as $field) {
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
        return array_keys($this->countryDefaults());
    }

    private function countryDefaults(): array
    {
        return [
            'Malaysia' => [
                'timezone' => 'Asia/Kuala_Lumpur',
                'base_currency' => 'MYR',
                'currency_display' => 'symbol',
                'currency_symbol_override' => 'RM',
                'date_format' => 'd M Y',
                'number_format' => 'en-MY',
                'tax_label' => 'Tax',
                'tax_registration_label' => 'Tax Registration No.',
                'default_tax_rate' => '0',
            ],
            'Singapore' => [
                'timezone' => 'Asia/Singapore',
                'base_currency' => 'SGD',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'd M Y',
                'number_format' => 'en-SG',
                'tax_label' => 'GST',
                'tax_registration_label' => 'GST Registration No.',
                'default_tax_rate' => '0',
            ],
            'Indonesia' => [
                'timezone' => 'Asia/Jakarta',
                'base_currency' => 'IDR',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'd/m/Y',
                'number_format' => 'en-MY',
                'tax_label' => 'Tax',
                'tax_registration_label' => 'Tax Registration No.',
                'default_tax_rate' => '0',
            ],
            'Thailand' => [
                'timezone' => 'Asia/Bangkok',
                'base_currency' => 'THB',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'd/m/Y',
                'number_format' => 'en-MY',
                'tax_label' => 'VAT',
                'tax_registration_label' => 'VAT Registration No.',
                'default_tax_rate' => '0',
            ],
            'Philippines' => [
                'timezone' => 'Asia/Manila',
                'base_currency' => 'PHP',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'd/m/Y',
                'number_format' => 'en-US',
                'tax_label' => 'VAT',
                'tax_registration_label' => 'VAT Registration No.',
                'default_tax_rate' => '0',
            ],
            'Vietnam' => [
                'timezone' => 'Asia/Ho_Chi_Minh',
                'base_currency' => 'VND',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'd/m/Y',
                'number_format' => 'en-MY',
                'tax_label' => 'VAT',
                'tax_registration_label' => 'VAT Registration No.',
                'default_tax_rate' => '0',
            ],
            'Brunei' => [
                'timezone' => 'Asia/Brunei',
                'base_currency' => 'BND',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'd M Y',
                'number_format' => 'en-GB',
                'tax_label' => 'Tax',
                'tax_registration_label' => 'Tax Registration No.',
                'default_tax_rate' => '0',
            ],
            'Australia' => [
                'timezone' => 'Australia/Sydney',
                'base_currency' => 'AUD',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'd/m/Y',
                'number_format' => 'en-GB',
                'tax_label' => 'GST',
                'tax_registration_label' => 'GST Registration No.',
                'default_tax_rate' => '0',
            ],
            'New Zealand' => [
                'timezone' => 'Pacific/Auckland',
                'base_currency' => 'NZD',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'd/m/Y',
                'number_format' => 'en-GB',
                'tax_label' => 'GST',
                'tax_registration_label' => 'GST Registration No.',
                'default_tax_rate' => '0',
            ],
            'United Kingdom' => [
                'timezone' => 'Europe/London',
                'base_currency' => 'GBP',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'd/m/Y',
                'number_format' => 'en-GB',
                'tax_label' => 'VAT',
                'tax_registration_label' => 'VAT Registration No.',
                'default_tax_rate' => '0',
            ],
            'United States' => [
                'timezone' => 'America/New_York',
                'base_currency' => 'USD',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'm/d/Y',
                'number_format' => 'en-US',
                'tax_label' => 'Sales tax',
                'tax_registration_label' => 'Tax ID / EIN',
                'default_tax_rate' => '0',
            ],
            'Canada' => [
                'timezone' => 'America/Toronto',
                'base_currency' => 'CAD',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'Y-m-d',
                'number_format' => 'en-CA',
                'tax_label' => 'GST/HST',
                'tax_registration_label' => 'Business Number / GST-HST No.',
                'default_tax_rate' => '0',
            ],
            'Germany' => [
                'timezone' => 'Europe/Berlin',
                'base_currency' => 'EUR',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'd/m/Y',
                'number_format' => 'de-DE',
                'tax_label' => 'VAT',
                'tax_registration_label' => 'VAT ID',
                'default_tax_rate' => '0',
            ],
            'France' => [
                'timezone' => 'Europe/Paris',
                'base_currency' => 'EUR',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'd/m/Y',
                'number_format' => 'fr-FR',
                'tax_label' => 'VAT',
                'tax_registration_label' => 'VAT ID',
                'default_tax_rate' => '0',
            ],
            'Switzerland' => [
                'timezone' => 'Europe/Zurich',
                'base_currency' => 'CHF',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'd/m/Y',
                'number_format' => 'de-CH',
                'tax_label' => 'VAT',
                'tax_registration_label' => 'VAT ID',
                'default_tax_rate' => '0',
            ],
            'Japan' => [
                'timezone' => 'Asia/Tokyo',
                'base_currency' => 'JPY',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'Y-m-d',
                'number_format' => 'ja-JP',
                'tax_label' => 'Consumption tax',
                'tax_registration_label' => 'Qualified Invoice Issuer No.',
                'default_tax_rate' => '0',
            ],
            'China' => [
                'timezone' => 'Asia/Shanghai',
                'base_currency' => 'CNY',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'Y-m-d',
                'number_format' => 'zh-CN',
                'tax_label' => 'VAT',
                'tax_registration_label' => 'Taxpayer ID',
                'default_tax_rate' => '0',
            ],
            'Hong Kong' => [
                'timezone' => 'Asia/Hong_Kong',
                'base_currency' => 'HKD',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'd/m/Y',
                'number_format' => 'en-GB',
                'tax_label' => 'Tax',
                'tax_registration_label' => 'Business Registration No.',
                'default_tax_rate' => '0',
            ],
            'India' => [
                'timezone' => 'Asia/Kolkata',
                'base_currency' => 'INR',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'd/m/Y',
                'number_format' => 'en-IN',
                'tax_label' => 'GST',
                'tax_registration_label' => 'GSTIN',
                'default_tax_rate' => '0',
            ],
            'United Arab Emirates' => [
                'timezone' => 'Asia/Dubai',
                'base_currency' => 'AED',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'd/m/Y',
                'number_format' => 'en-GB',
                'tax_label' => 'VAT',
                'tax_registration_label' => 'TRN',
                'default_tax_rate' => '0',
            ],
            'Saudi Arabia' => [
                'timezone' => 'Asia/Riyadh',
                'base_currency' => 'SAR',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'd/m/Y',
                'number_format' => 'en-GB',
                'tax_label' => 'VAT',
                'tax_registration_label' => 'VAT Registration No.',
                'default_tax_rate' => '0',
            ],
            'South Africa' => [
                'timezone' => 'Africa/Johannesburg',
                'base_currency' => 'ZAR',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'Y-m-d',
                'number_format' => 'en-GB',
                'tax_label' => 'VAT',
                'tax_registration_label' => 'VAT Registration No.',
                'default_tax_rate' => '0',
            ],
            'South Korea' => [
                'timezone' => 'Asia/Seoul',
                'base_currency' => 'KRW',
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => 'Y-m-d',
                'number_format' => 'ko-KR',
                'tax_label' => 'VAT',
                'tax_registration_label' => 'Business Registration No.',
                'default_tax_rate' => '0',
            ],
            'Other' => [
                'timezone' => CompanyProfile::defaults()['timezone'],
                'base_currency' => CompanyProfile::defaults()['base_currency'],
                'currency_display' => 'code',
                'currency_symbol_override' => '',
                'date_format' => CompanyProfile::defaults()['date_format'],
                'number_format' => CompanyProfile::defaults()['number_format'],
                'tax_label' => CompanyProfile::defaults()['tax_label'],
                'tax_registration_label' => CompanyProfile::defaults()['tax_registration_label'],
                'default_tax_rate' => (string) CompanyProfile::defaults()['default_tax_rate'],
            ],
        ];
    }

    private function timezones(): array
    {
        return collect(['UTC'])
            ->merge(collect(timezone_identifiers_list())
                ->filter(fn (string $timezone) => str_starts_with($timezone, 'Asia/') || str_starts_with($timezone, 'Australia/') || str_starts_with($timezone, 'Pacific/') || str_starts_with($timezone, 'Europe/') || str_starts_with($timezone, 'America/') || str_starts_with($timezone, 'Africa/')))
            ->unique()
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
            'CAD' => 'CAD - Canadian Dollar',
            'CHF' => 'CHF - Swiss Franc',
            'JPY' => 'JPY - Japanese Yen',
            'CNY' => 'CNY - Chinese Yuan',
            'HKD' => 'HKD - Hong Kong Dollar',
            'INR' => 'INR - Indian Rupee',
            'AED' => 'AED - UAE Dirham',
            'SAR' => 'SAR - Saudi Riyal',
            'ZAR' => 'ZAR - South African Rand',
            'KRW' => 'KRW - South Korean Won',
            'IDR' => 'IDR - Indonesian Rupiah',
            'THB' => 'THB - Thai Baht',
            'PHP' => 'PHP - Philippine Peso',
            'VND' => 'VND - Vietnamese Dong',
            'BND' => 'BND - Brunei Dollar',
        ];
    }

    private function currencyDisplays(): array
    {
        return [
            'code' => 'Currency code - MYR 1,234.56',
            'symbol' => 'Currency symbol - RM 1,234.56',
            'symbol_with_code' => 'Symbol and code - RM 1,234.56 (MYR)',
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
            'en-MY' => 'Malaysia / English - 1,234.56',
            'en-SG' => 'Singapore / English - 1,234.56',
            'en-US' => 'United States / English - 1,234.56',
            'en-GB' => 'United Kingdom / English - 1,234.56',
            'en-CA' => 'Canada / English - 1,234.56',
            'en-IN' => 'India / English - 1,234.56',
            'ja-JP' => 'Japan / Japanese - 1,234.56',
            'zh-CN' => 'China / Chinese - 1,234.56',
            'ko-KR' => 'South Korea / Korean - 1,234.56',
            'de-DE' => 'Germany / German - 1.234,56',
            'de-CH' => 'Switzerland / German - 1.234,56',
            'fr-FR' => 'France / French - 1 234,56',
        ];
    }
}
