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
        'delivery_gap_alert_percent',
        'received_not_invoiced_alert_percent',
        'payment_instructions',
        'pdf_footer',
        'is_active',
    ];

    protected $casts = [
        'default_tax_rate' => 'decimal:2',
        'delivery_gap_alert_percent' => 'decimal:2',
        'received_not_invoiced_alert_percent' => 'decimal:2',
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
            'name' => 'Your Company Name',
            'registration_number' => null,
            'email' => null,
            'phone' => null,
            'address' => null,
            'tagline' => '',
            'primary_color' => '#0a345f',
            'accent_color' => '#0a4f93',
            'country' => 'Other',
            'timezone' => 'UTC',
            'base_currency' => 'USD',
            'currency_display' => 'code',
            'currency_symbol_override' => null,
            'date_format' => 'Y-m-d',
            'number_format' => 'en-US',
            'tax_label' => 'Tax',
            'tax_registration_label' => 'Tax Registration No.',
            'tax_registration_number' => null,
            'default_tax_rate' => 0,
            'delivery_gap_alert_percent' => 25,
            'received_not_invoiced_alert_percent' => 10,
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

    public function initials(): string
    {
        $initials = collect(preg_split('/\s+/', trim($this->displayName())) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $word) => strtoupper(substr($word, 0, 1)))
            ->implode('');

        return $initials ?: 'CO';
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

    public function deliveryGapAlertPercent(): float
    {
        return (float) ($this->delivery_gap_alert_percent ?? static::defaults()['delivery_gap_alert_percent']);
    }

    public function receivedNotInvoicedAlertPercent(): float
    {
        return (float) ($this->received_not_invoiced_alert_percent ?? static::defaults()['received_not_invoiced_alert_percent']);
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
        if ($this->numberFormat() === 'en-IN') {
            return $this->formatIndianNumber($value, $decimals);
        }

        [$decimalSeparator, $thousandSeparator] = $this->numberSeparators();

        return number_format((float) $value, $decimals, $decimalSeparator, $thousandSeparator);
    }

    public function formatMoney(mixed $value, ?string $currency = null): string
    {
        $currency = strtoupper((string) ($currency ?: $this->baseCurrency()));
        $amount = $this->formatNumber($value, $this->moneyDecimals($currency));

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
            'CHF' => 'CHF',
            'JPY' => '¥',
            'CNY' => '¥',
            'HKD' => 'HK$',
            'INR' => '₹',
            'AED' => 'AED',
            'SAR' => 'SAR',
            'ZAR' => 'R',
            'KRW' => '₩',
            'IDR' => 'Rp',
            'THB' => '฿',
            'PHP' => '₱',
            'VND' => '₫',
            'BND' => 'B$',
        ];
    }

    private static function fallbackDefaults(): array
    {
        return static::defaults();
    }

    private function numberSeparators(): array
    {
        return match ($this->numberFormat()) {
            'de-DE' => [',', '.'],
            'de-CH' => ['.', "'"],
            'fr-FR' => [',', ' '],
            default => ['.', ','],
        };
    }

    private function moneyDecimals(string $currency): int
    {
        return in_array($currency, ['JPY', 'KRW', 'VND'], true) ? 0 : 2;
    }

    private function formatIndianNumber(mixed $value, int $decimals = 2): string
    {
        $value = (float) $value;
        $negative = $value < 0;
        $absolute = abs($value);
        $formatted = number_format($absolute, $decimals, '.', '');
        [$whole, $fraction] = array_pad(explode('.', $formatted, 2), 2, '');

        if (strlen($whole) > 3) {
            $lastThree = substr($whole, -3);
            $leading = substr($whole, 0, -3);
            $leading = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $leading);
            $whole = $leading.','.$lastThree;
        }

        return ($negative ? '-' : '').$whole.($decimals > 0 ? '.'.$fraction : '');
    }

    private function shouldUseDefaultLogo(): bool
    {
        return false;
    }
}
