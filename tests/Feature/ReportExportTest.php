<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin);
    }

    public function test_receivables_export_appends_currency_column(): void
    {
        $customer = Customer::create([
            'name' => 'Global Customer Pte Ltd',
            'code' => 'GLOBAL-CUST',
            'email' => 'accounts@global-customer.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $document = Document::create([
            'type' => 'customer_invoice',
            'direction' => 'outgoing',
            'document_number' => 'INV-2026-00001',
            'customer_id' => $customer->id,
            'status' => 'issued',
            'issue_date' => '2026-05-01',
            'due_date' => '2026-05-31',
            'currency' => 'SGD',
            'subtotal' => 1000,
            'tax_total' => 80,
            'total' => 1080,
            'created_by' => $this->admin->id,
        ]);

        Payment::create([
            'document_id' => $document->id,
            'direction' => 'incoming',
            'payment_date' => '2026-05-10',
            'amount' => 300,
            'method' => 'Bank transfer',
            'reference' => 'PAY-CUST-001',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->get(route('reports.export', ['report' => 'receivables']));

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Document,Party,"Issue Date","Due Date",Status,Total,Paid,Balance,Currency', $csv);
        $this->assertStringContainsString('INV-2026-00001,"Global Customer Pte Ltd",2026-05-01,2026-05-31,issued,1080.00,300,780,SGD', $csv);
    }

    public function test_payments_export_appends_document_currency_column(): void
    {
        $supplier = Supplier::create([
            'name' => 'International Supplier Ltd',
            'code' => 'GLOBAL-SUP',
            'category' => 'Services',
            'email' => 'accounts@international-supplier.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $document = Document::create([
            'type' => 'supplier_invoice',
            'direction' => 'incoming',
            'document_number' => 'SIN-2026-00001',
            'supplier_id' => $supplier->id,
            'status' => 'matched',
            'issue_date' => '2026-05-02',
            'due_date' => '2026-06-01',
            'currency' => 'USD',
            'subtotal' => 500,
            'tax_total' => 0,
            'total' => 500,
            'created_by' => $this->admin->id,
        ]);

        Payment::create([
            'document_id' => $document->id,
            'direction' => 'outgoing',
            'payment_date' => '2026-05-11',
            'amount' => 125,
            'method' => 'Wire transfer',
            'reference' => 'PAY-SUP-001',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->get(route('reports.export', ['report' => 'payments']));

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('Date,"Payment Type",Document,Amount,Method,Reference,Currency', $csv);
        $this->assertStringContainsString('2026-05-11,"Supplier payment made",SIN-2026-00001,125.00,"Wire transfer",PAY-SUP-001,USD', $csv);
    }
}
