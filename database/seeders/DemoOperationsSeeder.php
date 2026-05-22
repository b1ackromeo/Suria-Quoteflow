<?php

namespace Database\Seeders;

use App\Models\Approval;
use App\Models\CompanyProfile;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoOperationsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            CompanyProfile::updateOrCreate(
                ['name' => 'RC Technology Resources'],
                array_merge(CompanyProfile::defaults(), [
                    'name' => 'RC Technology Resources',
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

            collect([
                ['name' => 'QuoteFlow Manager', 'email' => 'manager@quoteflow.test', 'role' => 'manager'],
                ['name' => 'QuoteFlow Sales User', 'email' => 'sales@quoteflow.test', 'role' => 'sales'],
                ['name' => 'QuoteFlow Procurement User', 'email' => 'procurement@quoteflow.test', 'role' => 'procurement'],
                ['name' => 'QuoteFlow Accounts User', 'email' => 'accounts@quoteflow.test', 'role' => 'accounts'],
                ['name' => 'QuoteFlow Viewer', 'email' => 'viewer@quoteflow.test', 'role' => 'viewer'],
            ])->each(fn (array $user) => User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'password' => Hash::make('Password123!'),
                    'role' => $user['role'],
                    'is_active' => true,
                ]
            ));

            $customers = collect([
                ['name' => 'Acme Trading Sdn Bhd', 'code' => 'ACME', 'payment_terms_days' => 30],
                ['name' => 'Global Enterprises Sdn Bhd', 'code' => 'GLOBAL', 'payment_terms_days' => 30],
                ['name' => 'Sunrise Solutions Sdn Bhd', 'code' => 'SUNRISE', 'payment_terms_days' => 45],
                ['name' => 'Tech Dynamics Sdn Bhd', 'code' => 'TECHDYN', 'payment_terms_days' => 30],
                ['name' => 'Innova Systems Sdn Bhd', 'code' => 'INNOVA', 'payment_terms_days' => 30],
            ])->mapWithKeys(fn ($row) => [
                $row['code'] => Customer::updateOrCreate(['code' => $row['code']], $row + ['is_active' => true]),
            ]);

            $suppliers = collect([
                ['name' => 'Best Supplies Sdn Bhd', 'code' => 'BESTSUP', 'category' => 'Materials / Hardware', 'payment_terms_days' => 30],
                ['name' => 'Metro Office Solutions', 'code' => 'METRO', 'category' => 'Office / Admin', 'payment_terms_days' => 30],
                ['name' => 'Prime Logistics Services', 'code' => 'PRIMELOG', 'category' => 'Logistics / Delivery', 'payment_terms_days' => 14],
            ])->mapWithKeys(fn ($row) => [
                $row['code'] => Supplier::updateOrCreate(['code' => $row['code']], $row + ['is_active' => true]),
            ]);

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

            $documents = [
                ['customer_quotation', 'outgoing', 'CQ-2026-00001', 'pending_approval', 'INQ-1001', 'ACME', null, '2026-05-13', '2026-06-12', 3240, $service, '2.000', 1500],
                ['customer_quotation', 'outgoing', 'CQ-2026-00012', 'pending_approval', 'INQ-1022', 'ACME', null, '2026-05-10', '2026-06-09', 8450, $service, '5.000', 1564.81],
                ['customer_po', 'outgoing', 'CPO-2026-00015', 'approved', 'PO-ACME-515', 'ACME', null, '2026-05-12', '2026-06-11', 15300, $hardware, '3.000', 4722.22],
                ['customer_invoice', 'outgoing', 'INV-2026-00018', 'draft', 'INV-ACME-018', 'ACME', null, '2026-05-13', '2026-06-12', 15900, $service, '10.000', 1472.22],
                ['customer_invoice', 'outgoing', 'INV-2026-00021', 'issued', 'INV-GLOBAL-021', 'GLOBAL', null, '2026-05-08', '2026-05-30', 58420, $hardware, '12.000', 4507.72],
                ['customer_invoice', 'outgoing', 'INV-2026-00022', 'part_paid', 'INV-SUN-022', 'SUNRISE', null, '2026-04-21', '2026-05-06', 41300, $service, '25.000', 1529.63],
                ['customer_invoice', 'outgoing', 'INV-2026-00023', 'issued', 'INV-TECH-023', 'TECHDYN', null, '2026-03-25', '2026-04-24', 23650, $hardware, '5.000', 4379.63],
                ['customer_invoice', 'outgoing', 'INV-2026-00024', 'issued', 'INV-INNOVA-024', 'INNOVA', null, '2026-02-20', '2026-03-22', 19800, $service, '12.000', 1527.78],
                ['purchase_request', 'incoming', 'PR-2026-00009', 'approved', 'REQ-OPS-009', null, 'BESTSUP', '2026-05-09', null, 12750, $hardware, '3.000', 3935.19],
                ['supplier_quotation', 'incoming', 'SQ-2026-00009', 'approved', 'SQ-BEST-009', null, 'BESTSUP', '2026-05-10', null, 12750, $hardware, '3.000', 3935.19],
                ['supplier_po', 'incoming', 'SPO-2026-00021', 'issued', 'SPO-BEST-021', null, 'BESTSUP', '2026-05-12', '2026-06-11', 9900, $hardware, '2.000', 4583.33],
                ['goods_receipt', 'incoming', 'GR-2026-00010', 'received', 'DO-BEST-110', null, 'BESTSUP', '2026-05-10', null, 9900, $hardware, '2.000', 4583.33],
                ['supplier_invoice', 'incoming', 'SIN-2026-00007', 'matched', 'SI-BEST-007', null, 'BESTSUP', '2026-05-09', '2026-06-08', 9900, $hardware, '2.000', 4583.33],
                ['supplier_invoice', 'incoming', 'SIN-2026-00008', 'issued', 'SI-METRO-008', null, 'METRO', '2026-04-28', '2026-05-28', 52890, $service, '34.000', 1440.90],
                ['supplier_invoice', 'incoming', 'SIN-2026-00009', 'issued', 'SI-PRIME-009', null, 'PRIMELOG', '2026-03-19', '2026-04-18', 31810, $service, '20.000', 1472.69],
            ];

            $seededDocs = collect();

            foreach ($documents as [$type, $direction, $number, $status, $reference, $customerCode, $supplierCode, $issueDate, $dueDate, $total, $product, $quantity, $unitPrice]) {
                $subtotal = round($total / 1.08, 2);
                $tax = round($total - $subtotal, 2);

                $document = Document::updateOrCreate(
                    ['type' => $type, 'document_number' => $number],
                    [
                        'direction' => $direction,
                        'external_reference' => $reference,
                        'customer_id' => $customerCode ? $customers[$customerCode]->id : null,
                        'supplier_id' => $supplierCode ? $suppliers[$supplierCode]->id : null,
                        'status' => $status,
                        'issue_date' => $issueDate,
                        'due_date' => $dueDate,
                        'currency' => 'MYR',
                        'subtotal' => $subtotal,
                        'tax_total' => $tax,
                        'total' => $total,
                        'notes' => 'Demo operating data for visual review.',
                        'terms' => 'Payment terms follow the customer or supplier profile.',
                        'created_by' => $admin->id,
                        'approved_by' => in_array($status, ['approved', 'issued', 'received', 'matched', 'paid', 'closed'], true) ? $admin->id : null,
                        'approved_at' => in_array($status, ['approved', 'issued', 'received', 'matched', 'paid', 'closed'], true) ? now()->subDays(2) : null,
                    ]
                );

                $document->items()->delete();
                $document->items()->create([
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => $quantity,
                    'unit' => $product->unit,
                    'unit_price' => $unitPrice,
                    'tax_rate' => 8,
                    'tax_amount' => $tax,
                    'line_total' => $total,
                ]);

                $seededDocs[$number] = $document;
            }

            Approval::whereIn('document_id', $seededDocs->pluck('id'))->delete();

            foreach (['CQ-2026-00001', 'CQ-2026-00012'] as $number) {
                Approval::create([
                    'document_id' => $seededDocs[$number]->id,
                    'requested_by' => $admin->id,
                    'status' => 'pending',
                    'comment' => 'Awaiting manager approval.',
                ]);
            }

            foreach (['CPO-2026-00015', 'SQ-2026-00009', 'PR-2026-00009', 'GR-2026-00010'] as $number) {
                Approval::create([
                    'document_id' => $seededDocs[$number]->id,
                    'requested_by' => $admin->id,
                    'decided_by' => $admin->id,
                    'status' => 'approved',
                    'comment' => 'Approved for demo workflow.',
                    'decided_at' => now()->subDay(),
                ]);
            }

            Approval::create([
                'document_id' => $seededDocs['SIN-2026-00009']->id,
                'requested_by' => $admin->id,
                'decided_by' => $admin->id,
                'status' => 'rejected',
                'comment' => 'Demo rejection event.',
                'decided_at' => now()->subDays(4),
            ]);

            Payment::whereIn('document_id', $seededDocs->pluck('id'))->delete();

            Payment::create([
                'document_id' => $seededDocs['INV-2026-00022']->id,
                'direction' => 'incoming',
                'payment_date' => '2026-05-11',
                'amount' => 12000,
                'method' => 'Bank transfer',
                'reference' => 'RCPT-2026-0051',
                'created_by' => $admin->id,
            ]);

            Payment::create([
                'document_id' => $seededDocs['SIN-2026-00007']->id,
                'direction' => 'outgoing',
                'payment_date' => '2026-05-12',
                'amount' => 9900,
                'method' => 'Bank transfer',
                'reference' => 'PAY-2026-0032',
                'created_by' => $admin->id,
            ]);
        });
    }
}
