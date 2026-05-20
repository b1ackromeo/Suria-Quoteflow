<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Document;
use App\Support\CompanyPdfText;
use Barryvdh\DomPDF\Facade\Pdf;

class DocumentPdfController extends Controller
{
    public function __construct(private CompanyPdfText $companyPdfText)
    {
    }

    public function stream(Document $document)
    {
        return $this->documentPdf($document)->stream($document->document_number.'.pdf');
    }

    public function download(Document $document)
    {
        return $this->documentPdf($document)->download($document->document_number.'.pdf');
    }

    private function documentPdf(Document $document)
    {
        return Pdf::loadHTML($this->renderHtml($document))->setPaper('a4');
    }

    private function renderHtml(Document $document): string
    {
        $document->load(['customer', 'supplier', 'relatedDocument.items', 'items.product', 'billingStages', 'payments', 'attachments', 'creator', 'approver']);
        $companyProfile = CompanyProfile::active();

        $html = view('documents.pdf', [
            'document' => $document,
            'meta' => Document::metaForSlug(Document::slugForType($document->type)),
        ])->render();

        return $this->companyPdfText->apply($html, $companyProfile, $document);
    }
}
