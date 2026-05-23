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

    public function test_new_documents_use_active_company_base_currency_when_currency_is_missing(): void
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
        ]);

        $this->assertSame('SGD', $document->currency);
    }

    public function test_new_documents_keep_explicit_myr_currency_when_company_uses_another_currency(): void
    {
        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'base_currency' => 'SGD',
        ]));

        $document = Document::create([
            'type' => 'customer_quotation',
            'direction' => 'outgoing',
            'document_number' => 'CQ-2026-00002',
            'customer_id' => null,
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'currency' => 'MYR',
        ]);

        $this->assertSame('MYR', $document->currency);
    }

    public function test_new_documents_keep_explicit_non_default_currency(): void
    {
        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'base_currency' => 'SGD',
        ]));

        $document = Document::create([
            'type' => 'customer_quotation',
            'direction' => 'outgoing',
            'document_number' => 'CQ-2026-00003',
            'customer_id' => null,
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'currency' => 'USD',
        ]);

        $this->assertSame('USD', $document->currency);
    }

    public function test_document_create_form_displays_company_base_currency(): void
    {
        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'base_currency' => 'SGD',
        ]));

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('documents.create', 'customer-quotations'));

        $response->assertOk();
        $response->assertSee('name="currency"', false);
        $response->assertSee('value="SGD"', false);
        $response->assertDontSee('value="MYR"', false);
    }

    public function test_document_create_form_preserves_source_document_currency(): void
    {
        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'base_currency' => 'SGD',
        ]));

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'United States Customer Inc',
            'code' => 'USCUST',
            'email' => 'accounts@us-customer.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $quotation = Document::create([
            'type' => 'customer_quotation',
            'direction' => 'outgoing',
            'document_number' => 'CQ-2026-00001',
            'customer_id' => $customer->id,
            'status' => 'issued',
            'issue_date' => now()->toDateString(),
            'currency' => 'USD',
            'subtotal' => 1000,
            'tax_total' => 0,
            'total' => 1000,
        ]);

        $response = $this->actingAs($admin)->get(route('documents.create', [
            'module' => 'customer-pos',
            'source_document_id' => $quotation->id,
        ]));

        $response->assertOk();
        $response->assertSee('value="USD"', false);
        $response->assertDontSee('value="MYR"', false);
    }

    public function test_document_preview_uses_company_base_currency_when_document_currency_is_missing(): void
    {
        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'base_currency' => 'SGD',
            'number_format' => 'en-SG',
        ]));

        $document = new Document([
            'type' => 'customer_quotation',
            'direction' => 'outgoing',
            'document_number' => 'CQ-DRAFT',
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'subtotal' => 100,
            'tax_total' => 0,
            'total' => 100,
        ]);

        $view = $this->view('documents.partials.quotation-preview', [
            'document' => $document,
            'meta' => ['singular' => 'Customer quotation'],
        ]);

        $view->assertSee('SGD');
        $view->assertSee('SGD 100.00');
        $view->assertDontSee('MYR 100.00');
    }

    public function test_document_form_loads_company_live_preview_formatting_config(): void
    {
        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'base_currency' => 'MYR',
            'currency_display' => 'symbol_with_code',
            'currency_symbol_override' => 'RM',
            'number_format' => 'fr-FR',
            'date_format' => 'Y-m-d',
            'tax_label' => 'GST',
            'default_tax_rate' => 9,
        ]));

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('documents.create', 'customer-quotations'));

        $response->assertOk();
        $response->assertSee('window.QuoteFlowCompanyFormat', false);
        $response->assertSee('"baseCurrency":"MYR"', false);
        $response->assertSee('"currencyDisplay":"symbol_with_code"', false);
        $response->assertSee('"currencySymbolOverride":"RM"', false);
        $response->assertSee('"MYR":"RM"', false);
        $response->assertSee('"numberFormat":"fr-FR"', false);
        $response->assertSee('"dateFormat":"Y-m-d"', false);
        $response->assertSee('"taxLabel":"GST"', false);
        $response->assertSee('"defaultTaxRate":9', false);
        $response->assertSee('js/document-form-company-formatting.js', false);
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
        $response->assertSee('SGD 0.00 overdue');
        $response->assertSee('SGD 0.00 due within 7 days');
        $response->assertDontSee('RM 1,000.00');
        $response->assertDontSee('RM 0.00 overdue');
        $response->assertDontSee('RM 0.00 due within 7 days');
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
