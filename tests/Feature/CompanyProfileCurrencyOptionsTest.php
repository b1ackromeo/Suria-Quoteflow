<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyProfileCurrencyOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_profile_form_includes_common_global_currency_options(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $company = CompanyProfile::create(CompanyProfile::defaults());

        $response = $this->actingAs($admin)->get(route('company-profiles.edit', $company));

        $response->assertOk();
        $response->assertSee('CAD - Canadian Dollar');
        $response->assertSee('JPY - Japanese Yen');
        $response->assertSee('CNY - Chinese Yuan');
        $response->assertSee('HKD - Hong Kong Dollar');
        $response->assertSee('INR - Indian Rupee');
        $response->assertSee('AED - UAE Dirham');
    }
}
