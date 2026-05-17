<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Document extends Model
{
    use HasFactory;

    public const TYPES = [
        'customer-quotations' => [
            'type' => 'customer_quotation',
            'label' => 'Customer Quotations',
            'singular' => 'Customer Quotation',
            'prefix' => 'CQ',
            'direction' => 'outgoing',
            'party' => 'customer',
        ],
        'customer-pos' => [
            'type' => 'customer_po',
            'label' => 'PO Received',
            'singular' => 'PO Received',
            'prefix' => 'CPO',
            'direction' => 'outgoing',
            'party' => 'customer',
        ],
        'customer-invoices' => [
            'type' => 'customer_invoice',
            'label' => 'Customer Invoices',
            'singular' => 'Customer Invoice',
            'prefix' => 'INV',
            'direction' => 'outgoing',
            'party' => 'customer',
        ],
        'purchase-requests' => [
            'type' => 'purchase_request',
            'label' => 'Purchase Requests',
            'singular' => 'Purchase Request',
            'prefix' => 'PR',
            'direction' => 'incoming',
            'party' => 'supplier_optional',
        ],
        'supplier-quotations' => [
            'type' => 'supplier_quotation',
            'label' => 'Supplier Quotations',
            'singular' => 'Supplier Quotation',
            'prefix' => 'SQ',
            'direction' => 'incoming',
            'party' => 'supplier',
        ],
        'supplier-pos' => [
            'type' => 'supplier_po',
            'label' => 'Purchase Orders',
            'singular' => 'Purchase Order',
            'prefix' => 'SPO',
            'direction' => 'incoming',
            'party' => 'supplier',
        ],
        'goods-receipts' => [
            'type' => 'goods_receipt',
            'label' => 'Receiving Records',
            'singular' => 'Receiving Record',
            'prefix' => 'GR',
            'direction' => 'incoming',
            'party' => 'supplier',
        ],
        'supplier-invoices' => [
            'type' => 'supplier_invoice',
            'label' => 'Supplier Invoices',
            'singular' => 'Supplier Invoice',
            'prefix' => 'SIN',
            'direction' => 'incoming',
            'party' => 'supplier',
        ],
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'pending_approval' => 'Pending Approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'issued' => 'Issued',
        'fulfilled' => 'Delivered / Completed',
        'received' => 'Received',
        'matched' => 'Matched',
        'part_paid' => 'Part Paid',
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
            return 'Milestone-Based';
        }

        if ($this->payment_due_days !== null) {
            return $this->payment_due_days === 0 ? 'Due upon invoice' : $this->payment_due_days.' days from invoice date';
        }

        return 'Not specified';
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
            'direct_customer_po' => 'Direct PO received',
            'customer_po' => 'From PO received',
            'direct_invoice' => 'Direct invoice',
            'progress_claim' => 'Progress claim',
            'purchase_request' => 'From purchase request',
            'supplier_quote' => 'From supplier quotation',
            'quote_exception' => 'Quote exception',
            'direct_supplier_po' => 'Direct purchase order',
            'supplier_po' => 'From purchase order',
            'goods_receipt' => 'From receiving record',
            'direct_supplier_invoice' => 'Direct supplier invoice',
            default => $this->related_document_id ? 'Linked document' : 'Not specified',
        };
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
