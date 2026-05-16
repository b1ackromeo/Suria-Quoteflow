<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentBillingStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'sort_order',
        'stage_name',
        'condition_label',
        'percentage',
        'amount',
        'payment_term',
        'previously_invoiced',
        'current_invoice',
        'remaining_amount',
        'is_current',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'amount' => 'decimal:2',
        'previously_invoiced' => 'decimal:2',
        'current_invoice' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'is_current' => 'boolean',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
