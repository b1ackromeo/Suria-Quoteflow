<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportCurrencyColumnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_receivables_export_includes_currency_column(): void
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
            'email' => 'customer@example.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $invoice = Document::create([
            'type' => 'customer_invoice',
            'direction' => 'outgoing',
            'document_number' => 'INV-EXPORT-001',
            'customer_id' => $customer->id,
            'status' => 'issued',
            'issue_date' => '2026-05-20',
            'due_date' => '2026-06-19',
            'currency' => 'USD',
            'subtotal' => 1000,
            'tax_total' => 0,
            'total' => 1000,
            'created_by' => $admin->id,
        ]);

        Payment::create([
            'document_id' => $invoice->id,
            'payment_number' => 'PAY-EXPORT-001',
            'direction' => 'incoming',
            'amount' => 250,
            'payment_date' => '2026-05-21',
            'method' => 'transfer',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.export', ['report' => 'receivables']));

        $response->assertOk();
        $response->assertStreamedContentContains('Document,Party,"Issue Date","Due Date",Status,Currency,Total,Paid,Balance');
        $response->assertStreamedContentContains('INV-EXPORT-001,"Global Customer Pte Ltd",2026-05-20,2026-06-19,issued,USD,1000.00,250,750');
    }

    public function test_payments_export_includes_document_currency_column(): void
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
            'email' => 'customer@example.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $invoice = Document::create([
            'type' => 'customer_invoice',
            'direction' => 'outgoing',
            'document_number' => 'INV-PAY-EXPORT-001',
            'customer_id' => $customer->id,
            'status' => 'issued',
            'issue_date' => '2026-05-20',
            'currency' => 'EUR',
            'subtotal' => 500,
            'tax_total' => 0,
            'total' => 500,
            'created_by' => $admin->id,
        ]);

        Payment::create([
            'document_id' => $invoice->id,
            'payment_number' => 'PAY-EXPORT-002',
            'direction' => 'incoming',
            'amount' => 500,
            'payment_date' => '2026-05-22',
            'method' => 'transfer',
            'reference' => 'REF-500',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.export', ['report' => 'payments']));

        $response->assertOk();
        $response->assertStreamedContentContains('Date,"Payment Type",Document,Currency,Amount,Method,Reference');
        $response->assertStreamedContentContains('2026-05-22,Customer,INV-PAY-EXPORT-001,EUR,500,transfer,REF-500');
    }
}
