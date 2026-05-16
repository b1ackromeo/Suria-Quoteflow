<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    public const CATEGORY_SUGGESTIONS = [
        'Materials / Hardware',
        'Service Subcontractor',
        'Logistics / Delivery',
        'Office / Admin',
        'Professional Services',
        'Utilities / Recurring Bills',
        'Other',
    ];

    protected $fillable = [
        'name',
        'code',
        'category',
        'contact_person',
        'email',
        'phone',
        'address',
        'tax_number',
        'payment_terms_days',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'payment_terms_days' => 'integer',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
