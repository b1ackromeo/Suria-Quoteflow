<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentPdfCompanyTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_view_uses_company_profile_document_text(): void
    {
        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'name' => 'Global QuoteFlow Pte Ltd',
            'country' => 'Singapore',
            'timezone' => 'Asia/Singapore',
            'base_currency' => 'SGD',
            'date_format' => 'Y-m-d',
            'number_format' => 'en-SG',
            'tax_label' => 'GST',
            'tax_registration_number' => 'REG-9000',
            'default_tax_rate' => 9,
            'payment_instructions' => "Custom instruction line one\nCustom instruction line two",
            'pdf_footer' => 'Custom PDF footer text.',
        ]));

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $customer = Customer::create([
            'name' => 'Global Customer Pte Ltd',
            'code' => 'GLOBAL',
            'email' => 'customer@example.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $product = Product::create([
            'type' => 'service',
            'sku' => 'IMPLEMENT',
            'name' => 'Implementation service',
            'description' => 'Implementation service package',
            'unit' => 'job',
            'selling_price' => 1000,
            'cost_price' => 500,
            'tax_rate' => 9,
            'is_active' => true,
        ]);

        $invoice = Document::create([
            'type' => 'customer_invoice',
            'direction' => 'outgoing',
            'document_number' => 'INV-PDF-001',
            'customer_id' => $customer->id,
            'status' => 'issued',
            'issue_date' => '2026-05-20',
            'due_date' => '2026-06-19',
            'currency' => 'SGD',
            'payment_terms_type' => 'standard',
            'payment_due_days' => 30,
            'payment_terms_label' => '30 days from invoice date',
            'subtotal' => 1000,
            'tax_total' => 90,
            'total' => 1090,
            'created_by' => $admin->id,
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'description' => 'Implementation service package',
            'quantity' => 1,
            'unit' => 'job',
            'unit_price' => 1000,
            'tax_rate' => 9,
            'tax_amount' => 90,
            'line_total' => 1000,
        ]);

        $invoice->load(['customer', 'supplier', 'relatedDocument.items', 'items.product', 'billingStages', 'payments', 'attachments', 'creator', 'approver']);

        $html = view('documents.pdf', [
            'document' => $invoice,
            'meta' => Document::metaForSlug(Document::slugForType($invoice->type)),
        ])->render();

        $this->assertStringContainsString('GST Reg. No. REG-9000', $html);
        $this->assertStringContainsString('Custom instruction line one', $html);
        $this->assertStringContainsString('Custom instruction line two', $html);
        $this->assertStringContainsString('Payment Reference:</strong> INV-PDF-001', $html);
        $this->assertStringContainsString('Custom PDF footer text.', $html);
    }
}
