<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentLocalizationDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_documents_use_active_company_base_currency_when_legacy_default_is_submitted(): void
    {
        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'base_currency' => 'SGD',
        ]));

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Global Customer Pte Ltd',
            'code' => 'GLOBAL',
            'email' => 'accounts@global-customer.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('documents.store', 'customer-quotations'), [
            'customer_id' => $customer->id,
            'external_reference' => 'RFQ-SG-001',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'currency' => 'MYR',
            'payment_terms_type' => 'standard',
            'payment_due_days' => 30,
            'document_tax_rate' => 0,
            'items' => [[
                'description' => 'Implementation planning',
                'quantity' => 1,
                'unit' => 'job',
                'unit_price' => 1200,
            ]],
        ]);

        $response->assertRedirect();

        $document = Document::firstOrFail();

        $this->assertSame('SGD', $document->currency);
    }

    public function test_new_documents_keep_explicit_non_default_currency(): void
    {
        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'base_currency' => 'SGD',
        ]));

        $document = Document::create([
            'type' => 'customer_quotation',
            'direction' => 'outgoing',
            'document_number' => 'CQ-2026-00001',
            'customer_id' => null,
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'currency' => 'USD',
        ]);

        $this->assertSame('USD', $document->currency);
    }

    public function test_dashboard_uses_company_money_formatting(): void
    {
        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'base_currency' => 'SGD',
            'number_format' => 'en-SG',
        ]));

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'Global Customer Pte Ltd',
            'code' => 'GLOBAL',
            'email' => 'accounts@global-customer.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $invoice = Document::create([
            'type' => 'customer_invoice',
            'direction' => 'outgoing',
            'document_number' => 'INV-2026-00001',
            'customer_id' => $customer->id,
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SGD',
            'subtotal' => 1200,
            'tax_total' => 0,
            'total' => 1200,
        ]);

        Payment::create([
            'document_id' => $invoice->id,
            'payment_number' => 'PAY-2026-00001',
            'direction' => 'incoming',
            'amount' => 200,
            'payment_date' => now()->toDateString(),
            'method' => 'bank_transfer',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('SGD 1,000.00');
        $response->assertDontSee('RM 1,000.00');
    }

    public function test_reports_use_company_money_formatting(): void
    {
        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'base_currency' => 'SGD',
            'number_format' => 'en-SG',
        ]));

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'name' => 'Global Supplier Pte Ltd',
            'code' => 'SUPSG',
            'email' => 'accounts@global-supplier.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        Document::create([
            'type' => 'supplier_invoice',
            'direction' => 'incoming',
            'document_number' => 'SIN-2026-00001',
            'supplier_id' => $supplier->id,
            'status' => 'matched',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency' => 'SGD',
            'subtotal' => 500,
            'tax_total' => 0,
            'total' => 500,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('SGD 500.00');
        $response->assertDontSee('RM 500.00');
    }
}
