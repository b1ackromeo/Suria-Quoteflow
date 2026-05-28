<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Product;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PhaseFiveProjectDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            CompanyProfile::updateOrCreate(
                ['name' => 'RC Technology Resources'],
                array_merge(CompanyProfile::defaults(), [
                    'name' => 'RC Technology Resources',
                    'registration_number' => '202603107223 (003844744-P)',
                    'email' => 'rctech@gmail.com',
                    'phone' => '+60 16-445 2786',
                    'address' => '1-3 Level 1, Jalan Perdana Blok 4801, CBD Perdana, Cyberjaya',
                    'tagline' => 'Reliable Infrastructure. Connected Future.',
                    'country' => 'Malaysia',
                    'timezone' => 'Asia/Kuala_Lumpur',
                    'base_currency' => 'MYR',
                    'currency_display' => 'symbol',
                    'currency_symbol_override' => 'RM',
                    'number_format' => 'en-MY',
                    'tax_label' => 'Tax',
                    'tax_registration_label' => 'Tax Registration No.',
                    'default_tax_rate' => 0,
                    'payment_instructions' => "Bank transfer only.\nUse the document number as payment reference.\nSend payment advice to rctech@gmail.com.",
                    'pdf_footer' => 'RC Technology Resources | Reg. No. 202603107223 (003844744-P) | rctech@gmail.com',
                    'is_active' => true,
                ])
            );

            $admin = User::updateOrCreate(
                ['email' => 'admin@quoteflow.test'],
                [
                    'name' => 'QuoteFlow Admin',
                    'password' => Hash::make('Password123!'),
                    'role' => 'admin',
                    'is_active' => true,
                ]
            );
            $manager = User::updateOrCreate(
                ['email' => 'manager@quoteflow.test'],
                [
                    'name' => 'QuoteFlow Manager',
                    'password' => Hash::make('Password123!'),
                    'role' => 'manager',
                    'is_active' => true,
                ]
            );
            $customer = Customer::updateOrCreate(
                ['code' => 'ACME'],
                [
                    'name' => 'Acme Trading Sdn Bhd',
                    'email' => 'accounts@acme.test',
                    'payment_terms_days' => 30,
                    'is_active' => true,
                ]
            );
            $supplier = Supplier::updateOrCreate(
                ['code' => 'BESTSUP'],
                [
                    'name' => 'Best Supplies Sdn Bhd',
                    'category' => 'Materials / Hardware',
                    'email' => 'accounts@bestsup.test',
                    'payment_terms_days' => 30,
                    'is_active' => true,
                ]
            );
            $hardware = Product::updateOrCreate(
                ['sku' => 'HW-KIT'],
                [
                    'type' => 'product',
                    'name' => 'Operations hardware kit',
                    'description' => 'Supply of approved operations hardware kit, accessories, and basic readiness support for project deployment.',
                    'unit' => 'set',
                    'selling_price' => 4200,
                    'cost_price' => 2850,
                    'tax_rate' => 8,
                    'is_active' => true,
                ]
            );
            $service = Product::updateOrCreate(
                ['sku' => 'SVC-IMPL'],
                [
                    'type' => 'service',
                    'name' => 'Implementation service',
                    'description' => 'Implementation, configuration, testing, documentation, and handover support for approved project scope.',
                    'unit' => 'job',
                    'selling_price' => 1500,
                    'cost_price' => 900,
                    'tax_rate' => 8,
                    'is_active' => true,
                ]
            );

            $project = Project::updateOrCreate(
                ['project_code' => 'PRJ-2026-P5-DEMO'],
                [
                    'name' => 'Cyberjaya data hall upgrade',
                    'customer_id' => $customer->id,
                    'manager_id' => $manager->id,
                    'status' => 'active',
                    'start_date' => '2026-05-01',
                    'expected_completion_date' => '2026-08-31',
                    'contract_value' => 50000,
                    'budget_amount' => 32000,
                    'margin_target_percent' => 25,
                    'description' => 'Phase 5 demo project with staged billing, partial delivery, retention, and variation order reporting.',
                ]
            );

            $equipment = $project->wbsItems()->updateOrCreate(
                ['code' => '1.01'],
                [
                    'name' => 'Network equipment supply',
                    'cost_type' => 'material',
                    'revenue_budget' => 26000,
                    'cost_budget' => 18000,
                    'sort_order' => 10,
                    'status' => 'active',
                ]
            );
            $installation = $project->wbsItems()->updateOrCreate(
                ['code' => '2.01'],
                [
                    'name' => 'Installation and commissioning',
                    'cost_type' => 'service',
                    'revenue_budget' => 18000,
                    'cost_budget' => 9000,
                    'sort_order' => 20,
                    'status' => 'active',
                ]
            );

            $makeDocument = function (string $type, string $direction, string $number, string $status, float $total, ?Document $related = null) use ($admin, $customer, $project, $supplier): Document {
                return Document::updateOrCreate(
                    ['type' => $type, 'document_number' => $number],
                    [
                        'direction' => $direction,
                        'external_reference' => $number,
                        'customer_id' => str_starts_with($type, 'customer_') ? $customer->id : null,
                        'supplier_id' => str_starts_with($type, 'supplier_') || $type === 'goods_receipt' ? $supplier->id : null,
                        'related_document_id' => $related?->id,
                        'project_id' => $project->id,
                        'status' => $status,
                        'issue_date' => '2026-05-14',
                        'due_date' => str_contains($type, 'invoice') ? '2026-06-13' : null,
                        'currency' => 'MYR',
                        'payment_terms_type' => 'standard',
                        'payment_due_days' => 30,
                        'subtotal' => $total,
                        'tax_total' => 0,
                        'total' => $total,
                        'notes' => 'Phase 5 project demo data for billing and delivery reports.',
                        'terms' => 'Use for local review of project billing controls.',
                        'created_by' => $admin->id,
                        'approved_by' => $admin->id,
                        'approved_at' => now()->subDays(2),
                    ]
                );
            };

            $replaceLines = function (Document $document, array $lines): void {
                $document->items()->delete();

                foreach ($lines as $line) {
                    $document->items()->create([
                        'product_id' => $line['product']->id,
                        'project_id' => $document->project_id,
                        'wbs_item_id' => $line['work_item']->id,
                        'description' => $line['description'],
                        'quantity' => 1,
                        'unit' => $line['product']->unit,
                        'unit_price' => $line['amount'],
                        'tax_rate' => 0,
                        'tax_amount' => 0,
                        'line_total' => $line['amount'],
                    ]);
                }
            };

            $replaceStages = function (Document $document, array $stages, array $attributes = []): void {
                $document->update(array_merge([
                    'payment_terms_type' => 'milestone',
                    'progress_invoice_number' => null,
                    'progress_invoice_total' => null,
                    'billing_stage_name' => null,
                ], $attributes));
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
            };

            $customerPo = $makeDocument('customer_po', 'outgoing', 'CPO-2026-P5-001', 'approved', 30000);
            $replaceLines($customerPo, [
                ['product' => $hardware, 'work_item' => $equipment, 'description' => 'Customer PO received for network equipment supply', 'amount' => 18000],
                ['product' => $service, 'work_item' => $installation, 'description' => 'Customer PO received for installation and commissioning', 'amount' => 12000],
            ]);
            $replaceStages($customerPo, [
                ['stage_name' => 'Deposit', 'amount' => 15000, 'percentage' => 50, 'payment_term' => 'Due on project kickoff'],
                ['stage_name' => 'Final handover', 'amount' => 15000, 'percentage' => 50, 'payment_term' => 'Due on customer acceptance'],
            ]);

            $customerInvoice = $makeDocument('customer_invoice', 'outgoing', 'INV-2026-P5-001', 'issued', 12000, $customerPo);
            $replaceLines($customerInvoice, [
                ['product' => $hardware, 'work_item' => $equipment, 'description' => 'First customer invoice for delivered equipment', 'amount' => 8000],
                ['product' => $service, 'work_item' => $installation, 'description' => 'First customer invoice for installation mobilisation', 'amount' => 4000],
            ]);
            $replaceStages($customerInvoice, [
                ['stage_name' => 'Deposit', 'amount' => 15000, 'percentage' => 50, 'current_invoice' => 12000, 'remaining_amount' => 3000, 'is_current' => true],
                ['stage_name' => 'Final handover', 'amount' => 15000, 'percentage' => 50, 'remaining_amount' => 15000],
            ], [
                'progress_invoice_number' => 1,
                'progress_invoice_total' => 2,
                'billing_stage_name' => 'Deposit',
                'retention_percent' => 5,
                'retention_amount' => 600,
                'retention_release_date' => '2026-08-31',
            ]);

            $supplierPo = $makeDocument('supplier_po', 'incoming', 'SPO-2026-P5-001', 'issued', 20000);
            $replaceLines($supplierPo, [
                ['product' => $hardware, 'work_item' => $equipment, 'description' => 'Purchase order for network equipment supply', 'amount' => 14000],
                ['product' => $service, 'work_item' => $installation, 'description' => 'Purchase order for installation support', 'amount' => 6000],
            ]);
            $replaceStages($supplierPo, [
                ['stage_name' => 'Hardware delivery', 'amount' => 12000, 'percentage' => 60, 'payment_term' => 'Due on valid supplier invoice'],
                ['stage_name' => 'Commissioning completion', 'amount' => 8000, 'percentage' => 40, 'payment_term' => 'Due after service acceptance'],
            ]);

            $goodsReceipt = $makeDocument('goods_receipt', 'incoming', 'GR-2026-P5-001', 'received', 9000, $supplierPo);
            $replaceLines($goodsReceipt, [
                ['product' => $hardware, 'work_item' => $equipment, 'description' => 'Goods receipt for partial equipment delivery', 'amount' => 7000],
                ['product' => $service, 'work_item' => $installation, 'description' => 'Service acceptance for site preparation', 'amount' => 2000],
            ]);

            $supplierInvoice = $makeDocument('supplier_invoice', 'incoming', 'SIN-2026-P5-001', 'matched', 6500, $goodsReceipt);
            $replaceLines($supplierInvoice, [
                ['product' => $hardware, 'work_item' => $equipment, 'description' => 'Supplier invoice for delivered equipment', 'amount' => 5000],
                ['product' => $service, 'work_item' => $installation, 'description' => 'Supplier invoice for site preparation', 'amount' => 1500],
            ]);
            $replaceStages($supplierInvoice, [
                ['stage_name' => 'Hardware delivery', 'amount' => 12000, 'percentage' => 60, 'current_invoice' => 6500, 'remaining_amount' => 5500, 'is_current' => true],
                ['stage_name' => 'Commissioning completion', 'amount' => 8000, 'percentage' => 40, 'remaining_amount' => 8000],
            ], [
                'progress_invoice_number' => 1,
                'progress_invoice_total' => 2,
                'billing_stage_name' => 'Hardware delivery',
                'retention_percent' => 2.5,
                'retention_amount' => 162.50,
                'retention_release_date' => '2026-09-15',
            ]);

            $project->variations()->updateOrCreate(
                ['variation_number' => 'VO-2026-P5-001'],
                [
                    'title' => 'Additional fiber tray route',
                    'status' => 'approved',
                    'effective_date' => '2026-05-20',
                    'customer_value' => 2500,
                    'supplier_cost' => 1400,
                    'source_document_id' => $customerPo->id,
                    'notes' => 'Approved scope change for additional containment route.',
                ]
            );
            $project->variations()->updateOrCreate(
                ['variation_number' => 'VO-2026-P5-002'],
                [
                    'title' => 'After-hours commissioning support',
                    'status' => 'pending_review',
                    'effective_date' => null,
                    'customer_value' => 1200,
                    'supplier_cost' => 900,
                    'source_document_id' => $supplierPo->id,
                    'notes' => 'Pending customer confirmation before revising the baseline.',
                ]
            );
        });
    }
}
