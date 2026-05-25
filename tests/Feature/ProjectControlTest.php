<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\Product;
use App\Models\Project;
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
        $show->assertSee('Project documents');
        $show->assertSee('Quoted revenue');
        $show->assertSee('Customer confirmed');
        $show->assertSee('Supplier committed');
        $show->assertSee('Expected margin');
        $show->assertSee('CQ-2026-90001');
        $show->assertSee('SPO-2026-90001');
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
