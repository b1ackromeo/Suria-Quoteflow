<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public const STATUSES = [
        'active' => 'Active',
        'on_hold' => 'On hold',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'project_code',
        'name',
        'customer_id',
        'manager_id',
        'status',
        'start_date',
        'expected_completion_date',
        'contract_value',
        'budget_amount',
        'margin_target_percent',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expected_completion_date' => 'date',
        'contract_value' => 'decimal:2',
        'budget_amount' => 'decimal:2',
        'margin_target_percent' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function wbsItems(): HasMany
    {
        return $this->hasMany(WbsItem::class)->orderBy('sort_order')->orderBy('code');
    }

    public function statusDisplay(): string
    {
        return self::STATUSES[$this->status] ?? ucwords(str_replace('_', ' ', (string) $this->status));
    }

    public function statusChipClass(): string
    {
        return match ($this->status) {
            'active' => 'status-approved',
            'on_hold' => 'status-pending_approval',
            'completed' => 'status-paid',
            'cancelled' => 'status-cancelled',
            default => 'status-draft',
        };
    }

    public function displayLabel(): string
    {
        return $this->project_code.' - '.$this->name;
    }
}
