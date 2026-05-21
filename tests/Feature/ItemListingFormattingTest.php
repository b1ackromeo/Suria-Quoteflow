<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemListingFormattingTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_listing_uses_company_currency(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        CompanyProfile::create([
            'name' => 'QuoteFlow Demo Company',
            'registration_number' => 'TEST-001',
            'email' => 'demo@example.test',
            'phone' => '+1 555 0100',
            'address' => 'Demo address',
            'tagline' => 'Demo',
            'primary_color' => '#0a345f',
            'accent_color' => '#0a4f93',
            'country' => 'Singapore',
            'timezone' => 'Asia/Singapore',
            'base_currency' => 'SGD',
            'date_format' => 'Y-m-d',
            'number_format' => 'en-MY',
            'tax_label' => 'GST',
            'tax_registration_label' => 'GST Registration No.',
            'default_tax_rate' => 8,
            'is_active' => true,
        ]);

        Product::create([
            'type' => 'service',
            'sku' => 'SVC-001',
            'name' => 'Implementation service',
            'description' => 'Implementation service shown on documents.',
            'unit' => 'job',
            'selling_price' => 1200,
            'cost_price' => 450,
            'tax_rate' => 8,
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('products.index'));

        $response->assertOk();
        $response->assertSee('SGD 1,200.00');
        $response->assertDontSee('MYR 1,200.00');
    }
}
