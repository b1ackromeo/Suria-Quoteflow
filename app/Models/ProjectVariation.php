<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectVariation extends Model
{
    public const STATUSES = [
        'pending_review' => 'Pending review',
        'approved' => 'Approved',
        'not_proceeding' => 'Not proceeding',
    ];

    protected $fillable = [
        'project_id',
        'variation_number',
        'title',
        'status',
        'effective_date',
        'customer_value',
        'supplier_cost',
        'source_document_id',
        'notes',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'customer_value' => 'decimal:2',
        'supplier_cost' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    public function statusDisplay(): string
    {
        return self::STATUSES[$this->status] ?? ucwords(str_replace('_', ' ', (string) $this->status));
    }

    public function statusChipClass(): string
    {
        return match ($this->status) {
            'approved' => 'status-approved',
            'pending_review' => 'status-pending_approval',
            'not_proceeding' => 'status-cancelled',
            default => 'status-draft',
        };
    }

    public function marginImpact(): float
    {
        return (float) $this->customer_value - (float) $this->supplier_cost;
    }

    public function affectsCommercialBaseline(): bool
    {
        return $this->status === 'approved';
    }
}
