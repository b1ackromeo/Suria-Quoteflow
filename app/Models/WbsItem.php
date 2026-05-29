<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WbsItem extends Model
{
    public const STATUSES = [
        'active' => 'Active',
        'on_hold' => 'On hold',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public const COST_TYPES = [
        'material' => 'Material',
        'service' => 'Service',
        'subcontractor' => 'Subcontractor',
        'labour' => 'Labour',
        'other' => 'Other',
    ];

    protected $fillable = [
        'project_id',
        'parent_id',
        'code',
        'name',
        'description',
        'cost_type',
        'revenue_budget',
        'cost_budget',
        'delivery_gap_alert_percent',
        'received_not_invoiced_alert_percent',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'revenue_budget' => 'decimal:2',
        'cost_budget' => 'decimal:2',
        'delivery_gap_alert_percent' => 'decimal:2',
        'received_not_invoiced_alert_percent' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('code');
    }

    public function documentItems(): HasMany
    {
        return $this->hasMany(DocumentItem::class);
    }

    public function statusDisplay(): string
    {
        return self::STATUSES[$this->status] ?? ucwords(str_replace('_', ' ', (string) $this->status));
    }

    public function costTypeDisplay(): string
    {
        return self::COST_TYPES[$this->cost_type] ?? ucwords(str_replace('_', ' ', (string) $this->cost_type));
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
        return $this->code.' - '.$this->name;
    }
}
