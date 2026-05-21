<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\CompanyProfile;
use App\Models\Document;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierInvoiceFilePreviewFormattingTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_invoice_file_preview_uses_company_number_format_for_file_size(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'name' => 'Supplier Invoice Preview Company',
            'base_currency' => 'SGD',
            'number_format' => 'fr-FR',
            'date_format' => 'Y-m-d',
            'is_active' => true,
        ]));

        $supplier = Supplier::create([
            'name' => 'Preview Supplier Ltd',
            'code' => 'PREVIEW-SUP',
            'category' => 'Materials',
            'email' => 'preview-supplier@example.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $document = Document::create([
            'type' => 'supplier_invoice',
            'direction' => 'incoming',
            'document_number' => 'SI-FILE-001',
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'issue_date' => '2026-05-21',
            'external_reference' => 'INV-FILE-001',
            'currency' => 'SGD',
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
            'created_by' => $admin->id,
        ]);

        Attachment::create([
            'document_id' => $document->id,
            'category' => 'invoice_copy',
            'original_name' => 'supplier-invoice.pdf',
            'path' => 'attachments/supplier-invoice.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1536,
            'uploaded_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('documents.show', $document));

        $response->assertOk();
        $response->assertSee('supplier-invoice.pdf');
        $response->assertSee('1,5 KB');
        $response->assertDontSee('1.5 KB');
    }
}
