<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchCompanyFormattingTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_results_use_company_date_and_money_formatting(): void
    {
        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'base_currency' => 'SGD',
            'date_format' => 'Y-m-d',
            'number_format' => 'en-SG',
        ]));

        $user = User::factory()->create([
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

        Document::create([
            'type' => 'customer_invoice',
            'direction' => 'outgoing',
            'document_number' => 'INV-SEARCH-001',
            'customer_id' => $customer->id,
            'status' => 'issued',
            'issue_date' => '2026-05-20',
            'currency' => 'SGD',
            'payment_terms_type' => 'standard',
            'payment_due_days' => 30,
            'payment_terms_label' => '30 days from invoice date',
            'subtotal' => 1234.56,
            'tax_total' => 0,
            'total' => 1234.56,
            'created_by' => $user->id,
        ]);

        Product::create([
            'type' => 'service',
            'sku' => 'GLOBAL-SETUP',
            'name' => 'Global setup service',
            'description' => 'Global setup service package',
            'unit' => 'job',
            'selling_price' => 987.65,
            'cost_price' => 432.10,
            'tax_rate' => 0,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('search.index', ['q' => 'Global']));

        $response->assertOk();
        $response->assertSee('2026-05-20');
        $response->assertSee('SGD 1,234.56');
        $response->assertSee('Sell: SGD 987.65', false);
        $response->assertSee('Cost: SGD 432.10', false);
        $response->assertDontSee('20 May 2026');
        $response->assertDontSee('Sell: 987.65', false);
    }
}
