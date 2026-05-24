<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use Database\Seeders\DemoOperationsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DemoOperationsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_creates_complete_rc_technology_company_profile(): void
    {
        Artisan::call('db:seed', [
            '--class' => DemoOperationsSeeder::class,
        ]);

        $company = CompanyProfile::query()
            ->where('name', 'RC Technology Resources')
            ->firstOrFail();

        $this->assertTrue($company->is_active);
        $this->assertSame('202603107223 (003844744-P)', $company->registration_number);
        $this->assertSame('rctech@gmail.com', $company->email);
        $this->assertSame('+60 16-445 2786', $company->phone);
        $this->assertSame('1-3 Level 1, Jalan Perdana Blok 4801, CBD Perdana, Cyberjaya', $company->address);
        $this->assertSame('MYR', $company->baseCurrency());
        $this->assertSame('RM', $company->currencySymbol('MYR'));
        $this->assertNotEmpty($company->paymentInstructionLines());
        $this->assertStringContainsString('Reg. No. 202603107223 (003844744-P)', $company->displayPdfFooter());
    }
}
