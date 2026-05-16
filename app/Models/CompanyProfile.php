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
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function active(): self
    {
        return static::query()->where('is_active', true)->first()
            ?? static::query()->orderBy('id')->first()
            ?? new static(static::defaults());
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

        return $this->placeholderLogoDataUri();
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

    private function shouldUseDefaultLogo(): bool
    {
        return $this->displayName() === static::defaults()['name'];
    }

    private function placeholderLogoDataUri(): string
    {
        $initials = collect(preg_split('/\s+/', trim($this->displayName())))
            ->filter()
            ->take(2)
            ->map(fn (string $word) => strtoupper(substr($word, 0, 1)))
            ->implode('') ?: 'CO';

        $primary = $this->primary_color ?: static::defaults()['primary_color'];
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128"><rect width="128" height="128" rx="28" fill="%s"/><text x="64" y="73" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="38" font-weight="700" fill="#fff">%s</text></svg>',
            htmlspecialchars($primary, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($initials, ENT_QUOTES, 'UTF-8')
        );

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
