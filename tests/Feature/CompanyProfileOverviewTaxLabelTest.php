<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyProfileOverviewTaxLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_profile_overview_shows_tax_registration_label_and_number(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'name' => 'Global QuoteFlow Pte Ltd',
            'country' => 'Singapore',
            'timezone' => 'Asia/Singapore',
            'base_currency' => 'SGD',
            'date_format' => 'Y-m-d',
            'number_format' => 'en-SG',
            'tax_label' => 'GST',
            'tax_registration_label' => 'GST Registration No.',
            'tax_registration_number' => 'M90000000X',
            'default_tax_rate' => 9,
        ]));

        $response = $this->actingAs($admin)->get(route('company-profiles.index'));

        $response->assertOk();
        $response->assertSee('Tax registration label');
        $response->assertSee('GST Registration No.');
        $response->assertSee('Tax registration number');
        $response->assertSee('M90000000X');
    }
}
