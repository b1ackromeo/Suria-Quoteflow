<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\AttachmentExtraction;
use App\Models\Document;
use App\Models\Supplier;
use App\Services\Documents\BusinessDocumentCaptureService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PurchaseRequestQuoteController extends Controller
{
    public function __construct(private BusinessDocumentCaptureService $extractor)
    {
    }

    public function create(): View
    {
        $this->ensureWriteAccess();

        return view('documents.purchase-request-quote-first', [
            'suppliers' => Supplier::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureWriteAccess();

        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'delivery_to' => ['nullable', 'string'],
            'source_note' => ['nullable', 'string'],
            'issue_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3'],
            'source_attachment' => ['required', 'file', 'max:8192', 'mimes:pdf,jpg,jpeg,png,webp,bmp,tif,tiff'],
        ]);

        $file = $request->file('source_attachment');
        abort_unless($file instanceof UploadedFile, 422);

        $document = DB::transaction(function () use ($data, $file) {
            $document = Document::create([
                'type' => 'purchase_request',
                'direction' => 'incoming',
                'document_number' => Document::nextNumber('purchase_request'),
                'supplier_id' => $data['supplier_id'] ?? null,
                'source_type' => 'supplier_quote',
                'source_note' => $data['source_note'] ?? null,
                'project_name' => $data['project_name'] ?? null,
                'delivery_to' => $data['delivery_to'] ?? null,
                'status' => 'draft',
                'issue_date' => $data['issue_date'],
                'currency' => strtoupper($data['currency'] ?? 'MYR'),
                'payment_terms_type' => 'standard',
                'created_by' => auth()->id(),
            ]);

            $document->items()->create([
                'description' => 'Supplier quote OCR pending verification',
                'quantity' => 1,
                'unit' => 'lot',
                'unit_price' => 0,
                'tax_rate' => 0,
                'tax_amount' => 0,
                'line_total' => 0,
            ]);

            $attachment = $this->storeSupplierQuoteAttachment($document, $file);
            $extraction = $this->storeExtractionDraft($attachment);

            Audit::record('purchase_request_quote_first_created', $document, null, [
                'document_id' => $document->id,
                'attachment_id' => $attachment->id,
                'extraction_status' => $extraction?->status,
            ]);

            return $document;
        });

        return redirect()
            ->route('documents.show', $document)
            ->with('status', 'Purchase request draft created from supplier quote. Verify the OCR quote lines before submitting for approval.');
    }

    private function ensureWriteAccess(): void
    {
        abort_unless(auth()->user()->hasRole('admin', 'manager', 'procurement', 'accounts'), 403);
    }

    private function storeSupplierQuoteAttachment(Document $document, UploadedFile $file): Attachment
    {
        $name = Str::uuid().'-'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $extension = $file->getClientOriginalExtension();
        $path = $file->storeAs('attachments/'.$document->id, $name.($extension ? '.'.$extension : ''));

        $attachment = Attachment::create([
            'document_id' => $document->id,
            'category' => 'supplier_quote',
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        Audit::record('attachment_uploaded', $attachment, null, $attachment->toArray());

        return $attachment;
    }

    private function storeExtractionDraft(Attachment $attachment): ?AttachmentExtraction
    {
        if (! config('ocr.enabled', true)) {
            return AttachmentExtraction::create([
                'attachment_id' => $attachment->id,
                'document_id' => $attachment->document_id,
                'status' => 'failed',
                'engine' => 'manual',
                'language' => (string) config('ocr.language', 'eng'),
                'extracted_fields' => [],
                'error_message' => 'OCR is disabled. Verify the supplier quote manually from the uploaded file.',
            ]);
        }

        if (! $this->extractor->canExtract($attachment)) {
            throw ValidationException::withMessages([
                'source_attachment' => 'Upload a PDF or image file that can be previewed and extracted.',
            ]);
        }

        try {
            $result = $this->extractor->extract($attachment);
            $payload = [
                'document_id' => $attachment->document_id,
                'status' => 'processed',
                'engine' => $result['engine'] ?? 'quoteflow_document_capture',
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
                'engine' => $this->extractor->engineName(),
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
}
