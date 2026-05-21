<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LivePreviewFormattingBatchTest extends TestCase
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

        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'name' => 'Live Preview Company',
            'base_currency' => 'SGD',
            'date_format' => 'Y-m-d',
            'number_format' => 'fr-FR',
            'is_active' => true,
        ]));
    }

    public function test_document_live_preview_uses_company_date_and_currency_fallbacks(): void
    {
        $customer = Customer::create([
            'name' => 'Preview Customer Ltd',
            'code' => 'PREVIEW-CUST',
            'email' => 'preview-customer@example.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $document = Document::create([
            'type' => 'customer_quotation',
            'direction' => 'outgoing',
            'document_number' => 'QT-PREVIEW-001',
            'customer_id' => $customer->id,
            'status' => 'draft',
            'issue_date' => '2026-05-21',
            'due_date' => '2026-06-20',
            'currency' => '',
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('documents.edit', $document));

        $response->assertOk();
        $response->assertSee('2026-05-21');
        $response->assertSee('2026-06-20');
        $response->assertSee('SGD 0,00');
        $response->assertDontSee('21 May 2026');
        $response->assertDontSee('MYR 0.00');
    }

    public function test_receipt_live_preview_uses_company_date_format(): void
    {
        $supplier = Supplier::create([
            'name' => 'Preview Supplier Ltd',
            'code' => 'PREVIEW-SUP',
            'category' => 'Materials',
            'email' => 'preview-supplier@example.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $document = Document::create([
            'type' => 'goods_receipt',
            'direction' => 'incoming',
            'document_number' => 'GRN-PREVIEW-001',
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'issue_date' => '2026-05-22',
            'due_date' => null,
            'currency' => 'SGD',
            'subtotal' => 0,
            'tax_total' => 0,
            'total' => 0,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('documents.edit', $document));

        $response->assertOk();
        $response->assertSee('2026-05-22');
        $response->assertDontSee('22 May 2026');
    }
}
