<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserListingDateFormattingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_listing_uses_active_company_date_format(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role' => 'admin',
            'is_active' => true,
        ]);

        CompanyProfile::create(array_merge(CompanyProfile::defaults(), [
            'name' => 'Date Format Company',
            'date_format' => 'Y-m-d',
            'is_active' => true,
        ]));

        User::factory()->create([
            'name' => 'Operations Manager',
            'email' => 'ops-manager@example.test',
            'role' => 'manager',
            'is_active' => true,
            'updated_at' => '2026-05-21 09:15:00',
        ]);

        $response = $this->actingAs($admin)->get(route('users.index'));

        $response->assertOk();
        $response->assertSee('Operations Manager');
        $response->assertSee('Updated 2026-05-21');
        $response->assertDontSee('Updated 21 May 2026');
    }
}
