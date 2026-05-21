<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalDisplayFormattingBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'name' => 'Global Formatting Company',
            'base_currency' => 'SGD',
            'date_format' => 'Y-m-d',
            'number_format' => 'fr-FR',
            'is_active' => true,
        ]));

        $this->customer = Customer::create([
            'name' => 'Global Customer Ltd',
            'code' => 'GLOBAL-CUST',
            'email' => 'accounts@global-customer.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);
    }

    public function test_pending_approvals_use_company_money_and_date_formatting(): void
    {
        $document = $this->createCustomerInvoice('INV-FORMAT-001', '2026-05-21', 'USD', 1234.56, 'pending_approval');

        $approval = Approval::create([
            'document_id' => $document->id,
            'requested_by' => $this->admin->id,
            'status' => 'pending',
        ]);

        $approval->timestamps = false;
        $approval->forceFill([
            'created_at' => '2026-05-21 14:30:00',
            'updated_at' => '2026-05-21 14:30:00',
        ])->save();

        $response = $this->actingAs($this->admin)->get(route('approvals.pending'));

        $response->assertOk();
        $response->assertSee('USD 1 234,56');
        $response->assertSee('2026-05-21');
        $response->assertDontSee('USD 1,234.56');
        $response->assertDontSee('21 May 2026');
    }

    public function test_document_list_uses_company_money_and_date_formatting(): void
    {
        $this->createCustomerInvoice('INV-FORMAT-002', '2026-05-22', 'EUR', 9876.54, 'issued');

        $response = $this->actingAs($this->admin)->get(route('documents.index', 'customer-invoices'));

        $response->assertOk();
        $response->assertSee('EUR 9 876,54');
        $response->assertSee('2026-05-22');
        $response->assertDontSee('EUR 9,876.54');
        $response->assertDontSee('22 May 2026');
    }

    public function test_document_show_uses_company_money_and_date_formatting(): void
    {
        $document = $this->createCustomerInvoice('INV-FORMAT-003', '2026-05-21', 'EUR', 9876.54, 'issued');

        $response = $this->actingAs($this->admin)->get(route('documents.show', $document));

        $response->assertOk();
        $response->assertSee('EUR 9 876,54');
        $response->assertSee('2026-05-21');
        $response->assertDontSee('EUR 9,876.54');
        $response->assertDontSee('21 May 2026');
    }

    private function createCustomerInvoice(string $documentNumber, string $issueDate, string $currency, float $total, string $status): Document
    {
        return Document::create([
            'type' => 'customer_invoice',
            'direction' => 'outgoing',
            'document_number' => $documentNumber,
            'customer_id' => $this->customer->id,
            'status' => $status,
            'issue_date' => $issueDate,
            'due_date' => '2026-06-21',
            'currency' => $currency,
            'subtotal' => $total,
            'tax_total' => 0,
            'total' => $total,
            'created_by' => $this->admin->id,
        ]);
    }
}
