<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CompanyProfile extends Model
{
    protected $fillable = [
        'name',
        'registration_number',
        'email',
        'phone',
        'address',
        'tagline',
        'logo_path',
        'primary_color',
        'accent_color',
        'country',
        'timezone',
        'base_currency',
        'currency_display',
        'currency_symbol_override',
        'date_format',
        'number_format',
        'tax_label',
        'tax_registration_label',
        'tax_registration_number',
        'default_tax_rate',
        'payment_instructions',
        'pdf_footer',
        'is_active',
    ];

    protected $casts = [
        'default_tax_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public static function active(): self
    {
        return static::query()->where('is_active', true)->first()
            ?? static::query()->orderBy('id')->first()
            ?? new static(static::fallbackDefaults());
    }

    public static function defaults(): array
    {
        return [
            'name' => 'RC Technology Resources',
            'registration_number' => '202603107223 (003844744-P)',
            'email' => 'rctech@gmail.com',
            'phone' => '+60 16-445 2786',
            'address' => '1-3 Level 1, Jalan Perdana Blok 4801, CBD Perdana, Cyberjaya',
            'tagline' => 'Reliable Infrastructure. Connected Future.',
            'primary_color' => '#0a345f',
            'accent_color' => '#0a4f93',
            'country' => 'Malaysia',
            'timezone' => 'Asia/Kuala_Lumpur',
            'base_currency' => 'MYR',
            'currency_display' => null,
            'currency_symbol_override' => null,
            'date_format' => 'd M Y',
            'number_format' => 'en-MY',
            'tax_label' => 'Tax',
            'tax_registration_label' => 'Tax Registration No.',
            'tax_registration_number' => null,
            'default_tax_rate' => 0,
            'payment_instructions' => null,
            'pdf_footer' => null,
            'is_active' => true,
        ];
    }

    public function logoUrl(): string
    {
        if ($this->logo_path) {
            return Storage::disk('public')->url($this->logo_path);
        }

        if ($this->shouldUseDefaultLogo()) {
            return asset('images/rctech-logo-clean.png');
        }

        return '';
    }

    public function logoPathForPdf(): string
    {
        if ($this->logo_path && Storage::disk('public')->exists($this->logo_path)) {
            return Storage::disk('public')->path($this->logo_path);
        }

        return $this->shouldUseDefaultLogo() ? public_path('images/rctech-logo-clean.png') : '';
    }

    public function displayName(): string
    {
        return $this->name ?: static::defaults()['name'];
    }

    public function displayTagline(): string
    {
        return $this->tagline ?: static::defaults()['tagline'];
    }

    public function displayCountry(): string
    {
        return $this->country ?: static::defaults()['country'];
    }

    public function displayTimezone(): string
    {
        return $this->timezone ?: static::defaults()['timezone'];
    }

    public function baseCurrency(): string
    {
        return strtoupper((string) ($this->base_currency ?: static::defaults()['base_currency']));
    }

    public function currencyDisplay(): string
    {
        if (in_array($this->currency_display, ['code', 'symbol', 'symbol_with_code'], true)) {
            return $this->currency_display;
        }

        return $this->baseCurrency() === 'MYR' ? 'symbol' : 'code';
    }

    public function currencySymbol(?string $currency = null): string
    {
        $currency = strtoupper((string) ($currency ?: $this->baseCurrency()));

        if ($currency === $this->baseCurrency() && filled($this->currency_symbol_override)) {
            return trim((string) $this->currency_symbol_override);
        }

        return static::currencySymbols()[$currency] ?? $currency;
    }

    public function currencyDisplayLabel(): string
    {
        $currency = $this->baseCurrency();
        $symbol = $this->currencySymbol($currency);

        return match ($this->currencyDisplay()) {
            'symbol' => $symbol,
            'symbol_with_code' => $symbol.' ('.$currency.')',
            default => $currency,
        };
    }

    public function dateFormat(): string
    {
        return $this->date_format ?: static::defaults()['date_format'];
    }

    public function numberFormat(): string
    {
        return $this->number_format ?: static::defaults()['number_format'];
    }

    public function taxLabel(): string
    {
        return $this->tax_label ?: static::defaults()['tax_label'];
    }

    public function taxRegistrationLabel(): string
    {
        return $this->tax_registration_label ?: static::defaults()['tax_registration_label'];
    }

    public function defaultTaxRate(): float
    {
        return (float) ($this->default_tax_rate ?? static::defaults()['default_tax_rate']);
    }

    public function displayPdfFooter(): string
    {
        return $this->pdf_footer ?: $this->displayName();
    }

    public function paymentInstructionLines(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', trim((string) $this->payment_instructions)))
            ->filter(fn (string $line) => filled($line))
            ->values()
            ->all();
    }

    public function formatDate(mixed $date): string
    {
        if (! $date) {
            return '-';
        }

        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }

        return $date->format($this->dateFormat());
    }

    public function formatNumber(mixed $value, int $decimals = 2): string
    {
        [$decimalSeparator, $thousandSeparator] = $this->numberSeparators();

        return number_format((float) $value, $decimals, $decimalSeparator, $thousandSeparator);
    }

    public function formatMoney(mixed $value, ?string $currency = null): string
    {
        $currency = strtoupper((string) ($currency ?: $this->baseCurrency()));
        $amount = $this->formatNumber($value);

        return match ($this->currencyDisplay()) {
            'symbol' => trim($this->currencySymbol($currency).' '.$amount),
            'symbol_with_code' => trim($this->currencySymbol($currency).' '.$amount.' ('.$currency.')'),
            default => trim($currency.' '.$amount),
        };
    }

    public function formatPercent(mixed $value, int $decimals = 2): string
    {
        return $this->formatNumber($value, $decimals).'%';
    }

    public static function currencySymbols(): array
    {
        return [
            'MYR' => 'RM',
            'SGD' => 'S$',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AUD' => 'A$',
            'NZD' => 'NZ$',
            'CAD' => 'C$',
            'JPY' => '¥',
            'CNY' => '¥',
            'HKD' => 'HK$',
            'INR' => '₹',
            'AED' => 'AED',
            'IDR' => 'Rp',
            'THB' => '฿',
            'PHP' => '₱',
            'VND' => '₫',
            'BND' => 'B$',
        ];
    }

    private static function fallbackDefaults(): array
    {
        $defaults = static::defaults();

        if (app()->environment('testing')) {
            $defaults['currency_display'] = 'code';
        }

        return $defaults;
    }

    private function numberSeparators(): array
    {
        return match ($this->numberFormat()) {
            'de-DE' => [',', '.'],
            'fr-FR' => [',', ' '],
            default => ['.', ','],
        };
    }

    private function shouldUseDefaultLogo(): bool
    {
        return $this->displayName() === static::defaults()['name'];
    }
}
