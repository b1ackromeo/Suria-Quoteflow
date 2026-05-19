<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Document extends Model
{
    use HasFactory;

    public const TYPES = [
        'customer-quotations' => [
            'type' => 'customer_quotation',
            'label' => 'Customer quotations',
            'singular' => 'Customer quotation',
            'prefix' => 'CQ',
            'direction' => 'outgoing',
            'party' => 'customer',
        ],
        'customer-pos' => [
            'type' => 'customer_po',
            'label' => 'Customer POs received',
            'singular' => 'Customer PO received',
            'prefix' => 'CPO',
            'direction' => 'outgoing',
            'party' => 'customer',
        ],
        'customer-invoices' => [
            'type' => 'customer_invoice',
            'label' => 'Customer invoices',
            'singular' => 'Customer invoice',
            'prefix' => 'INV',
            'direction' => 'outgoing',
            'party' => 'customer',
        ],
        'purchase-requests' => [
            'type' => 'purchase_request',
            'label' => 'Purchase requests',
            'singular' => 'Purchase request',
            'prefix' => 'PR',
            'direction' => 'incoming',
            'party' => 'supplier_optional',
        ],
        'supplier-quotations' => [
            'type' => 'supplier_quotation',
            'label' => 'Supplier quotations',
            'singular' => 'Supplier quotation',
            'prefix' => 'SQ',
            'direction' => 'incoming',
            'party' => 'supplier',
        ],
        'supplier-pos' => [
            'type' => 'supplier_po',
            'label' => 'Purchase orders',
            'singular' => 'Purchase order',
            'prefix' => 'SPO',
            'direction' => 'incoming',
            'party' => 'supplier',
        ],
        'goods-receipts' => [
            'type' => 'goods_receipt',
            'label' => 'Goods receipts',
            'singular' => 'Goods receipt',
            'prefix' => 'GR',
            'direction' => 'incoming',
            'party' => 'supplier',
        ],
        'supplier-invoices' => [
            'type' => 'supplier_invoice',
            'label' => 'Supplier invoices',
            'singular' => 'Supplier invoice',
            'prefix' => 'SIN',
            'direction' => 'incoming',
            'party' => 'supplier',
        ],
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'pending_approval' => 'Pending approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'issued' => 'Issued',
        'fulfilled' => 'Delivered / completed',
        'received' => 'Received',
        'matched' => 'Matched',
        'part_paid' => 'Part paid',
        'paid' => 'Paid',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
    ];

    protected $fillable = [
        'type',
        'direction',
        'document_number',
        'external_reference',
        'customer_id',
        'supplier_id',
        'related_document_id',
        'source_type',
        'source_note',
        'project_name',
        'delivery_to',
        'status',
        'issue_date',
        'due_date',
        'currency',
        'payment_terms_type',
        'payment_due_days',
        'payment_terms_label',
        'progress_invoice_number',
        'progress_invoice_total',
        'billing_stage_name',
        'subtotal',
        'tax_total',
        'total',
        'notes',
        'terms',
        'created_by',
        'approved_by',
        'approved_at',
        'fulfilled_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'approved_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'payment_due_days' => 'integer',
        'progress_invoice_number' => 'integer',
        'progress_invoice_total' => 'integer',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            $legacyDefaultCurrency = CompanyProfile::defaults()['base_currency'];

            if (! filled($document->currency) || strtoupper((string) $document->currency) === $legacyDefaultCurrency) {
                $document->currency = CompanyProfile::active()->baseCurrency();
            }
        });

        static::updating(function (self $document): void {
            if (
                $document->type === 'purchase_request'
                && $document->isDirty('status')
                && $document->status === 'pending_approval'
                && ! $document->hasVerifiedSupplierQuoteEvidence()
                && ! $document->hasSupplierQuoteException()
            ) {
                throw ValidationException::withMessages([
                    'approval' => 'Verify the supplier quotation evidence, or record a quotation exception reason, before submitting this purchase request for approval.',
                ]);
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function relatedDocument(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_document_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DocumentItem::class);
    }

    public function billingStages(): HasMany
    {
        return $this->hasMany(DocumentBillingStage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function attachmentExtractions(): HasMany
    {
        return $this->hasMany(AttachmentExtraction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function partyName(): string
    {
        return $this->customer?->name ?? $this->supplier?->name ?? 'Internal';
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public function balanceDue(): float
    {
        return max(0, (float) $this->total - $this->paidAmount());
    }

    public function isInvoice(): bool
    {
        return in_array($this->type, ['customer_invoice', 'supplier_invoice'], true);
    }

    public function isPurchaseOrder(): bool
    {
        return in_array($this->type, ['customer_po', 'supplier_po'], true);
    }

    public function isQuotation(): bool
    {
        return in_array($this->type, ['customer_quotation', 'supplier_quotation'], true);
    }

    public function shouldPreviewGeneratedPdfOutput(): bool
    {
        return match ($this->type) {
            'customer_quotation' => in_array($this->status, ['issued', 'closed'], true),
            'purchase_request' => in_array($this->status, ['approved', 'closed'], true),
            'supplier_po' => in_array($this->status, ['issued', 'fulfilled', 'closed'], true),
            'goods_receipt' => in_array($this->status, ['received', 'closed'], true),
            'customer_invoice' => in_array($this->status, ['issued', 'part_paid', 'paid', 'closed'], true),
            default => false,
        };
    }

    public function paymentTermsDisplay(): string
    {
        if (filled($this->payment_terms_label)) {
            return $this->payment_terms_label;
        }

        if ($this->payment_terms_type === 'milestone') {
            return 'Milestone based';
        }

        if ($this->payment_due_days !== null) {
            return $this->payment_due_days === 0 ? 'Due upon invoice' : $this->payment_due_days.' days from invoice date';
        }

        return 'Not specified';
    }

    public static function formatQuantity(mixed $value, string $default = '0'): string
    {
        if (! filled($value)) {
            return $default;
        }

        $text = trim((string) $value);
        $normalized = str_replace(',', '', $text);

        if (! is_numeric($normalized)) {
            return $text;
        }

        $number = (float) $normalized;

        if (abs($number - round($number)) < 0.00001) {
            return (string) (int) round($number);
        }

        return rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
    }

    public function statusDisplay(): string
    {
        if ($this->type === 'customer_po' && $this->status === 'issued') {
            return 'Accepted';
        }

        return self::STATUSES[$this->status] ?? ucwords(str_replace('_', ' ', $this->status));
    }

    public function sourceTypeDisplay(): string
    {
        return match ($this->source_type) {
            'quotation' => 'From approved quotation',
            'direct_customer_po' => 'Direct customer PO',
            'customer_po' => 'From customer PO',
            'direct_invoice' => 'Direct invoice',
            'progress_claim' => 'Progress claim',
            'purchase_request' => 'From purchase request',
            'supplier_quote' => 'From supplier quotation',
            'quote_exception' => 'Quotation exception',
            'direct_supplier_po' => 'Direct purchase order',
            'supplier_po' => 'From purchase order',
            'goods_receipt' => 'From goods receipt',
            'direct_supplier_invoice' => 'Direct supplier invoice',
            default => $this->related_document_id ? 'Linked document' : 'Not specified',
        };
    }

    public function hasVerifiedSupplierQuoteEvidence(): bool
    {
        $this->loadMissing('attachments.extraction');

        return $this->attachments
            ->where('category', 'supplier_quote')
            ->contains(fn (Attachment $attachment) => $attachment->extraction?->status === 'verified');
    }

    public function hasSupplierQuoteException(): bool
    {
        return $this->source_type === 'quote_exception' && filled($this->source_note);
    }

    public static function metaForSlug(string $slug): array
    {
        abort_unless(isset(self::TYPES[$slug]), 404);

        return self::TYPES[$slug] + ['slug' => $slug];
    }

    public static function slugForType(string $type): string
    {
        foreach (self::TYPES as $slug => $meta) {
            if ($meta['type'] === $type) {
                return $slug;
            }
        }

        return 'customer-quotations';
    }

    public static function nextNumber(string $type): string
    {
        $meta = collect(self::TYPES)->firstWhere('type', $type);
        $year = (int) now()->format('Y');

        return DB::transaction(function () use ($type, $meta, $year) {
            $sequence = DocumentSequence::where('type', $type)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = DocumentSequence::create([
                    'type' => $type,
                    'year' => $year,
                    'last_number' => 0,
                ]);
            }

            $sequence->last_number++;
            $sequence->save();
            $number = str_pad((string) $sequence->last_number, 5, '0', STR_PAD_LEFT);

            return ($meta['prefix'] ?? 'DOC').'-'.$year.'-'.$number;
        });
    }
}
