<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\AttachmentExtraction;
use App\Models\Approval;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\Documents\PaymentEligibilityService;
use App\Services\Invoices\SupplierInvoiceMatchingService;
use App\Services\Invoices\SupplierInvoiceVerificationService;
use App\Services\Ocr\TesseractInvoiceExtractor;
use App\Support\Audit;
use App\Support\SearchFilters;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class DocumentController extends Controller
{
    private const DIRECT_EXCEPTION_SOURCE_TYPES = [
        'direct_customer_po',
        'direct_invoice',
        'direct_supplier_po',
        'direct_receipt',
        'direct_supplier_invoice',
    ];

    public function __construct(
        private SupplierInvoiceVerificationService $supplierInvoiceVerification,
        private SupplierInvoiceMatchingService $supplierInvoiceMatching
    ) {
    }

    public function index(Request $request, string $module): View
    {
        $meta = Document::metaForSlug($module);
        $query = $this->baseQuery($meta)
            ->when(trim($request->string('q')->toString()), fn ($query, string $q) => SearchFilters::documents($query, $q))
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status))
            ->when($request->date('from'), fn ($query, $date) => $query->whereDate('issue_date', '>=', $date))
            ->when($request->date('to'), fn ($query, $date) => $query->whereDate('issue_date', '<=', $date));

        if (in_array($meta['type'], ['customer_quotation', 'customer_po', 'customer_invoice', 'purchase_request', 'supplier_quotation', 'supplier_po', 'goods_receipt', 'supplier_invoice'], true)) {
            $query->with(['items.product', 'billingStages']);
        }

        if (in_array($meta['type'], ['customer_po', 'purchase_request', 'supplier_quotation', 'goods_receipt', 'supplier_invoice'], true)) {
            $query->with(['items.product', 'billingStages', 'attachments.uploader', 'attachments.extraction']);
        }

        return view('documents.index', [
            'meta' => $meta,
            'documents' => $query->latest('issue_date')->latest('id')->paginate(20)->withQueryString(),
            'statuses' => Document::STATUSES,
        ]);
    }

    public function create(Request $request, string $module): View
    {
        $meta = Document::metaForSlug($module);
        $this->ensureWriteAccess($meta);

        $document = new Document([
            'type' => $meta['type'],
            'direction' => $meta['direction'],
            'issue_date' => now()->toDateString(),
            'currency' => 'MYR',
            'payment_terms_type' => 'standard',
            'payment_due_days' => 30,
        ]);

        $sourceId = $request->integer('source_document_id') ?: $request->integer('related_document_id');
        if ($sourceId) {
            $sourceDocument = Document::with(['items', 'billingStages'])->find($sourceId);
            if ($sourceDocument) {
                $this->prefillDocumentFromSource($document, $meta, $sourceDocument);
            }
        }

        return view('documents.form', $this->formData($meta, $document));
    }

    public function store(Request $request, string $module): RedirectResponse
    {
        $meta = Document::metaForSlug($module);
        $this->ensureWriteAccess($meta);
        $data = $this->validated($request, $meta);

        $document = DB::transaction(function () use ($data, $meta) {
            $document = Document::create([
                'type' => $meta['type'],
                'direction' => $meta['direction'],
                'document_number' => Document::nextNumber($meta['type']),
                'external_reference' => $data['external_reference'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'related_document_id' => $data['related_document_id'] ?? null,
                'source_type' => $data['source_type'] ?? null,
                'source_note' => $data['source_note'] ?? null,
                'project_name' => $data['project_name'] ?? null,
                'delivery_to' => $data['delivery_to'] ?? null,
                'status' => 'draft',
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'currency' => strtoupper($data['currency'] ?? 'MYR'),
                'payment_terms_type' => $data['payment_terms_type'] ?? 'standard',
                'payment_due_days' => $data['payment_due_days'] ?? null,
                'payment_terms_label' => $data['payment_terms_label'] ?? null,
                'progress_invoice_number' => $data['progress_invoice_number'] ?? null,
                'progress_invoice_total' => $data['progress_invoice_total'] ?? null,
                'billing_stage_name' => $data['billing_stage_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->syncItems($document, $data['items'] ?? [], $data['document_tax_rate'] ?? null);
            $this->syncBillingStages($document, $data['billing_stages'] ?? []);
            Audit::record('document_created', $document, null, $document->fresh(['items'])->toArray());

            return $document;
        });

        return redirect()->route('documents.show', $document)->with('status', $meta['singular'].' created.');
    }

    public function show(Document $document, PaymentEligibilityService $paymentEligibility): View
    {
        $document->load(['customer', 'supplier', 'relatedDocument', 'items.product', 'billingStages', 'payments.creator', 'approvals.requester', 'approvals.decider', 'attachments.uploader', 'attachments.extraction.verifier', 'creator', 'approver']);
        $supplierInvoiceVerification = $document->type === 'supplier_invoice'
            ? $this->supplierInvoiceVerification->summary($document)
            : null;
        $supplierInvoiceMatching = $document->type === 'supplier_invoice'
            ? $this->supplierInvoiceMatching->checklist($document)
            : null;

        return view('documents.show', [
            'document' => $document,
            'meta' => Document::metaForSlug(Document::slugForType($document->type)),
            'statuses' => Document::STATUSES,
            'paymentEligible' => $paymentEligibility->canRecordPayment($document),
            'paymentBlockedReason' => $paymentEligibility->blockedReason($document),
            'supplierInvoiceVerification' => $supplierInvoiceVerification,
            'supplierInvoiceMatching' => $supplierInvoiceMatching,
        ]);
    }

    public function edit(Document $document): View
    {
        $meta = Document::metaForSlug(Document::slugForType($document->type));
        $this->ensureWriteAccess($meta);
        abort_if(in_array($document->status, ['paid', 'closed', 'cancelled'], true), 422, 'Closed documents cannot be edited.');

        return view('documents.form', $this->formData($meta, $document->load(['items', 'billingStages'])));
    }

    public function update(Request $request, Document $document): RedirectResponse
    {
        $meta = Document::metaForSlug(Document::slugForType($document->type));
        $this->ensureWriteAccess($meta);
        abort_if(in_array($document->status, ['paid', 'closed', 'cancelled'], true), 422, 'Closed documents cannot be edited.');

        $data = $this->validated($request, $meta);
        $before = $document->load('items')->toArray();

        DB::transaction(function () use ($document, $data) {
            $document->update([
                'external_reference' => $data['external_reference'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'related_document_id' => $data['related_document_id'] ?? null,
                'source_type' => $data['source_type'] ?? null,
                'source_note' => $data['source_note'] ?? null,
                'project_name' => $data['project_name'] ?? null,
                'delivery_to' => $data['delivery_to'] ?? null,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'] ?? null,
                'currency' => strtoupper($data['currency'] ?? 'MYR'),
                'payment_terms_type' => $data['payment_terms_type'] ?? 'standard',
                'payment_due_days' => $data['payment_due_days'] ?? null,
                'payment_terms_label' => $data['payment_terms_label'] ?? null,
                'progress_invoice_number' => $data['progress_invoice_number'] ?? null,
                'progress_invoice_total' => $data['progress_invoice_total'] ?? null,
                'billing_stage_name' => $data['billing_stage_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);
            $this->syncItems($document, $data['items'] ?? [], $data['document_tax_rate'] ?? null);
            $this->syncBillingStages($document, $data['billing_stages'] ?? []);
        });

        Audit::record('document_updated', $document, $before, $document->fresh(['items'])->toArray());

        return redirect()->route('documents.show', $document)->with('status', 'Document updated.');
    }

    public function submit(Document $document): RedirectResponse
    {
        $meta = Document::metaForSlug(Document::slugForType($document->type));
        $this->ensureWriteAccess($meta);
        abort_unless(in_array($document->status, ['draft', 'rejected'], true), 422, 'Only draft or rejected documents can be submitted.');

        $blockingIssues = $this->supplierInvoiceVerification->blockingIssues($document);
        if ($blockingIssues !== []) {
            throw ValidationException::withMessages([
                'approval' => 'This supplier invoice cannot be submitted yet. '.implode(' ', $blockingIssues),
            ]);
        }

        $before = $document->only(['status']);
        DB::transaction(function () use ($document) {
            $document->update(['status' => 'pending_approval']);
            Approval::create([
                'document_id' => $document->id,
                'requested_by' => auth()->id(),
                'status' => 'pending',
            ]);
        });
        Audit::record('approval_requested', $document, $before, ['status' => 'pending_approval']);

        return back()->with('status', 'Submitted for approval.');
    }

    public function approve(Request $request, Document $document): RedirectResponse
    {
        $this->ensureApprover();
        abort_unless($document->status === 'pending_approval', 422, 'Only pending documents can be approved.');

        $comment = $request->validate(['comment' => ['nullable', 'string']])['comment'] ?? null;
        $before = $document->only(['status', 'approved_by', 'approved_at']);

        DB::transaction(function () use ($document, $comment) {
            $document->approvals()->where('status', 'pending')->latest()->first()?->update([
                'status' => 'approved',
                'decided_by' => auth()->id(),
                'comment' => $comment,
                'decided_at' => now(),
            ]);

            $document->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });
        Audit::record('document_approved', $document, $before, $document->only(['status', 'approved_by', 'approved_at']));

        return back()->with('status', 'Approved.');
    }

    public function reject(Request $request, Document $document): RedirectResponse
    {
        $this->ensureApprover();
        abort_unless($document->status === 'pending_approval', 422, 'Only pending documents can be rejected.');

        $comment = $request->validate(['comment' => ['nullable', 'string']])['comment'] ?? null;
        $before = $document->only(['status']);

        DB::transaction(function () use ($document, $comment) {
            $document->approvals()->where('status', 'pending')->latest()->first()?->update([
                'status' => 'rejected',
                'decided_by' => auth()->id(),
                'comment' => $comment,
                'decided_at' => now(),
            ]);

            $document->update(['status' => 'rejected']);
        });
        Audit::record('document_rejected', $document, $before, ['status' => 'rejected']);

        return back()->with('status', 'Rejected.');
    }

    public function transition(Request $request, Document $document, string $action): RedirectResponse
    {
        $meta = Document::metaForSlug(Document::slugForType($document->type));
        $this->ensureWriteAccess($meta);

        $status = match ($action) {
            'issue' => 'issued',
            'fulfill' => 'fulfilled',
            'receive' => 'received',
            'match' => 'matched',
            'close' => 'closed',
            'cancel' => 'cancelled',
            default => abort(404),
        };

        $allowed = [
            'issue' => ['approved'],
            'fulfill' => ['issued', 'approved'],
            'receive' => ['issued', 'approved'],
            'match' => ['received', 'issued', 'approved'],
            'close' => match (true) {
                $document->isInvoice() => ['paid'],
                $document->type === 'customer_po' => ['fulfilled'],
                default => [],
            },
            'cancel' => ['draft', 'rejected', 'approved', 'issued'],
        ];

        abort_unless($this->transitionAppliesToDocument($document, $action), 422, 'This workflow action does not apply to this document type.');
        abort_unless(in_array($document->status, $allowed[$action], true), 422, 'This status change is not allowed.');

        $matchingOverride = null;

        if ($action === 'match' && $document->type === 'supplier_invoice') {
            $matchingChecklist = $this->supplierInvoiceMatching->checklist($document);

            if (! $matchingChecklist['passes']) {
                $overrideReason = trim((string) $request->input('matching_override_reason', ''));

                if ($overrideReason === '') {
                    throw ValidationException::withMessages([
                        'matching' => 'Complete the matching checklist before marking this invoice matched. '.implode(' ', $matchingChecklist['blocking_messages']),
                    ]);
                }

                abort_unless($request->user()->hasRole('admin', 'manager'), 403);

                $request->validate([
                    'matching_override_reason' => ['required', 'string', 'max:2000'],
                ], [
                    'matching_override_reason.required' => 'Add matching notes before using manager override.',
                ]);

                $matchingOverride = [
                    'reason' => $overrideReason,
                    'checklist' => $matchingChecklist['checks'],
                ];
            }
        }

        $before = $document->only(['status', 'fulfilled_at']);
        $payload = ['status' => $status];
        if ($action === 'fulfill') {
            $payload['fulfilled_at'] = now();
        }

        $document->update($payload);
        Audit::record('document_'.$action, $document, $before, $document->only(['status', 'fulfilled_at']));

        if ($matchingOverride) {
            Audit::record('supplier_invoice_match_override', $document, $before, [
                'status' => $document->status,
                'override_reason' => $matchingOverride['reason'],
                'checklist' => $matchingOverride['checklist'],
            ]);
        }

        $message = $matchingOverride
            ? $document->statusDisplay().' recorded with audited override.'
            : $document->statusDisplay().' recorded.';

        return back()->with('status', $message);
    }

    public function uploadAttachment(Request $request, Document $document, TesseractInvoiceExtractor $extractor): RedirectResponse
    {
        $meta = Document::metaForSlug(Document::slugForType($document->type));
        $this->ensureWriteAccess($meta);

        $data = $request->validate([
            'attachment' => ['required', 'file', 'max:8192'],
            'category' => ['nullable', 'string', 'max:80'],
        ]);

        $file = $data['attachment'];
        $name = Str::uuid().'-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $extension = $file->getClientOriginalExtension();
        $path = $file->storeAs('attachments/'.$document->id, $name.($extension ? '.'.$extension : ''));

        $attachment = Attachment::create([
            'document_id' => $document->id,
            'category' => $data['category'] ?? 'supporting_document',
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        Audit::record('attachment_uploaded', $attachment, null, $attachment->toArray());

        $message = 'Attachment uploaded.';

        if ($document->type === 'supplier_invoice') {
            $extraction = $this->storeExtractionDraft($attachment, $extractor);

            if ($extraction?->status === 'processed') {
                $message = 'Attachment uploaded. OCR extraction draft is ready for verification.';
            } elseif ($extraction?->status === 'failed') {
                $message = 'Attachment uploaded. OCR extraction could not run yet; check the extraction message on the invoice page.';
            }
        }

        return back()->with('status', $message);
    }

    public function extractAttachment(Attachment $attachment, TesseractInvoiceExtractor $extractor): RedirectResponse
    {
        $attachment->load('document');
        abort_if(! $attachment->document, 404);

        $meta = Document::metaForSlug(Document::slugForType($attachment->document->type));
        $this->ensureWriteAccess($meta);
        abort_unless($attachment->document->type === 'supplier_invoice', 422, 'OCR extraction is only available for supplier invoice documents.');

        $extraction = $this->storeExtractionDraft($attachment, $extractor, true);

        if ($extraction?->status === 'processed') {
            return back()->with('status', 'OCR extraction draft is ready. Verify the fields before approval.');
        }

        return back()->withErrors([
            'extraction' => $extraction?->error_message ?: 'OCR extraction could not be completed.',
        ]);
    }

    public function verifyExtraction(Request $request, AttachmentExtraction $extraction): RedirectResponse
    {
        $extraction->load(['attachment.document']);
        $document = $extraction->document ?: $extraction->attachment?->document;
        abort_if(! $document, 404);

        $meta = Document::metaForSlug(Document::slugForType($document->type));
        $this->ensureWriteAccess($meta);
        abort_unless($document->type === 'supplier_invoice', 422, 'Extraction verification is only available for supplier invoices.');

        $data = $request->validate([
            'fields' => ['required', 'array'],
            'fields.supplier_name' => ['nullable', 'string', 'max:255'],
            'fields.invoice_number' => ['required', 'string', 'max:255'],
            'fields.invoice_date' => ['required', 'string', 'max:80'],
            'fields.po_number' => ['nullable', 'string', 'max:255'],
            'fields.subtotal' => ['nullable', 'string', 'max:80'],
            'fields.tax_total' => ['nullable', 'string', 'max:80'],
            'fields.total' => ['nullable', 'string', 'max:80'],
            'fields.payment_terms' => ['nullable', 'string', 'max:255'],
            'supplier_confirmed' => ['accepted'],
            'recorded_total_confirmed' => ['nullable', 'boolean'],
            'verification_notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'fields.invoice_number.required' => 'Enter the supplier invoice number before verifying.',
            'fields.invoice_date.required' => 'Enter the invoice date before verifying.',
            'supplier_confirmed.accepted' => 'Confirm the invoice supplier matches this supplier record.',
        ]);

        $fields = collect($data['fields'])
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => filled($value))
            ->all();

        $recordedTotalConfirmed = $request->boolean('recorded_total_confirmed');
        $verificationNotes = $request->string('verification_notes')->trim()->toString() ?: null;
        if (! filled($fields['total'] ?? null) && ! $recordedTotalConfirmed) {
            throw ValidationException::withMessages([
                'recorded_total_confirmed' => 'Enter the invoice total or confirm the recorded total was checked.',
            ]);
        }

        DB::transaction(function () use ($extraction, $document, $fields, $recordedTotalConfirmed, $verificationNotes) {
            $before = $extraction->only(['status', 'verified_by', 'verified_at', 'verification_method', 'verification_notes', 'supplier_confirmed', 'recorded_total_confirmed']);

            $extraction->update([
                'status' => 'verified',
                'verified_fields' => $fields,
                'verification_method' => SupplierInvoiceVerificationService::METHOD_OCR_ASSISTED,
                'verification_notes' => $verificationNotes,
                'supplier_confirmed' => true,
                'recorded_total_confirmed' => $recordedTotalConfirmed,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]);

            if (($extraction->attachment?->category ?? null) === 'invoice_copy') {
                $this->applyVerifiedSupplierInvoiceFields($document, $fields);
            }

            Audit::record('attachment_extraction_verified', $extraction, $before, [
                'status' => 'verified',
                'verified_by' => auth()->id(),
                'verified_field_keys' => array_keys($fields),
                'verification_method' => SupplierInvoiceVerificationService::METHOD_OCR_ASSISTED,
            ]);
        });

        return back()->with('status', 'Supplier invoice extraction verified.');
    }

    public function verifySupplierInvoiceDetails(Request $request, Document $document): RedirectResponse
    {
        $meta = Document::metaForSlug(Document::slugForType($document->type));
        $this->ensureWriteAccess($meta);
        abort_unless($document->type === 'supplier_invoice', 422, 'Manual verification is only available for supplier invoices.');

        $document->loadMissing('attachments.extraction');
        $invoiceCopy = $document->attachments->firstWhere('category', 'invoice_copy');

        if (! $invoiceCopy) {
            throw ValidationException::withMessages([
                'verification' => 'Upload the supplier invoice file as Invoice copy before verifying details.',
            ]);
        }

        $data = $request->validate([
            'verification_method' => ['required', Rule::in([
                SupplierInvoiceVerificationService::METHOD_MANUAL,
                SupplierInvoiceVerificationService::METHOD_EXTERNAL,
            ])],
            'fields' => ['required', 'array'],
            'fields.supplier_name' => ['nullable', 'string', 'max:255'],
            'fields.invoice_number' => ['required', 'string', 'max:255'],
            'fields.invoice_date' => ['required', 'string', 'max:80'],
            'fields.po_number' => ['nullable', 'string', 'max:255'],
            'fields.subtotal' => ['nullable', 'string', 'max:80'],
            'fields.tax_total' => ['nullable', 'string', 'max:80'],
            'fields.total' => ['nullable', 'string', 'max:80'],
            'fields.payment_terms' => ['nullable', 'string', 'max:255'],
            'supplier_confirmed' => ['accepted'],
            'recorded_total_confirmed' => ['nullable', 'boolean'],
            'verification_notes' => ['required', 'string', 'max:2000'],
        ], [
            'fields.invoice_number.required' => 'Enter the supplier invoice number before verifying.',
            'fields.invoice_date.required' => 'Enter the invoice date before verifying.',
            'supplier_confirmed.accepted' => 'Confirm the invoice supplier matches this supplier record.',
            'verification_notes.required' => 'Add verification notes for manual or external verification.',
        ]);

        $fields = collect($data['fields'])
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => filled($value))
            ->all();

        $recordedTotalConfirmed = $request->boolean('recorded_total_confirmed');
        if (! filled($fields['total'] ?? null) && ! $recordedTotalConfirmed) {
            throw ValidationException::withMessages([
                'recorded_total_confirmed' => 'Enter the invoice total or confirm the recorded total was checked.',
            ]);
        }

        DB::transaction(function () use ($invoiceCopy, $document, $fields, $data, $recordedTotalConfirmed) {
            $extraction = $invoiceCopy->extraction ?: new AttachmentExtraction([
                'attachment_id' => $invoiceCopy->id,
                'document_id' => $document->id,
                'engine' => $data['verification_method'],
                'language' => (string) config('ocr.language', 'eng'),
                'extracted_fields' => [],
            ]);
            $before = $extraction->exists
                ? $extraction->only(['status', 'verified_by', 'verified_at', 'verification_method', 'verification_notes', 'supplier_confirmed', 'recorded_total_confirmed'])
                : null;

            $extraction->fill([
                'document_id' => $document->id,
                'status' => 'verified',
                'verified_fields' => $fields,
                'verification_method' => $data['verification_method'],
                'verification_notes' => trim((string) $data['verification_notes']),
                'supplier_confirmed' => true,
                'recorded_total_confirmed' => $recordedTotalConfirmed,
                'error_message' => null,
                'verified_by' => auth()->id(),
                'verified_at' => now(),
            ]);
            $extraction->save();

            $this->applyVerifiedSupplierInvoiceFields($document, $fields);

            Audit::record('supplier_invoice_details_verified', $extraction, $before, [
                'document_id' => $document->id,
                'verification_method' => $data['verification_method'],
                'verified_by' => auth()->id(),
                'verified_field_keys' => array_keys($fields),
            ]);
        });

        return back()->with('status', 'Supplier invoice details verified.');
    }

    public function downloadAttachment(Attachment $attachment)
    {
        abort_unless(Storage::exists($attachment->path), 404);

        return Storage::download($attachment->path, $attachment->original_name);
    }

    public function previewAttachment(Attachment $attachment)
    {
        abort_unless(Storage::exists($attachment->path), 404);
        abort_unless($attachment->isPreviewable(), 415);

        $path = Storage::path($attachment->path);
        $filename = str_replace(['"', "\r", "\n"], '', $attachment->original_name);

        return response()->file($path, [
            'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function storeExtractionDraft(Attachment $attachment, TesseractInvoiceExtractor $extractor, bool $failIfUnsupported = false): ?AttachmentExtraction
    {
        if (! config('ocr.enabled', true)) {
            return null;
        }

        if (! $extractor->canExtract($attachment)) {
            if ($failIfUnsupported) {
                throw ValidationException::withMessages([
                    'extraction' => 'Only supplier invoice PDF and image files can be extracted.',
                ]);
            }

            return null;
        }

        $attachment->loadMissing('document');
        abort_if(! $attachment->document, 404);

        try {
            $result = $extractor->extract($attachment);
            $payload = [
                'document_id' => $attachment->document_id,
                'status' => 'processed',
                'engine' => $result['engine'] ?? 'tesseract',
                'language' => $result['language'] ?? (string) config('ocr.language', 'eng'),
                'raw_text' => $result['raw_text'] ?? null,
                'extracted_fields' => $result['extracted_fields'] ?? [],
                'verified_fields' => null,
                'error_message' => null,
                'verified_by' => null,
                'verified_at' => null,
            ];
        } catch (Throwable $exception) {
            $payload = [
                'document_id' => $attachment->document_id,
                'status' => 'failed',
                'engine' => 'tesseract',
                'language' => (string) config('ocr.language', 'eng'),
                'raw_text' => null,
                'extracted_fields' => [],
                'verified_fields' => null,
                'error_message' => Str::limit($exception->getMessage(), 1000),
                'verified_by' => null,
                'verified_at' => null,
            ];
        }

        $extraction = AttachmentExtraction::updateOrCreate(
            ['attachment_id' => $attachment->id],
            $payload
        );

        Audit::record('attachment_extraction_'.$extraction->status, $extraction, null, [
            'attachment_id' => $extraction->attachment_id,
            'document_id' => $extraction->document_id,
            'status' => $extraction->status,
            'engine' => $extraction->engine,
            'field_keys' => array_keys($extraction->extracted_fields ?? []),
            'error_message' => $extraction->error_message,
        ]);

        return $extraction;
    }

    private function applyVerifiedSupplierInvoiceFields(Document $document, array $fields): void
    {
        $payload = [];

        if (filled($fields['invoice_number'] ?? null)) {
            $payload['external_reference'] = $fields['invoice_number'];
        }

        if (filled($fields['invoice_date'] ?? null)) {
            try {
                $payload['issue_date'] = Carbon::parse($fields['invoice_date'])->toDateString();
            } catch (Throwable) {
                // Keep the verified OCR value stored on the extraction if it is not a parseable date.
            }
        }

        foreach (['subtotal', 'tax_total', 'total'] as $moneyField) {
            $amount = $this->decimalFromExtraction($fields[$moneyField] ?? null);

            if ($amount !== null) {
                $payload[$moneyField] = $amount;
            }
        }

        if (filled($fields['payment_terms'] ?? null)) {
            $payload['payment_terms_label'] = $fields['payment_terms'];
        }

        if ($payload !== []) {
            $document->update($payload);
        }
    }

    private function decimalFromExtraction(?string $value): ?float
    {
        if (! filled($value)) {
            return null;
        }

        $normalized = preg_replace('/[^\d.\-]/', '', (string) $value);

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return round((float) $normalized, 2);
    }

    public function pdf(Document $document)
    {
        return $this->documentPdf($document)->stream($document->document_number.'.pdf');
    }

    public function downloadPdf(Document $document)
    {
        return $this->documentPdf($document)->download($document->document_number.'.pdf');
    }

    private function documentPdf(Document $document)
    {
        $document->load(['customer', 'supplier', 'relatedDocument.items', 'items.product', 'billingStages', 'payments', 'attachments', 'creator', 'approver']);

        return Pdf::loadView('documents.pdf', [
            'document' => $document,
            'meta' => Document::metaForSlug(Document::slugForType($document->type)),
        ])->setPaper('a4');
    }

    private function transitionAppliesToDocument(Document $document, string $action): bool
    {
        return match ($action) {
            'issue' => in_array($document->type, ['customer_quotation', 'customer_po', 'customer_invoice', 'supplier_po'], true),
            'fulfill' => $document->type === 'customer_po',
            'receive' => $document->type === 'goods_receipt',
            'match' => $document->type === 'supplier_invoice',
            'close' => $document->isInvoice() || $document->type === 'customer_po',
            default => true,
        };
    }

    public function exportCsv(Request $request, string $module)
    {
        $meta = Document::metaForSlug($module);
        $query = $this->baseQuery($meta)
            ->when($request->string('status')->toString(), fn ($query, $status) => $query->where('status', $status));

        $filename = $module.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Number', 'Status', 'Party', 'Issue Date', 'Due Date', 'Currency', 'Subtotal', 'Tax', 'Total']);

            $query->orderBy('issue_date')->chunk(200, function ($documents) use ($handle) {
                foreach ($documents as $document) {
                    fputcsv($handle, [
                        $document->document_number,
                        $document->statusDisplay(),
                        $document->partyName(),
                        optional($document->issue_date)->format('Y-m-d'),
                        optional($document->due_date)->format('Y-m-d'),
                        $document->currency,
                        $document->subtotal,
                        $document->tax_total,
                        $document->total,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function baseQuery(array $meta)
    {
        return Document::with(['customer', 'supplier'])
            ->where('type', $meta['type']);
    }

    private function formData(array $meta, Document $document): array
    {
        $allowedRelatedTypes = $this->allowedRelatedTypes($meta['type']);

        $relatedDocuments = Document::where('id', '!=', $document->id ?? 0)
            ->where('direction', $meta['direction'])
            ->when($allowedRelatedTypes !== [], fn ($query) => $query->whereIn('type', $allowedRelatedTypes))
            ->when($allowedRelatedTypes === [], fn ($query) => $query->whereRaw('1 = 0'));

        if ($meta['type'] === 'goods_receipt') {
            $relatedDocuments->where('status', 'issued');
        } elseif (! in_array($meta['type'], ['customer_quotation'], true)) {
            $relatedDocuments->whereIn('status', ['approved', 'issued', 'fulfilled', 'received', 'matched']);
        }

        $relatedDocuments = $relatedDocuments
            ->latest('issue_date')
            ->limit(100)
            ->get();

        return [
            'meta' => $meta,
            'document' => $document,
            'customers' => Customer::query()
                ->where(function ($query) use ($document) {
                    $query->where('is_active', true)
                        ->when($document->customer_id, fn ($query, $customerId) => $query->orWhere('id', $customerId));
                })
                ->orderBy('name')
                ->get(),
            'suppliers' => Supplier::query()
                ->where(function ($query) use ($document) {
                    $query->where('is_active', true)
                        ->when($document->supplier_id, fn ($query, $supplierId) => $query->orWhere('id', $supplierId));
                })
                ->orderBy('name')
                ->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(),
            'relatedDocuments' => $relatedDocuments,
        ];
    }

    private function prefillDocumentFromSource(Document $document, array $meta, Document $sourceDocument): void
    {
        $allowedRelatedTypes = $this->allowedRelatedTypes($meta['type']);

        if ($sourceDocument->direction !== $meta['direction'] || ! in_array($sourceDocument->type, $allowedRelatedTypes, true)) {
            return;
        }

        if ($meta['type'] === 'goods_receipt' && $sourceDocument->status !== 'issued') {
            return;
        }

        $document->forceFill([
            'related_document_id' => $sourceDocument->id,
            'customer_id' => $sourceDocument->customer_id,
            'supplier_id' => $sourceDocument->supplier_id,
            'source_type' => $this->sourceTypeForRelatedDocument($meta['type'], $sourceDocument->type),
            'project_name' => $sourceDocument->project_name,
            'delivery_to' => $sourceDocument->delivery_to,
            'currency' => $sourceDocument->currency ?: 'MYR',
            'payment_terms_type' => $sourceDocument->payment_terms_type ?: 'standard',
            'payment_due_days' => $sourceDocument->payment_due_days,
            'payment_terms_label' => $sourceDocument->payment_terms_label,
            'notes' => $sourceDocument->notes,
            'terms' => $sourceDocument->terms,
        ]);

        if (! in_array($meta['type'], ['supplier_invoice', 'customer_invoice'], true)) {
            $document->external_reference = $sourceDocument->document_number;
        }

        if ($meta['type'] === 'goods_receipt') {
            $document->external_reference = null;
            $document->notes = null;
            $document->terms = null;
            $document->payment_terms_type = 'standard';
            $document->payment_due_days = null;
            $document->payment_terms_label = null;
        }

        $document->setRelation('items', $sourceDocument->items->map(fn ($item) => $item->replicate(['document_id'])));
        $document->setRelation('billingStages', $sourceDocument->billingStages->map(fn ($stage) => $stage->replicate(['document_id'])));
    }

    private function sourceTypeForRelatedDocument(string $targetType, string $sourceType): ?string
    {
        return match ([$targetType, $sourceType]) {
            ['supplier_quotation', 'purchase_request'] => 'purchase_request',
            ['supplier_quotation', 'supplier_quotation'] => 'supplier_quote',
            ['supplier_po', 'supplier_quotation'] => 'supplier_quote',
            ['supplier_po', 'purchase_request'] => 'purchase_request',
            ['goods_receipt', 'supplier_po'] => 'supplier_po',
            ['supplier_invoice', 'goods_receipt'] => 'goods_receipt',
            ['supplier_invoice', 'supplier_po'] => 'supplier_po',
            ['customer_po', 'customer_quotation'] => 'quotation',
            ['customer_invoice', 'customer_po'] => 'customer_po',
            ['customer_invoice', 'customer_quotation'] => 'quotation',
            default => null,
        };
    }

    private function allowedRelatedTypes(string $type): array
    {
        return match ($type) {
            'customer_quotation' => ['customer_quotation'],
            'customer_po' => ['customer_quotation'],
            'customer_invoice' => ['customer_po', 'customer_quotation'],
            'supplier_quotation' => ['purchase_request', 'supplier_quotation'],
            'supplier_po' => ['purchase_request', 'supplier_quotation'],
            'goods_receipt' => ['supplier_po'],
            'supplier_invoice' => ['goods_receipt', 'supplier_po'],
            default => [],
        };
    }

    private function validated(Request $request, array $meta): array
    {
        $quantityMinRule = $meta['type'] === 'goods_receipt' ? 'min:0' : 'min:0.001';

        $data = $request->validate([
            'external_reference' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'related_document_id' => ['nullable', 'exists:documents,id'],
            'source_type' => ['nullable', 'string', 'max:80'],
            'source_note' => ['nullable', 'string'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'delivery_to' => ['nullable', 'string'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_terms_type' => ['nullable', 'in:standard,milestone'],
            'payment_due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'payment_terms_label' => ['nullable', 'string', 'max:255'],
            'document_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'progress_invoice_number' => ['nullable', 'integer', 'min:1', 'max:99'],
            'progress_invoice_total' => ['nullable', 'integer', 'min:1', 'max:99'],
            'billing_stage_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'terms' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'numeric', $quantityMinRule, 'max:999999999'],
            'items.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'billing_stages' => ['nullable', 'array'],
            'billing_stages.*.stage_name' => ['nullable', 'string', 'max:255'],
            'billing_stages.*.condition_label' => ['nullable', 'string', 'max:500'],
            'billing_stages.*.percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'billing_stages.*.amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'billing_stages.*.payment_term' => ['nullable', 'string', 'max:255'],
            'billing_stages.*.previously_invoiced' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'billing_stages.*.current_invoice' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'billing_stages.*.remaining_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'billing_stages.*.is_current' => ['nullable', 'boolean'],
        ]);

        $data['source_type'] = is_string($data['source_type'] ?? null) ? trim($data['source_type']) : ($data['source_type'] ?? null);
        $data['source_note'] = is_string($data['source_note'] ?? null) ? trim($data['source_note']) : ($data['source_note'] ?? null);

        if ($meta['type'] === 'goods_receipt' && ($data['source_type'] ?? null) !== 'direct_receipt') {
            $data['source_type'] = 'supplier_po';
        }

        if ($meta['party'] === 'customer' && empty($data['customer_id'])) {
            throw ValidationException::withMessages(['customer_id' => 'Select a customer.']);
        }

        if ($meta['party'] === 'supplier' && empty($data['supplier_id'])) {
            throw ValidationException::withMessages(['supplier_id' => 'Select a supplier.']);
        }

        if (in_array($data['source_type'] ?? null, self::DIRECT_EXCEPTION_SOURCE_TYPES, true) && ! filled($data['source_note'] ?? null)) {
            throw ValidationException::withMessages([
                'source_note' => $this->directExceptionSourceNoteMessage($data['source_type']),
            ]);
        }

        if ($meta['type'] === 'goods_receipt' && ($data['source_type'] ?? null) === 'supplier_po' && empty($data['related_document_id'])) {
            throw ValidationException::withMessages(['related_document_id' => 'Select the issued purchase order this receipt is recorded against, or choose Direct receipt exception.']);
        }

        $relatedDocument = null;

        if (! empty($data['related_document_id'])) {
            $relatedDocument = Document::find($data['related_document_id']);
            $allowedRelatedTypes = $this->allowedRelatedTypes($meta['type']);

            if (! $relatedDocument || $relatedDocument->direction !== $meta['direction']) {
                throw ValidationException::withMessages(['related_document_id' => 'Select a related document from the same workflow.']);
            }

            if ($allowedRelatedTypes !== [] && ! in_array($relatedDocument->type, $allowedRelatedTypes, true)) {
                throw ValidationException::withMessages(['related_document_id' => 'Select a valid source document for this workflow step.']);
            }

            if ($meta['party'] === 'customer' && (int) $relatedDocument->customer_id !== (int) ($data['customer_id'] ?? 0)) {
                throw ValidationException::withMessages(['related_document_id' => 'Select a related document for the selected customer.']);
            }

            if ($meta['party'] === 'supplier' && (int) $relatedDocument->supplier_id !== (int) ($data['supplier_id'] ?? 0)) {
                throw ValidationException::withMessages(['related_document_id' => 'Select a related document for the selected supplier.']);
            }
        }

        if ($meta['type'] === 'goods_receipt' && ($data['source_type'] ?? null) === 'supplier_po' && $relatedDocument?->status !== 'issued') {
            throw ValidationException::withMessages(['related_document_id' => 'Select an issued supplier purchase order before recording receiving.']);
        }

        $data['items'] = collect($data['items'])
            ->filter(fn ($item) => filled($item['description'] ?? null))
            ->values()
            ->all();

        if ($data['items'] === []) {
            throw ValidationException::withMessages(['items' => 'Add at least one line item.']);
        }

        $data['payment_terms_type'] = $data['payment_terms_type'] ?? 'standard';
        $data['payment_due_days'] = $data['payment_due_days'] ?? null;

        if (($data['payment_due_days'] ?? null) !== null && empty($data['payment_terms_label'])) {
            $days = (int) $data['payment_due_days'];
            $data['payment_terms_label'] = $days === 0 ? 'Due upon invoice' : $days.' days from invoice date';
        }

        if (empty($data['due_date']) && in_array($meta['type'], ['customer_invoice', 'supplier_invoice'], true) && ($data['payment_due_days'] ?? null) !== null) {
            $data['due_date'] = Carbon::parse($data['issue_date'])->addDays((int) $data['payment_due_days'])->toDateString();
        }

        if (! empty($data['progress_invoice_number']) && ! empty($data['progress_invoice_total']) && (int) $data['progress_invoice_number'] > (int) $data['progress_invoice_total']) {
            throw ValidationException::withMessages(['progress_invoice_number' => 'Progress invoice number cannot be greater than total progress invoices.']);
        }

        $data['billing_stages'] = collect($data['billing_stages'] ?? [])
            ->filter(fn ($stage) => filled($stage['stage_name'] ?? null))
            ->values()
            ->all();

        return $data;
    }

    private function directExceptionSourceNoteMessage(string $sourceType): string
    {
        return match ($sourceType) {
            'direct_customer_po' => 'Add a reason for this direct PO received.',
            'direct_invoice' => 'Add a reason for this direct customer invoice.',
            'direct_supplier_po' => 'Add a reason for this direct purchase order.',
            'direct_receipt' => 'Add a reason for this direct receipt.',
            'direct_supplier_invoice' => 'Add a reason for this direct supplier invoice.',
            default => 'Add a reason for this direct exception.',
        };
    }

    private function syncItems(Document $document, array $items, ?float $documentTaxRate = null): void
    {
        $document->items()->delete();
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($items as $item) {
            $quantity = (float) ($item['quantity'] ?? 1);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $taxRate = $documentTaxRate !== null ? (float) $documentTaxRate : (float) ($item['tax_rate'] ?? 0);
            $lineSubtotal = round($quantity * $unitPrice, 2);
            $taxAmount = round($lineSubtotal * ($taxRate / 100), 2);

            $document->items()->create([
                'product_id' => $item['product_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? 'unit',
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'line_total' => $lineSubtotal + $taxAmount,
            ]);

            $subtotal += $lineSubtotal;
            $taxTotal += $taxAmount;
        }

        $document->update([
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $subtotal + $taxTotal,
        ]);
    }

    private function syncBillingStages(Document $document, array $stages): void
    {
        $document->billingStages()->delete();

        foreach (array_values($stages) as $index => $stage) {
            $document->billingStages()->create([
                'sort_order' => $index + 1,
                'stage_name' => $stage['stage_name'],
                'condition_label' => $stage['condition_label'] ?? null,
                'percentage' => (float) ($stage['percentage'] ?? 0),
                'amount' => (float) ($stage['amount'] ?? 0),
                'payment_term' => $stage['payment_term'] ?? null,
                'previously_invoiced' => (float) ($stage['previously_invoiced'] ?? 0),
                'current_invoice' => (float) ($stage['current_invoice'] ?? 0),
                'remaining_amount' => (float) ($stage['remaining_amount'] ?? 0),
                'is_current' => (bool) ($stage['is_current'] ?? false),
            ]);
        }
    }

    private function ensureWriteAccess(array $meta): void
    {
        $role = request()->user()->role;

        if (in_array($role, ['admin', 'manager'], true)) {
            return;
        }

        if ($meta['direction'] === 'outgoing' && in_array($role, ['sales', 'accounts'], true)) {
            return;
        }

        if ($meta['direction'] === 'incoming' && in_array($role, ['procurement', 'accounts'], true)) {
            return;
        }

        abort(403);
    }

    private function ensureApprover(): void
    {
        abort_unless(request()->user()->canApprove(), 403);
    }
}
