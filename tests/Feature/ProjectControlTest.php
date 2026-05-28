<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectVariation;
use App\Models\Supplier;
use App\Models\User;
use App\Models\WbsItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectControlTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    private Customer $customer;

    private Supplier $supplier;

    private Product $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'QuoteFlow Admin',
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'name' => 'Project Manager',
            'role' => 'manager',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'Acme Trading Sdn Bhd',
            'code' => 'ACME',
            'email' => 'accounts@acme.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Best Supplies Sdn Bhd',
            'code' => 'BEST',
            'category' => 'Materials / Hardware',
            'email' => 'accounts@best.test',
            'payment_terms_days' => 30,
            'is_active' => true,
        ]);

        $this->service = Product::create([
            'type' => 'service',
            'sku' => 'ROLL',
            'name' => 'Rollout service',
            'description' => 'Network rollout planning, installation, testing, and handover.',
            'unit' => 'job',
            'selling_price' => 1200,
            'cost_price' => 450,
            'tax_rate' => 8,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin);
    }

    public function test_project_pages_show_project_control_summary_and_documents(): void
    {
        $project = $this->createProject();
        $this->createLinkedDocument($project, 'customer_quotation', 'CQ-2026-90001', 12000);
        $this->createLinkedDocument($project, 'customer_po', 'CPO-2026-90001', 11000);
        $this->createLinkedDocument($project, 'supplier_po', 'SPO-2026-90001', 6500);

        $index = $this->get(route('projects.index'));

        $index->assertOk();
        $index->assertSee('Project control');
        $index->assertSee('Projects');
        $index->assertSee('New project');
        $index->assertSee('Cyberjaya network rollout');
        $index->assertSee('3 linked documents');

        $show = $this->get(route('projects.show', $project));

        $show->assertOk();
        $show->assertSee('Commercial summary');
        $show->assertSee('Export report CSV');
        $show->assertSee('Project documents');
        $show->assertSee('Quoted revenue');
        $show->assertSee('Customer confirmed');
        $show->assertSee('Supplier committed');
        $show->assertSee('Expected margin');
        $show->assertSee('CQ-2026-90001');
        $show->assertSee('SPO-2026-90001');
    }

    public function test_project_page_shows_budget_margin_and_exception_reports(): void
    {
        $project = $this->createProject([
            'budget_amount' => 1000,
            'margin_target_percent' => 25,
        ]);
        $workItem = $this->createWorkItem($project, [
            'revenue_budget' => 1500,
            'cost_budget' => 1000,
        ]);
        $customerPo = $this->createLinkedDocument($project, 'customer_po', 'CPO-2026-92001', 1000);
        $customerInvoice = $this->createLinkedDocument($project, 'customer_invoice', 'INV-2026-92001', 600);
        $supplierPo = $this->createLinkedDocument($project, 'supplier_po', 'SPO-2026-92001', 1200);
        $goodsReceipt = $this->createLinkedDocument($project, 'goods_receipt', 'GR-2026-92001', 900);
        $supplierInvoice = $this->createLinkedDocument($project, 'supplier_invoice', 'SIN-2026-92001', 1300);
        $quotation = $this->createLinkedDocument($project, 'customer_quotation', 'CQ-2026-92001', 1200);

        $this->addProjectLine($customerPo, $workItem, 1000, 'Customer confirmation for rollout scope');
        $this->addProjectLine($customerInvoice, $workItem, 600, 'First billing stage for rollout scope');
        $this->addProjectLine($supplierPo, $workItem, 1200, 'Committed cable supply');
        $this->addProjectLine($goodsReceipt, $workItem, 900, 'Delivered cable supply');
        $this->addProjectLine($supplierInvoice, $workItem, 1300, 'Supplier overtime claim');
        $this->addProjectLine($quotation, null, 1200, 'Quotation line waiting for cost code');

        $show = $this->get(route('projects.show', $project));

        $show->assertOk();
        $show->assertSee('Budget and margin');
        $show->assertSee('Expected margin');
        $show->assertSee('Actual margin');
        $show->assertSee('Unbilled revenue');
        $show->assertSee('Unpaid supplier cost');
        $show->assertSee('Project exceptions');
        $show->assertSee('Review evidence');
        $show->assertSee('Margin below target');
        $show->assertSee('Work item missing');
        $show->assertSee('Budget overrun');
        $show->assertSee('Actual cost over budget');
        $show->assertSee('Supplier actual above committed');
        $show->assertSee('Quotation line waiting for cost code');
        $show->assertSee('Committed cable supply');
        $show->assertSee('Supplier overtime claim');
        $show->assertSee(route('documents.show', $supplierInvoice), false);
        $show->assertSee('Received / accepted');
        $show->assertSee('Actual variance');
    }

    public function test_project_page_shows_billing_progress_rollup(): void
    {
        $project = $this->createProject([
            'project_code' => 'PRJ-2026-026',
            'name' => 'Milestone billing project',
        ]);
        $customerPo = $this->createLinkedDocument($project, 'customer_po', 'CPO-2026-91501', 1000);
        $customerInvoice = $this->createLinkedDocument($project, 'customer_invoice', 'INV-2026-91501', 600);
        $supplierPo = $this->createLinkedDocument($project, 'supplier_po', 'SPO-2026-91501', 900);
        $supplierInvoice = $this->createLinkedDocument($project, 'supplier_invoice', 'SIN-2026-91501', 300);

        $this->attachMilestoneSchedule($customerPo, [
            ['stage_name' => 'Deposit', 'amount' => 600, 'percentage' => 60, 'payment_term' => 'Due upon invoice'],
            ['stage_name' => 'Final Claim', 'amount' => 400, 'percentage' => 40, 'payment_term' => '14 days from invoice date'],
        ]);
        $this->attachMilestoneSchedule($customerInvoice, [
            ['stage_name' => 'Deposit', 'amount' => 600, 'percentage' => 60, 'current_invoice' => 600, 'is_current' => true],
            ['stage_name' => 'Final Claim', 'amount' => 400, 'percentage' => 40, 'remaining_amount' => 400],
        ], [
            'progress_invoice_number' => 1,
            'progress_invoice_total' => 2,
            'billing_stage_name' => 'Deposit',
        ]);
        $this->attachMilestoneSchedule($supplierPo, [
            ['stage_name' => 'Deposit', 'amount' => 300, 'percentage' => 33.33, 'payment_term' => 'Due upon valid supplier invoice'],
            ['stage_name' => 'Final Claim', 'amount' => 600, 'percentage' => 66.67, 'payment_term' => '14 days from supplier invoice'],
        ]);
        $this->attachMilestoneSchedule($supplierInvoice, [
            ['stage_name' => 'Deposit', 'amount' => 300, 'percentage' => 33.33, 'current_invoice' => 300, 'is_current' => true],
            ['stage_name' => 'Final Claim', 'amount' => 600, 'percentage' => 66.67, 'remaining_amount' => 600],
        ], [
            'progress_invoice_number' => 1,
            'progress_invoice_total' => 2,
            'billing_stage_name' => 'Deposit',
        ]);

        $show = $this->get(route('projects.show', $project));

        $show->assertOk();
        $show->assertSee('Billing progress');
        $show->assertSee('Customer billing');
        $show->assertSee('Supplier billing');
        $show->assertSee('Recent staged invoices');
        $show->assertSee('No. 1 of 2');
        $show->assertSee('Deposit');
        $show->assertSee('INV-2026-91501');
        $show->assertSee('SIN-2026-91501');
        $show->assertSee(route('documents.show', $customerInvoice), false);
    }

    public function test_project_page_shows_retention_summary(): void
    {
        $project = $this->createProject([
            'project_code' => 'PRJ-2026-028',
            'name' => 'Retention project',
        ]);
        $customerInvoice = $this->createLinkedDocument($project, 'customer_invoice', 'INV-2026-92801', 1000);
        $supplierInvoice = $this->createLinkedDocument($project, 'supplier_invoice', 'SIN-2026-92801', 800);

        $this->attachRetention($customerInvoice, 5, 50, '2026-08-01');
        $this->attachRetention($supplierInvoice, 2.5, 20, '2026-08-15');

        $show = $this->get(route('projects.show', $project));

        $show->assertOk();
        $show->assertSee('Retention');
        $show->assertSee('Customer retention held');
        $show->assertSee('Supplier retention held');
        $show->assertSee('Net retention exposure');
        $show->assertSee('2026-08-01');
        $show->assertSee('2026-08-15');
    }

    public function test_project_page_shows_variation_order_summary_and_register(): void
    {
        $project = $this->createProject([
            'project_code' => 'PRJ-2026-029',
            'name' => 'Variation order project',
            'contract_value' => 25000,
            'budget_amount' => 15000,
        ]);
        $sourceDocument = $this->createLinkedDocument($project, 'customer_quotation', 'CQ-2026-92901', 2000);

        $this->createVariation($project, [
            'variation_number' => 'VO-2026-001',
            'title' => 'Additional switch cabinet scope',
            'status' => 'approved',
            'effective_date' => '2026-05-20',
            'customer_value' => 2000,
            'supplier_cost' => 1200,
            'source_document_id' => $sourceDocument->id,
            'notes' => 'Approved by customer signed email.',
        ]);
        $this->createVariation($project, [
            'variation_number' => 'VO-2026-002',
            'title' => 'Containment rerouting',
            'status' => 'pending_review',
            'customer_value' => 500,
            'supplier_cost' => 250,
            'notes' => 'Waiting for customer confirmation.',
        ]);

        $show = $this->get(route('projects.show', $project));

        $show->assertOk();
        $show->assertSee('Variation orders');
        $show->assertSee('Approved customer change');
        $show->assertSee('Pending customer change');
        $show->assertSee('Revised contract value');
        $show->assertSee('VO-2026-001');
        $show->assertSee('VO-2026-002');
        $show->assertSee('Approved by customer signed email.');
        $show->assertSee('Pending review');
        $show->assertSee(route('documents.show', $sourceDocument), false);
    }

    public function test_admin_can_create_update_and_delete_project_variations(): void
    {
        $project = $this->createProject([
            'project_code' => 'PRJ-2026-030',
        ]);
        $sourceDocument = $this->createLinkedDocument($project, 'customer_po', 'CPO-2026-93001', 1500);

        $this->post(route('projects.variations.store', $project), [
            'variation_number' => 'VO-2026-010',
            'title' => 'Structured cabling increase',
            'status' => 'approved',
            'effective_date' => '2026-05-25',
            'customer_value' => 1500,
            'supplier_cost' => 700,
            'source_document_id' => $sourceDocument->id,
            'notes' => 'Approved scope change.',
        ])->assertRedirect(route('projects.show', $project));

        $variation = ProjectVariation::query()->firstOrFail();

        $this->assertDatabaseHas('project_variations', [
            'id' => $variation->id,
            'project_id' => $project->id,
            'variation_number' => 'VO-2026-010',
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'project_variation_created',
            'auditable_type' => ProjectVariation::class,
            'auditable_id' => $variation->id,
        ]);

        $this->put(route('projects.variations.update', [$project, $variation]), [
            'variation_number' => 'VO-2026-010',
            'title' => 'Structured cabling increase revised',
            'status' => 'pending_review',
            'effective_date' => '2026-05-28',
            'customer_value' => 1800,
            'supplier_cost' => 900,
            'source_document_id' => $sourceDocument->id,
            'notes' => 'Returned for customer re-approval.',
        ])->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_variations', [
            'id' => $variation->id,
            'title' => 'Structured cabling increase revised',
            'status' => 'pending_review',
        ]);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'project_variation_updated',
            'auditable_type' => ProjectVariation::class,
            'auditable_id' => $variation->id,
        ]);

        $this->delete(route('projects.variations.destroy', [$project, $variation]))
            ->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseMissing('project_variations', [
            'id' => $variation->id,
        ]);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'project_variation_deleted',
            'auditable_type' => ProjectVariation::class,
            'auditable_id' => $variation->id,
        ]);
    }

    public function test_project_variation_requires_non_zero_change(): void
    {
        $project = $this->createProject([
            'project_code' => 'PRJ-2026-031',
        ]);
        $this->post(route('projects.variations.store', $project), [
            'variation_number' => 'VO-2026-011',
            'title' => 'Empty approved change',
            'status' => 'approved',
            'effective_date' => '',
            'customer_value' => 0,
            'supplier_cost' => 0,
        ])->assertSessionHasErrors([
            'customer_value',
        ]);
    }

    public function test_project_variation_requires_effective_date_when_approved(): void
    {
        $project = $this->createProject([
            'project_code' => 'PRJ-2026-032',
        ]);

        $this->post(route('projects.variations.store', $project), [
            'variation_number' => 'VO-2026-012',
            'title' => 'Approved change without date',
            'status' => 'approved',
            'effective_date' => '',
            'customer_value' => 1200,
            'supplier_cost' => 500,
        ])->assertSessionHasErrors([
            'effective_date',
        ]);
    }

    public function test_project_commercial_report_can_be_exported_from_detail_page(): void
    {
        $project = $this->createProject([
            'project_code' => 'PRJ-2026-025',
            'name' => 'Detail export project',
            'budget_amount' => 1000,
            'margin_target_percent' => 25,
        ]);
        $workItem = $this->createWorkItem($project, [
            'code' => '2.01',
            'name' => 'Exported work item',
            'revenue_budget' => 1500,
            'cost_budget' => 1000,
        ]);
        $customerPo = $this->createLinkedDocument($project, 'customer_po', 'CPO-2026-92501', 1000);
        $supplierPo = $this->createLinkedDocument($project, 'supplier_po', 'SPO-2026-92501', 1200);
        $supplierInvoice = $this->createLinkedDocument($project, 'supplier_invoice', 'SIN-2026-92501', 1300);
        $quotation = $this->createLinkedDocument($project, 'customer_quotation', 'CQ-2026-92501', 1200);

        $this->addProjectLine($customerPo, $workItem, 1000, 'Confirmed export scope');
        $this->addProjectLine($supplierPo, $workItem, 1200, 'Committed export materials');
        $this->addProjectLine($supplierInvoice, $workItem, 1300, 'Supplier invoice above commitment');
        $this->addProjectLine($quotation, null, 1200, 'Export line still missing cost code');

        $response = $this->get(route('projects.report.export', $project));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('"Project commercial report"', $csv);
        $this->assertStringContainsString('"Project Code",PRJ-2026-025', $csv);
        $this->assertStringContainsString('"Commercial Summary"', $csv);
        $this->assertStringContainsString('"Customer Confirmed",1000.00', $csv);
        $this->assertStringContainsString('"Supplier Committed",1200.00', $csv);
        $this->assertStringContainsString('"Project Exceptions"', $csv);
        $this->assertStringContainsString('"Margin below target"', $csv);
        $this->assertStringContainsString('"Review Evidence"', $csv);
        $this->assertStringContainsString('"Work item missing",,CQ-2026-92501,"Customer quotation",2026-05-14', $csv);
        $this->assertStringContainsString('"Budget overrun","2.01 - Exported work item",SPO-2026-92501,"Purchase order",2026-05-14', $csv);
        $this->assertStringContainsString('"Supplier invoice above commitment"', $csv);
        $this->assertStringContainsString('"Work Breakdown"', $csv);
        $this->assertStringContainsString('2.01,"Exported work item",Active,Service,3,1500.00,1000.00,0.00,1000.00,0.00,1200.00,0.00,1300.00,-200.00,-300.00', $csv);
        $this->assertStringContainsString('"Unassigned project lines",,,1', $csv);
    }

    public function test_project_commercial_report_export_includes_billing_progress(): void
    {
        $project = $this->createProject([
            'project_code' => 'PRJ-2026-027',
            'name' => 'Billing export project',
        ]);
        $customerPo = $this->createLinkedDocument($project, 'customer_po', 'CPO-2026-92601', 1000);
        $customerInvoice = $this->createLinkedDocument($project, 'customer_invoice', 'INV-2026-92601', 600);
        $supplierPo = $this->createLinkedDocument($project, 'supplier_po', 'SPO-2026-92601', 900);
        $supplierInvoice = $this->createLinkedDocument($project, 'supplier_invoice', 'SIN-2026-92601', 300);

        $this->attachMilestoneSchedule($customerPo, [
            ['stage_name' => 'Deposit', 'amount' => 600, 'percentage' => 60],
            ['stage_name' => 'Final Claim', 'amount' => 400, 'percentage' => 40],
        ]);
        $this->attachMilestoneSchedule($customerInvoice, [
            ['stage_name' => 'Deposit', 'amount' => 600, 'percentage' => 60, 'current_invoice' => 600, 'is_current' => true],
            ['stage_name' => 'Final Claim', 'amount' => 400, 'percentage' => 40, 'remaining_amount' => 400],
        ], [
            'progress_invoice_number' => 1,
            'progress_invoice_total' => 2,
            'billing_stage_name' => 'Deposit',
        ]);
        $this->attachMilestoneSchedule($supplierPo, [
            ['stage_name' => 'Deposit', 'amount' => 300, 'percentage' => 33.33],
            ['stage_name' => 'Final Claim', 'amount' => 600, 'percentage' => 66.67],
        ]);
        $this->attachMilestoneSchedule($supplierInvoice, [
            ['stage_name' => 'Deposit', 'amount' => 300, 'percentage' => 33.33, 'current_invoice' => 300, 'is_current' => true],
            ['stage_name' => 'Final Claim', 'amount' => 600, 'percentage' => 66.67, 'remaining_amount' => 600],
        ], [
            'progress_invoice_number' => 1,
            'progress_invoice_total' => 2,
            'billing_stage_name' => 'Deposit',
        ]);

        $response = $this->get(route('projects.report.export', $project));

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('"Billing Progress"', $csv);
        $this->assertStringContainsString('"Customer billing",1,2,1,1000.00,600.00,400.00,0.00,"No. 1 of 2",Deposit', $csv);
        $this->assertStringContainsString('"Supplier billing",1,2,1,900.00,300.00,600.00,0.00,"No. 1 of 2",Deposit', $csv);
        $this->assertStringContainsString('"Recent Staged Invoices"', $csv);
        $this->assertStringContainsString('INV-2026-92601,"Customer invoice","Acme Trading Sdn Bhd",2026-05-14,"No. 1 of 2",Deposit,600.00', $csv);
        $this->assertStringContainsString('SIN-2026-92601,"Supplier invoice","Best Supplies Sdn Bhd",2026-05-14,"No. 1 of 2",Deposit,300.00', $csv);
    }

    public function test_project_commercial_report_export_includes_retention_summary(): void
    {
        $project = $this->createProject([
            'project_code' => 'PRJ-2026-029',
            'name' => 'Retention export project',
        ]);
        $customerInvoice = $this->createLinkedDocument($project, 'customer_invoice', 'INV-2026-92901', 1000);
        $supplierInvoice = $this->createLinkedDocument($project, 'supplier_invoice', 'SIN-2026-92901', 800);

        $this->attachRetention($customerInvoice, 5, 50, '2026-08-01');
        $this->attachRetention($supplierInvoice, 2.5, 20, '2026-08-15');

        $response = $this->get(route('projects.report.export', $project));

        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringContainsString('"Retention Summary"', $csv);
        $this->assertStringContainsString('"Customer Retention Held",50.00', $csv);
        $this->assertStringContainsString('"Supplier Retention Held",20.00', $csv);
        $this->assertStringContainsString('"Net Retention Exposure",30.00', $csv);
        $this->assertStringContainsString('"Customer Retention Release",2026-08-01', $csv);
        $this->assertStringContainsString('"Supplier Retention Release",2026-08-15', $csv);
        $this->assertStringContainsString('"Documents With Retention",2', $csv);
    }

    public function test_project_commercial_report_export_includes_variation_orders(): void
    {
        $project = $this->createProject([
            'project_code' => 'PRJ-2026-033',
            'name' => 'Variation export project',
            'contract_value' => 25000,
            'budget_amount' => 15000,
        ]);
        $sourceDocument = $this->createLinkedDocument($project, 'customer_quotation', 'CQ-2026-93301', 2200);

        $this->createVariation($project, [
            'variation_number' => 'VO-2026-020',
            'title' => 'UPS capacity increase',
            'status' => 'approved',
            'effective_date' => '2026-05-21',
            'customer_value' => 2200,
            'supplier_cost' => 1200,
            'source_document_id' => $sourceDocument->id,
            'notes' => 'Approved by signed change request.',
        ]);

        $response = $this->get(route('projects.report.export', $project));

        $response->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('"Variation Orders"', $csv);
        $this->assertStringContainsString('"Approved Customer Change",2200.00', $csv);
        $this->assertStringContainsString('"Revised Contract Value",27200.00', $csv);
        $this->assertStringContainsString('"Variation Order Register"', $csv);
        $this->assertStringContainsString('VO-2026-020,"UPS capacity increase",Approved,2026-05-21,2200.00,1200.00,1000.00,CQ-2026-93301,"Customer quotation","Approved by signed change request."', $csv);
    }

    public function test_projects_index_shows_portfolio_commercial_review(): void
    {
        $riskProject = $this->createProject([
            'project_code' => 'PRJ-2026-021',
            'name' => 'Margin review project',
            'budget_amount' => 1000,
            'margin_target_percent' => 25,
        ]);
        $riskWorkItem = $this->createWorkItem($riskProject, [
            'cost_budget' => 1000,
        ]);
        $riskCustomerPo = $this->createLinkedDocument($riskProject, 'customer_po', 'CPO-2026-93001', 1000);
        $riskSupplierPo = $this->createLinkedDocument($riskProject, 'supplier_po', 'SPO-2026-93001', 1200);
        $riskSupplierInvoice = $this->createLinkedDocument($riskProject, 'supplier_invoice', 'SIN-2026-93001', 1300);
        $riskQuotation = $this->createLinkedDocument($riskProject, 'customer_quotation', 'CQ-2026-93001', 1200);

        $this->addProjectLine($riskCustomerPo, $riskWorkItem, 1000);
        $this->addProjectLine($riskSupplierPo, $riskWorkItem, 1200);
        $this->addProjectLine($riskSupplierInvoice, $riskWorkItem, 1300);
        $this->addProjectLine($riskQuotation, null, 1200);

        $healthyProject = $this->createProject([
            'project_code' => 'PRJ-2026-022',
            'name' => 'Healthy project',
            'budget_amount' => 5000,
            'margin_target_percent' => 10,
        ]);
        $this->createLinkedDocument($healthyProject, 'customer_po', 'CPO-2026-93002', 5000);
        $this->createLinkedDocument($healthyProject, 'supplier_po', 'SPO-2026-93002', 2000);

        $index = $this->get(route('projects.index'));

        $index->assertOk();
        $index->assertSee('Portfolio totals');
        $index->assertSee('Projects needing review');
        $index->assertSee('Customer confirmed');
        $index->assertSee('Supplier committed');
        $index->assertSee('Expected margin');
        $index->assertSee('Budget remaining');
        $index->assertSee('Review needed');
        $index->assertSee('No visible exceptions');
        $index->assertSee('Margin below target');
        $index->assertSee('Budget overrun');
        $index->assertSee('Work item missing');
        $index->assertSee('Open project');
    }

    public function test_projects_can_be_filtered_by_commercial_review_status(): void
    {
        $riskProject = $this->createProject([
            'project_code' => 'PRJ-2026-023',
            'name' => 'Work item review filter project',
            'budget_amount' => 5000,
            'margin_target_percent' => 0,
        ]);
        $riskWorkItem = $this->createWorkItem($riskProject, [
            'cost_budget' => 1000,
        ]);
        $riskSupplierPo = $this->createLinkedDocument($riskProject, 'supplier_po', 'SPO-2026-93501', 1200);

        $this->addProjectLine($riskSupplierPo, $riskWorkItem, 1200);

        $healthyProject = $this->createProject([
            'project_code' => 'PRJ-2026-024',
            'name' => 'Clear review filter project',
            'budget_amount' => 5000,
            'margin_target_percent' => 10,
        ]);
        $this->createLinkedDocument($healthyProject, 'customer_po', 'CPO-2026-93502', 5000);
        $this->createLinkedDocument($healthyProject, 'supplier_po', 'SPO-2026-93502', 2000);

        $needsReview = $this->get(route('projects.index', ['review' => 'needs_review']));

        $needsReview->assertOk();
        $needsReview->assertSee('Commercial review');
        $needsReview->assertSee('Filtered totals');
        $needsReview->assertSeeTextInOrder(['Total projects', '1', 'Projects needing review', '1']);
        $needsReview->assertSee('Work item review filter project');
        $needsReview->assertSee('Work item budget overrun');
        $needsReview->assertDontSee('Clear review filter project');

        $clear = $this->get(route('projects.index', ['review' => 'clear']));

        $clear->assertOk();
        $clear->assertSeeTextInOrder(['Total projects', '1', 'Projects needing review', '0']);
        $clear->assertSee('Clear review filter project');
        $clear->assertDontSee('Work item review filter project');

        $export = $this->get(route('projects.export', ['review' => 'needs_review']));

        $export->assertOk();
        $csv = $export->streamedContent();

        $this->assertStringContainsString('PRJ-2026-023,"Work item review filter project",Active', $csv);
        $this->assertStringContainsString('"Review needed","Work item budget overrun"', $csv);
        $this->assertStringNotContainsString('Clear review filter project', $csv);
    }

    public function test_project_commercial_review_export_uses_project_filters(): void
    {
        $riskProject = $this->createProject([
            'project_code' => 'PRJ-2026-031',
            'name' => 'Margin review export project',
            'budget_amount' => 1000,
            'margin_target_percent' => 25,
        ]);
        $riskWorkItem = $this->createWorkItem($riskProject, [
            'cost_budget' => 1000,
        ]);
        $riskCustomerPo = $this->createLinkedDocument($riskProject, 'customer_po', 'CPO-2026-94001', 1000);
        $riskSupplierPo = $this->createLinkedDocument($riskProject, 'supplier_po', 'SPO-2026-94001', 1200);
        $riskQuotation = $this->createLinkedDocument($riskProject, 'customer_quotation', 'CQ-2026-94001', 1200);

        $this->addProjectLine($riskCustomerPo, $riskWorkItem, 1000);
        $this->addProjectLine($riskSupplierPo, $riskWorkItem, 1200);
        $this->addProjectLine($riskQuotation, null, 1200);

        $healthyProject = $this->createProject([
            'project_code' => 'PRJ-2026-032',
            'name' => 'Healthy export project',
            'budget_amount' => 5000,
            'margin_target_percent' => 10,
        ]);
        $this->createLinkedDocument($healthyProject, 'customer_po', 'CPO-2026-94002', 5000);
        $this->createLinkedDocument($healthyProject, 'supplier_po', 'SPO-2026-94002', 2000);

        $response = $this->get(route('projects.export', ['q' => 'Margin review export']));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('"Project Code","Project Name",Status,Customer,Manager,"Target Date","Linked Documents","Customer Confirmed","Customer Invoiced","Supplier Committed","Supplier Invoiced","Expected Margin","Expected Margin %","Budget Remaining","Unassigned Project Lines","Review Status","Review Reasons"', $csv);
        $this->assertStringContainsString('PRJ-2026-031,"Margin review export project",Active', $csv);
        $this->assertStringContainsString('1000.00,0.00,1200.00,0.00,-200.00,-20.00,-200.00,1,"Review needed","Margin below target; Budget overrun; Work item missing; Work item budget overrun"', $csv);
        $this->assertStringNotContainsString('Healthy export project', $csv);
    }

    public function test_admin_can_create_and_update_project_records(): void
    {
        $this->post(route('projects.store'), [
            'project_code' => 'PRJ-2026-010',
            'name' => 'Data centre migration',
            'customer_id' => $this->customer->id,
            'manager_id' => $this->manager->id,
            'status' => 'active',
            'start_date' => '2026-05-01',
            'expected_completion_date' => '2026-06-30',
            'contract_value' => 50000,
            'budget_amount' => 35000,
            'margin_target_percent' => 25,
            'description' => 'Migration project for UAT coverage.',
        ])->assertRedirect();

        $project = Project::query()->where('project_code', 'PRJ-2026-010')->firstOrFail();

        $this->assertDatabaseHas('projects', [
            'project_code' => 'PRJ-2026-010',
            'name' => 'Data centre migration',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'project_created',
            'auditable_type' => Project::class,
            'auditable_id' => $project->id,
        ]);

        $this->put(route('projects.update', $project), [
            'project_code' => 'PRJ-2026-010',
            'name' => 'Data centre migration phase 1',
            'customer_id' => $this->customer->id,
            'manager_id' => $this->manager->id,
            'status' => 'on_hold',
            'start_date' => '2026-05-01',
            'expected_completion_date' => '2026-07-15',
            'contract_value' => 50000,
            'budget_amount' => 35000,
            'margin_target_percent' => 25,
            'description' => 'Updated project status.',
        ])->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Data centre migration phase 1',
            'status' => 'on_hold',
        ]);
    }

    public function test_document_flow_still_allows_no_project_and_can_link_project_when_needed(): void
    {
        $project = $this->createProject();

        $form = $this->get(route('documents.create', 'customer-quotations'));
        $form->assertOk();
        $form->assertSee('Project / job');
        $form->assertSee('Project / site note');

        $this->post(route('documents.store', 'customer-quotations'), $this->documentPayload([
            'external_reference' => 'NO-PROJECT-CQ',
            'project_id' => null,
        ]))->assertRedirect();

        $simpleQuotation = Document::query()->where('external_reference', 'NO-PROJECT-CQ')->firstOrFail();
        $this->assertNull($simpleQuotation->project_id);

        $this->post(route('documents.store', 'customer-quotations'), $this->documentPayload([
            'external_reference' => 'PROJECT-CQ',
            'project_id' => $project->id,
        ]))->assertRedirect();

        $projectQuotation = Document::query()->where('external_reference', 'PROJECT-CQ')->firstOrFail();
        $this->assertSame($project->id, $projectQuotation->project_id);

        $show = $this->get(route('documents.show', $projectQuotation));
        $show->assertOk();
        $show->assertSee('Project / job');
        $show->assertSee($project->displayLabel());
        $show->assertSee(route('projects.show', $project), false);
    }

    public function test_project_work_items_can_be_managed_and_used_on_document_lines(): void
    {
        $project = $this->createProject();

        $this->post(route('projects.work-items.store', $project), [
            'code' => '1.01',
            'name' => 'Installation and commissioning',
            'description' => 'Site installation, testing, and handover.',
            'cost_type' => 'service',
            'revenue_budget' => 15000,
            'cost_budget' => 9000,
            'sort_order' => 10,
            'status' => 'active',
        ])->assertRedirect(route('projects.show', $project));

        $workItem = WbsItem::query()->where('project_id', $project->id)->where('code', '1.01')->firstOrFail();

        $this->assertDatabaseHas('audit_trails', [
            'action' => 'work_item_created',
            'auditable_type' => WbsItem::class,
            'auditable_id' => $workItem->id,
        ]);

        $show = $this->get(route('projects.show', $project));
        $show->assertOk();
        $show->assertSee('Work breakdown');
        $show->assertSee('Installation and commissioning');
        $show->assertSee('Cost code');

        $this->post(route('documents.store', 'customer-quotations'), $this->documentPayload([
            'external_reference' => 'WBS-CQ',
            'project_id' => $project->id,
            'items' => [
                [
                    'product_id' => $this->service->id,
                    'wbs_item_id' => $workItem->id,
                    'description' => 'Installation and commissioning services',
                    'quantity' => 2,
                    'unit' => 'job',
                    'unit_price' => 1200,
                    'tax_rate' => 8,
                ],
            ],
        ]))->assertRedirect();

        $quotation = Document::query()->where('external_reference', 'WBS-CQ')->firstOrFail();
        $quotationLine = $quotation->items()->firstOrFail();

        $this->assertSame($project->id, $quotationLine->project_id);
        $this->assertSame($workItem->id, $quotationLine->wbs_item_id);

        $quotation->update(['status' => 'approved']);

        $this->post(route('documents.convert', [$quotation, 'customer-pos']))
            ->assertRedirect();

        $customerPo = Document::query()
            ->where('type', 'customer_po')
            ->where('related_document_id', $quotation->id)
            ->firstOrFail();
        $customerPoLine = $customerPo->items()->firstOrFail();

        $this->assertSame($project->id, $customerPoLine->project_id);
        $this->assertSame($workItem->id, $customerPoLine->wbs_item_id);
    }

    public function test_document_rejects_work_item_from_another_project(): void
    {
        $project = $this->createProject();
        $otherProject = $this->createProject([
            'project_code' => 'PRJ-2026-002',
            'name' => 'Other project',
        ]);
        $otherWorkItem = $otherProject->wbsItems()->create([
            'code' => '2.01',
            'name' => 'Other scope',
            'cost_type' => 'service',
            'revenue_budget' => 1000,
            'cost_budget' => 800,
            'sort_order' => 1,
            'status' => 'active',
        ]);

        $this->from(route('documents.create', 'customer-quotations'))
            ->post(route('documents.store', 'customer-quotations'), $this->documentPayload([
                'external_reference' => 'WRONG-WORK-ITEM',
                'project_id' => $project->id,
                'items' => [
                    [
                        'product_id' => $this->service->id,
                        'wbs_item_id' => $otherWorkItem->id,
                        'description' => 'Wrong project work item',
                        'quantity' => 1,
                        'unit' => 'job',
                        'unit_price' => 1200,
                        'tax_rate' => 8,
                    ],
                ],
            ]))
            ->assertRedirect(route('documents.create', 'customer-quotations'))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseMissing('documents', [
            'external_reference' => 'WRONG-WORK-ITEM',
        ]);
    }

    public function test_used_work_items_cannot_be_deleted(): void
    {
        $project = $this->createProject();
        $workItem = $project->wbsItems()->create([
            'code' => '1.02',
            'name' => 'Procurement and delivery',
            'cost_type' => 'material',
            'revenue_budget' => 5000,
            'cost_budget' => 3000,
            'sort_order' => 20,
            'status' => 'active',
        ]);
        $document = $this->createLinkedDocument($project, 'supplier_po', 'SPO-2026-91001', 3000);

        DocumentItem::create([
            'document_id' => $document->id,
            'product_id' => $this->service->id,
            'project_id' => $project->id,
            'wbs_item_id' => $workItem->id,
            'description' => 'Procurement service',
            'quantity' => 1,
            'unit' => 'job',
            'unit_price' => 3000,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'line_total' => 3000,
        ]);

        $this->delete(route('projects.work-items.destroy', [$project, $workItem]))
            ->assertRedirect(route('projects.show', $project))
            ->assertSessionHasErrors('work_item');

        $this->assertDatabaseHas('wbs_items', [
            'id' => $workItem->id,
            'code' => '1.02',
        ]);
    }

    public function test_next_document_conversion_preserves_project_link(): void
    {
        $project = $this->createProject();

        $this->post(route('documents.store', 'customer-quotations'), $this->documentPayload([
            'external_reference' => 'CONVERT-PROJECT-CQ',
            'project_id' => $project->id,
        ]))->assertRedirect();

        $quotation = Document::query()->where('external_reference', 'CONVERT-PROJECT-CQ')->firstOrFail();
        $quotation->update(['status' => 'approved']);

        $this->post(route('documents.convert', [$quotation, 'customer-pos']))
            ->assertRedirect();

        $customerPo = Document::query()
            ->where('type', 'customer_po')
            ->where('related_document_id', $quotation->id)
            ->firstOrFail();

        $this->assertSame($project->id, $customerPo->project_id);
    }

    public function test_simple_document_approval_does_not_require_project_commercial_reason(): void
    {
        $this->post(route('documents.store', 'customer-quotations'), $this->documentPayload([
            'external_reference' => 'SIMPLE-CQ-APPROVAL',
            'project_id' => null,
        ]))->assertRedirect();

        $quotation = Document::query()->where('external_reference', 'SIMPLE-CQ-APPROVAL')->firstOrFail();

        $this->post(route('documents.submit', $quotation))->assertRedirect();
        $this->assertSame('pending_approval', $quotation->refresh()->status);

        $this->post(route('documents.approve', $quotation))->assertRedirect();

        $this->assertSame('approved', $quotation->refresh()->status);
        $this->assertDatabaseMissing('audit_trails', [
            'action' => 'project_commercial_approval_override',
            'auditable_type' => Document::class,
            'auditable_id' => $quotation->id,
        ]);
    }

    public function test_project_customer_quotation_margin_exception_requires_approval_reason(): void
    {
        $project = $this->createProject([
            'margin_target_percent' => 25,
        ]);
        $workItem = $this->createWorkItem($project);
        $this->service->update(['cost_price' => 1100]);

        $this->post(route('documents.store', 'customer-quotations'), $this->documentPayload([
            'external_reference' => 'LOW-MARGIN-CQ',
            'project_id' => $project->id,
            'items' => [
                [
                    'product_id' => $this->service->id,
                    'wbs_item_id' => $workItem->id,
                    'description' => 'Low margin project quotation line',
                    'quantity' => 1,
                    'unit' => 'job',
                    'unit_price' => 1200,
                    'tax_rate' => 0,
                ],
            ],
        ]))->assertRedirect();

        $quotation = Document::query()->where('external_reference', 'LOW-MARGIN-CQ')->firstOrFail();
        $this->post(route('documents.submit', $quotation))->assertRedirect();

        $show = $this->get(route('documents.show', $quotation));
        $show->assertOk();
        $show->assertSee('Budget and margin impact');
        $show->assertSee('Margin below target');
        $show->assertSee('Expected gross margin');
        $show->assertSee('Approval reason');

        $this->from(route('documents.show', $quotation))
            ->post(route('documents.approve', $quotation))
            ->assertRedirect(route('documents.show', $quotation))
            ->assertSessionHasErrors('comment');

        $this->assertSame('pending_approval', $quotation->refresh()->status);

        $this->post(route('documents.approve', $quotation), [
            'comment' => 'Approved because the customer relationship justifies this lower first-stage margin.',
        ])->assertRedirect();

        $this->assertSame('approved', $quotation->refresh()->status);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'project_commercial_approval_override',
            'auditable_type' => Document::class,
            'auditable_id' => $quotation->id,
        ]);
    }

    public function test_project_supplier_po_budget_overrun_requires_approval_reason(): void
    {
        $project = $this->createProject();
        $workItem = $this->createWorkItem($project, [
            'cost_budget' => 1000,
        ]);
        $existingPurchaseOrder = $this->createLinkedDocument($project, 'supplier_po', 'SPO-2026-91010', 800);

        DocumentItem::create([
            'document_id' => $existingPurchaseOrder->id,
            'product_id' => $this->service->id,
            'project_id' => $project->id,
            'wbs_item_id' => $workItem->id,
            'description' => 'Previously committed installation materials',
            'quantity' => 1,
            'unit' => 'job',
            'unit_price' => 800,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'line_total' => 800,
        ]);

        $currentPurchaseOrder = Document::create([
            'type' => 'supplier_po',
            'direction' => 'incoming',
            'document_number' => 'SPO-2026-91011',
            'external_reference' => 'SPO-OVER-BUDGET',
            'supplier_id' => $this->supplier->id,
            'project_id' => $project->id,
            'status' => 'draft',
            'issue_date' => '2026-05-14',
            'currency' => 'MYR',
            'subtotal' => 500,
            'tax_total' => 0,
            'total' => 500,
            'created_by' => $this->admin->id,
        ]);

        DocumentItem::create([
            'document_id' => $currentPurchaseOrder->id,
            'product_id' => $this->service->id,
            'project_id' => $project->id,
            'wbs_item_id' => $workItem->id,
            'description' => 'Additional installation materials',
            'quantity' => 1,
            'unit' => 'job',
            'unit_price' => 500,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'line_total' => 500,
        ]);

        $this->post(route('documents.submit', $currentPurchaseOrder))->assertRedirect();

        $show = $this->get(route('documents.show', $currentPurchaseOrder));
        $show->assertOk();
        $show->assertSee('Budget and margin impact');
        $show->assertSee('Budget overrun');
        $show->assertSee('Approval reason');

        $this->from(route('documents.show', $currentPurchaseOrder))
            ->post(route('documents.approve', $currentPurchaseOrder))
            ->assertRedirect(route('documents.show', $currentPurchaseOrder))
            ->assertSessionHasErrors('comment');

        $this->assertSame('pending_approval', $currentPurchaseOrder->refresh()->status);

        $this->post(route('documents.approve', $currentPurchaseOrder), [
            'comment' => 'Approved because the extra materials are required to complete the signed project scope.',
        ])->assertRedirect();

        $this->assertSame('approved', $currentPurchaseOrder->refresh()->status);
        $this->assertDatabaseHas('audit_trails', [
            'action' => 'project_commercial_approval_override',
            'auditable_type' => Document::class,
            'auditable_id' => $currentPurchaseOrder->id,
        ]);
    }

    private function createProject(array $overrides = []): Project
    {
        return Project::create(array_merge([
            'project_code' => 'PRJ-2026-001',
            'name' => 'Cyberjaya network rollout',
            'customer_id' => $this->customer->id,
            'manager_id' => $this->manager->id,
            'status' => 'active',
            'start_date' => '2026-05-01',
            'expected_completion_date' => '2026-06-30',
            'contract_value' => 25000,
            'budget_amount' => 15000,
            'margin_target_percent' => 25,
            'description' => 'Network rollout with staged procurement and installation.',
        ], $overrides));
    }

    private function createWorkItem(Project $project, array $overrides = []): WbsItem
    {
        return $project->wbsItems()->create(array_merge([
            'code' => '1.01',
            'name' => 'Installation and commissioning',
            'cost_type' => 'service',
            'revenue_budget' => 15000,
            'cost_budget' => 9000,
            'sort_order' => 10,
            'status' => 'active',
        ], $overrides));
    }

    private function createLinkedDocument(Project $project, string $type, string $number, float $total): Document
    {
        return Document::create([
            'type' => $type,
            'direction' => str_starts_with($type, 'customer_') ? 'outgoing' : 'incoming',
            'document_number' => $number,
            'external_reference' => $number,
            'customer_id' => str_starts_with($type, 'customer_') ? $this->customer->id : null,
            'supplier_id' => str_starts_with($type, 'supplier_') ? $this->supplier->id : null,
            'project_id' => $project->id,
            'status' => 'approved',
            'issue_date' => '2026-05-14',
            'currency' => 'MYR',
            'subtotal' => $total,
            'tax_total' => 0,
            'total' => $total,
            'created_by' => $this->admin->id,
        ]);
    }

    private function addProjectLine(Document $document, ?WbsItem $workItem, float $total, ?string $description = null): DocumentItem
    {
        return DocumentItem::create([
            'document_id' => $document->id,
            'product_id' => $this->service->id,
            'project_id' => $document->project_id,
            'wbs_item_id' => $workItem?->id,
            'description' => $description ?? 'Project report test line',
            'quantity' => 1,
            'unit' => 'job',
            'unit_price' => $total,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'line_total' => $total,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $stages
     * @param  array<string, mixed>  $documentOverrides
     */
    private function attachMilestoneSchedule(Document $document, array $stages, array $documentOverrides = []): void
    {
        $document->update(array_merge([
            'payment_terms_type' => 'milestone',
            'progress_invoice_number' => null,
            'progress_invoice_total' => null,
            'billing_stage_name' => null,
        ], $documentOverrides));

        $document->billingStages()->delete();

        foreach (array_values($stages) as $index => $stage) {
            $document->billingStages()->create([
                'sort_order' => $index + 1,
                'stage_name' => $stage['stage_name'],
                'condition_label' => $stage['condition_label'] ?? null,
                'percentage' => (float) ($stage['percentage'] ?? 0),
                'amount' => (float) ($stage['amount'] ?? 0),
                'payment_term' => $stage['payment_term'] ?? null,
                'previously_invoiced' => (float) ($stage['previously_invoiced'] ?? 0),
                'current_invoice' => (float) ($stage['current_invoice'] ?? 0),
                'remaining_amount' => (float) ($stage['remaining_amount'] ?? 0),
                'is_current' => (bool) ($stage['is_current'] ?? false),
            ]);
        }
    }

    private function attachRetention(Document $document, float $percent, float $amount, string $releaseDate): void
    {
        $document->update([
            'retention_percent' => $percent,
            'retention_amount' => $amount,
            'retention_release_date' => $releaseDate,
        ]);
    }

    private function createVariation(Project $project, array $overrides = []): ProjectVariation
    {
        return $project->variations()->create(array_merge([
            'variation_number' => 'VO-2026-001',
            'title' => 'Project variation order',
            'status' => 'pending_review',
            'effective_date' => null,
            'customer_value' => 0,
            'supplier_cost' => 0,
            'source_document_id' => null,
            'notes' => null,
        ], $overrides));
    }

    private function documentPayload(array $overrides = []): array
    {
        return array_merge([
            'customer_id' => $this->customer->id,
            'supplier_id' => null,
            'related_document_id' => null,
            'project_id' => null,
            'external_reference' => 'PROJECT-CQ',
            'issue_date' => '2026-05-14',
            'due_date' => '2026-06-13',
            'currency' => 'MYR',
            'items' => [
                [
                    'product_id' => $this->service->id,
                    'description' => 'Project linked quotation line',
                    'wbs_item_id' => null,
                    'quantity' => 2,
                    'unit' => 'job',
                    'unit_price' => 1200,
                    'tax_rate' => 8,
                ],
            ],
            'notes' => 'Project control document test.',
            'terms' => 'Generated by automated coverage.',
        ], $overrides);
    }
}
