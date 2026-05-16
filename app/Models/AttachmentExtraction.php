<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttachmentExtraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'attachment_id',
        'document_id',
        'status',
        'engine',
        'language',
        'raw_text',
        'extracted_fields',
        'verified_fields',
        'verification_method',
        'verification_notes',
        'supplier_confirmed',
        'recorded_total_confirmed',
        'error_message',
        'verified_by',
        'verified_at',
    ];

    protected $casts = [
        'extracted_fields' => 'array',
        'verified_fields' => 'array',
        'supplier_confirmed' => 'boolean',
        'recorded_total_confirmed' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
