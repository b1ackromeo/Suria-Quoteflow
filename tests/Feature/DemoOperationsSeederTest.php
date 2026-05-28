<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Project;
use App\Services\Projects\ProjectCommercialReportService;
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

    public function test_demo_seed_includes_phase_five_project_billing_sample(): void
    {
        Artisan::call('db:seed', [
            '--class' => DemoOperationsSeeder::class,
        ]);

        $project = Project::query()
            ->where('project_code', 'PRJ-2026-P5-DEMO')
            ->firstOrFail();
        $workItems = $project->wbsItems()->with('parent')->get();
        $report = app(ProjectCommercialReportService::class)->report($project, $workItems);
        $delivery = $report['billing']['delivery'];

        $this->assertCount(2, $workItems);
        $this->assertSame(5, $project->documents()->count());
        $this->assertSame(2, $project->variations()->count());
        $this->assertSame(18000.0, $delivery['customer']['unbilled_value']);
        $this->assertSame(11000.0, $delivery['supplier']['not_yet_received_value']);
        $this->assertSame(2500.0, $delivery['supplier']['received_not_invoiced_value']);
        $this->assertGreaterThan(0, $delivery['evidence']['customer_unbilled']['line_count']);
        $this->assertGreaterThan(0, $delivery['evidence']['supplier_not_yet_received']['line_count']);
        $this->assertGreaterThan(0, $delivery['evidence']['received_not_invoiced']['line_count']);
    }
}
